<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\ProductWalletAdjustment;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RequestProductWalletAdjustmentAction
{
    public function execute(User $actor, Customer $customer, Store $store, string $operation, string $amount, string $sourceType, string $sourceId, string $reason, string $idempotencyKey, ?int $targetLedgerId = null, ?string $sourceLineId = null, ?string $sourceReference = null): ProductWalletAdjustment
    {
        Gate::forUser($actor)->authorize('product_wallet.adjust');
        $policy = WalletPolicy::for('product');
        $adjustmentPolicyVersion = WalletPolicy::assertAdjustmentEnabled('product');
        $operation = strtolower(trim($operation));
        if (! in_array($operation, ['adjustment', 'correction'], true)) {
            throw new InvalidArgumentException(__('Product Wallet adjustment operation is invalid.'));
        }
        $amount = WalletPolicy::decimal($amount, allowNegative: true);
        $sourceType = strtolower(trim($sourceType)) === 'sale' ? Sale::class : trim($sourceType);
        $sourceId = trim($sourceId);
        $reason = trim($reason);
        $idempotencyKey = trim($idempotencyKey);
        if ($sourceType === '' || $sourceId === '' || $reason === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException(__('A wallet adjustment requires a source, reason, and idempotency key.'));
        }
        if ($operation === 'correction' && $targetLedgerId === null) {
            throw new InvalidArgumentException(__('A wallet correction must reference the ledger entry being corrected.'));
        }
        $store = Store::query()->visibleTo($actor)->whereKey($store->id)->where('status', 'active')->firstOrFail();
        $customer = Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->where('status', 'active')->firstOrFail();
        $this->assertSource($actor, $customer, $store, $sourceType, $sourceId);
        $currency = WalletPolicy::currencyCode($store);
        $payload = [
            'wallet' => 'product', 'customer_id' => (int) $customer->id, 'branch_id' => (int) $store->branch_id,
            'store_id' => (int) $store->id, 'operation' => $operation, 'amount' => $amount,
            'target_ledger_id' => $targetLedgerId, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'source_line_id' => $sourceLineId, 'source_reference' => filled($sourceReference) ? trim($sourceReference) : null,
            'reason' => $reason, 'currency_code' => $currency,
        ];
        $payloadHash = WalletPolicy::payloadHash($payload);

        try {
            $requested = DB::transaction(function () use ($actor, $customer, $store, $operation, $amount, $targetLedgerId, $sourceType, $sourceId, $sourceLineId, $sourceReference, $reason, $idempotencyKey, $payload, $payloadHash, $adjustmentPolicyVersion): ProductWalletAdjustment {
                Customer::query()->lockForUpdate()->findOrFail($customer->id);
                $existing = ProductWalletAdjustment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw new InvalidArgumentException(__('This Product Wallet adjustment idempotency key was already used with a different payload.'));
                    }

                    return $existing->fresh(['approvalRecord']);
                }
                if ($operation === 'correction') {
                    $target = ProductWalletLedger::query()->lockForUpdate()->findOrFail($targetLedgerId);
                    if ((int) $target->customer_id !== (int) $customer->id || (int) $target->store_id !== (int) $store->id || in_array($target->entry_type, ['reversal'], true)) {
                        throw new InvalidArgumentException(__('The correction target does not belong to this customer/store or is already a reversal.'));
                    }
                }

                $adjustment = ProductWalletAdjustment::query()->create([
                    'customer_id' => $customer->id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                    'operation' => $operation, 'amount' => $amount, 'target_ledger_id' => $targetLedgerId,
                    'source_type' => $sourceType, 'source_id' => $sourceId, 'source_line_id' => $sourceLineId,
                    'source_reference' => filled($sourceReference) ? trim($sourceReference) : null,
                    'reason' => $reason, 'status' => 'pending', 'requested_by' => $actor->id,
                    'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
                    'lock_version' => 1, 'metadata' => ['wallet' => 'product', 'policy_version' => $adjustmentPolicyVersion],
                ]);
                $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                    sourceType: 'product_wallet_adjustments',
                    sourceId: (string) $adjustment->id,
                    sourceVersion: (string) $adjustment->lock_version,
                    requestedAction: $operation,
                    requestPermission: 'product_wallet.adjust',
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    reasonCode: 'manual_product_wallet_'.$operation,
                    reasonText: $reason,
                    limitContext: ['wallet' => 'product', 'amount' => $amount, 'operation' => $operation],
                    sourceHash: $payloadHash,
                    idempotencyKey: 'PRODUCT-WALLET-APPROVAL:'.$idempotencyKey,
                    decisionPermission: 'product_wallet.approve',
                ));
                $adjustment->transition(['approval_record_id' => $approval->id]);
                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value', event: 'product_wallet_adjustment_requested', source: $adjustment,
                    after: $adjustment->only(['customer_id', 'operation', 'amount', 'target_ledger_id', 'source_type', 'source_id', 'status', 'approval_record_id', 'lock_version']),
                    branchId: (int) $store->branch_id, storeId: (int) $store->id, reasonText: $reason,
                    metadata: ['wallet' => 'product', 'actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'approval_record_id' => $approval->id],
                );

                return $adjustment->fresh(['approvalRecord']);
            }, 5);

            if ($actor->canBypassApproval() && $requested->approvalRecord?->approval_state->value === 'pending') {
                app(ApproveProductWalletAdjustmentAction::class)->execute($actor, $requested->approvalRecord, $store);
            }

            return $requested->fresh(['approvalRecord']);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ProductWalletAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && hash_equals((string) $existing->payload_hash, $payloadHash)) {
                return $existing->fresh(['approvalRecord']);
            }
            throw $exception;
        }
    }

    private function assertSource(User $actor, Customer $customer, Store $store, string $sourceType, string $sourceId): void
    {
        if ($sourceType === Sale::class) {
            $sale = Sale::query()->visibleTo($actor)->approved()->whereKey($sourceId)->firstOrFail();
            if ((int) $sale->customer_id !== (int) $customer->id || (int) $sale->store_id !== (int) $store->id) {
                abort(403, __('The wallet adjustment source does not belong to this customer and store.'));
            }
        }
    }
}
