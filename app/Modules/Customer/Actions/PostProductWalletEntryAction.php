<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PostProductWalletEntryAction
{
    public function credit(User $actor, Customer $customer, Store $store, string $amount, string $sourceType, string $sourceId, string $idempotencyKey, ?string $sourceLineId = null, ?string $reference = null, ?string $reason = null): ProductWalletLedger
    {
        return $this->post($actor, $customer, $store, $amount, 'credit', $sourceType, $sourceId, $idempotencyKey, $sourceLineId, $reference, $reason);
    }

    public function debit(User $actor, Customer $customer, Store $store, string $amount, string $sourceType, string $sourceId, string $idempotencyKey, ?string $sourceLineId = null, ?string $reference = null, ?string $reason = null): ProductWalletLedger
    {
        return $this->post($actor, $customer, $store, $amount, 'debit', $sourceType, $sourceId, $idempotencyKey, $sourceLineId, $reference, $reason);
    }

    public function settle(User $actor, Customer $customer, Store $store, string $amount, string $direction, string $sourceType, string $sourceId, string $idempotencyKey, ?string $sourceLineId = null, ?string $reference = null, ?string $reason = null): ProductWalletLedger
    {
        $direction = strtolower(trim($direction));
        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException(__('A wallet settlement must declare credit or debit direction.'));
        }

        return $this->post($actor, $customer, $store, $amount, 'settlement', $sourceType, $sourceId, $idempotencyKey, $sourceLineId, $reference, $reason, $direction);
    }

    private function post(User $actor, Customer $customer, Store $store, string $amount, string $entryType, string $sourceType, string $sourceId, string $idempotencyKey, ?string $sourceLineId, ?string $reference, ?string $reason, ?string $direction = null): ProductWalletLedger
    {
        Gate::forUser($actor)->authorize('product_wallet.settle');
        $store = Store::query()->visibleTo($actor)->whereKey($store->id)->where('status', 'active')->firstOrFail();
        $store->loadMissing('company');
        $customer = Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->where('status', 'active')->firstOrFail();
        if ($store->branch_id === null) {
            throw new InvalidArgumentException(__('A wallet source requires a store linked to a branch.'));
        }
        $amount = WalletPolicy::decimal($amount);
        $sourceType = $this->normalizeSourceType($sourceType);
        $sourceId = trim($sourceId);
        $idempotencyKey = trim($idempotencyKey);
        if ($sourceId === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException(__('A wallet mutation requires a source and idempotency key.'));
        }
        $this->assertSource($actor, $customer, $store, $sourceType, $sourceId);
        $policy = WalletPolicy::for('product');
        WalletPolicy::assertOperation($policy, $entryType === 'settlement' ? 'settlement' : $entryType);
        $currency = WalletPolicy::currencyCode($store);
        $signedAmount = ($direction ?? $entryType) === 'debit' ? '-'.$amount : $amount;
        $reason = filled($reason) ? trim((string) $reason) : null;
        $reference = filled($reference) ? trim((string) $reference) : null;
        $payload = [
            'wallet' => 'product', 'customer_id' => (int) $customer->id, 'branch_id' => (int) $store->branch_id,
            'store_id' => (int) $store->id, 'entry_type' => $entryType, 'direction' => $direction,
            'amount' => $amount, 'signed_amount' => $signedAmount, 'currency_code' => $currency,
            'source_type' => $sourceType, 'source_id' => $sourceId, 'source_line_id' => $sourceLineId,
            'reference' => $reference, 'reason' => $reason,
        ];
        $payloadHash = WalletPolicy::payloadHash($payload);

        try {
            return DB::transaction(function () use ($actor, $customer, $store, $entryType, $amount, $signedAmount, $sourceType, $sourceId, $idempotencyKey, $sourceLineId, $reference, $reason, $payload, $payloadHash, $currency, $policy, $direction): ProductWalletLedger {
                Customer::query()->lockForUpdate()->findOrFail($customer->id);
                $existing = ProductWalletLedger::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw new InvalidArgumentException(__('This Product Wallet idempotency key was already used with a different payload.'));
                    }

                    return $existing;
                }

                $before = bcadd((string) (ProductWalletLedger::query()->where('customer_id', $customer->id)->sum('amount') ?: '0'), '0', 4);
                $after = bcadd($before, $signedAmount, 4);
                WalletPolicy::assertBalance($policy, $after);
                $entry = ProductWalletLedger::appendEntry([
                    ...$payload,
                    'entry_type' => $entryType,
                    'amount' => $signedAmount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'currency_code' => $currency,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_line_id' => $sourceLineId,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'reference' => $reference,
                    'reason' => $reason,
                    'created_by' => $actor->id,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'customer_id' => $customer->id,
                    'metadata' => ['wallet' => 'product', 'policy_versions' => ['settlement' => $policy['settlement_version'], 'visibility' => $policy['visibility_version']], 'direction' => $direction ?? $entryType],
                    'created_at' => now(),
                ]);
                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value',
                    event: 'product_wallet_'.$entryType.'_posted',
                    source: $entry,
                    before: ['balance' => $before],
                    after: ['balance' => $after, 'amount' => $signedAmount, 'entry_type' => $entryType],
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    reasonText: $reason,
                    metadata: ['wallet' => 'product', 'customer_id' => $customer->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'idempotency_key' => $idempotencyKey],
                );

                return $entry;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ProductWalletLedger::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && hash_equals((string) $existing->payload_hash, $payloadHash)) {
                return $existing;
            }
            throw $exception;
        }
    }

    private function normalizeSourceType(string $sourceType): string
    {
        $sourceType = trim($sourceType);

        return strtolower($sourceType) === 'sale' ? Sale::class : $sourceType;
    }

    private function assertSource(User $actor, Customer $customer, Store $store, string $sourceType, string $sourceId): void
    {
        if ($sourceType === Sale::class) {
            $sale = Sale::query()->visibleTo($actor)->approved()->whereKey($sourceId)->firstOrFail();
            if ((int) $sale->customer_id !== (int) $customer->id || (int) $sale->store_id !== (int) $store->id || (int) $sale->branch_id !== (int) $store->branch_id) {
                abort(403, __('The wallet source does not belong to this customer and store.'));
            }

            return;
        }

        if ($sourceType !== 'product_wallet_adjustments') {
            throw new InvalidArgumentException(__('Product Wallet settlement requires an approved retail sale source.'));
        }
    }
}
