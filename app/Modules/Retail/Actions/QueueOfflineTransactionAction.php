<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Retail\Enums\OfflineTransactionState;
use App\Modules\Retail\Models\OfflineDevice;
use App\Modules\Retail\Models\OfflineTransaction;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\OfflinePosPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class QueueOfflineTransactionAction
{
    public function __construct(
        private readonly OfflinePosPolicy $policy,
        private readonly PosCalculationService $calculator,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(User $actor, OfflineDevice $device, string $token, array $payload): OfflineTransaction
    {
        return DB::transaction(function () use ($actor, $device, $token, $payload): OfflineTransaction {
            $device = $this->policy->assertDeviceAccess($actor, $device, $token, true);
            if (! $actor->hasPermission('offline_queue_conflicts.submit')) {
                throw new InvalidArgumentException('Offline queue submission is not authorized.');
            }

            $canonical = $this->canonicalPayload($payload, $device);
            $existing = OfflineTransaction::query()->where('local_uuid', $canonical['local_uuid'])->lockForUpdate()->first();
            $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
            if ($existing !== null) {
                if ((int) $existing->offline_device_id !== (int) $device->id || ! hash_equals($existing->payload_hash, $hash)) {
                    throw new InvalidArgumentException('The local offline reference was already used with another payload.');
                }

                return $existing;
            }

            $this->assertQueueCapacity($device, $canonical['payment']['amount']);

            $transaction = OfflineTransaction::query()->create([
                'offline_device_id' => $device->id,
                'user_id' => $actor->id,
                'branch_id' => $device->branch_id,
                'store_id' => $device->store_id,
                'shift_id' => $device->shift_id,
                'local_uuid' => $canonical['local_uuid'],
                'state' => OfflineTransactionState::Queued,
                'policy_version' => (string) config('offline.policy_version'),
                'schema_version' => (string) config('offline.schema_version'),
                'payload_hash' => $hash,
                'canonical_payload' => $canonical,
                'captured_at' => $canonical['captured_at'],
                'price_cached_at' => $canonical['price_cached_at'],
                'expires_at' => now()->addMinutes((int) config('offline.limits.queue_expiry_minutes')),
            ]);
            app(RecordAuditEvent::class)->execute('retail', 'offline_transaction_queued', $transaction, null, [
                'state' => $transaction->state->value, 'local_uuid' => $transaction->local_uuid,
            ], (int) $transaction->branch_id, (int) $transaction->store_id, metadata: ['actor_id' => $actor->id, 'device_id' => $device->id]);

            return $transaction;
        });
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function canonicalPayload(array $payload, OfflineDevice $device): array
    {
        foreach (['customer_id', 'special_discount', 'loyalty_redemption', 'gift_card_id', 'wallet_id'] as $blocked) {
            if (array_key_exists($blocked, $payload)) {
                throw new InvalidArgumentException('This offline payload contains a prohibited operation.');
            }
        }
        if (in_array((string) ($payload['transaction_type'] ?? 'sale'), ['return', 'party'], true)) {
            throw new InvalidArgumentException('Returns and Party transactions cannot be queued offline.');
        }
        $uuid = trim((string) ($payload['local_uuid'] ?? ''));
        if ($uuid === '' || ! is_array($payload['lines'] ?? null) || count($payload['lines']) === 0 || ! is_array($payload['payment'] ?? null)) {
            throw new InvalidArgumentException('A bounded offline sale payload is required.');
        }
        $priceCachedAt = Carbon::parse((string) ($payload['price_cached_at'] ?? ''));
        if ($priceCachedAt->lt(now()->subMinutes((int) config('offline.limits.max_price_cache_age_minutes')))) {
            throw new InvalidArgumentException('The cached offline price is too old.');
        }
        $method = PaymentMethod::query()->findOrFail((int) ($payload['payment']['payment_method_id'] ?? 0));
        if ($method->status !== 'active' || ! $method->offline_eligible || ! in_array((string) $method->type, ['cash', 'manual_electronic'], true)) {
            throw new InvalidArgumentException('This payment method is not permitted offline.');
        }
        if (! (bool) config('offline.payments.'.($method->type === 'cash' ? 'cash' : 'manual_electronic'))) {
            throw new InvalidArgumentException('This offline payment method is disabled by policy.');
        }

        $lines = [];
        foreach ($payload['lines'] as $line) {
            if (! is_array($line) || ! empty($line['is_open_price']) || array_key_exists('open_price_amount', $line) || array_key_exists('discount_amount', $line)) {
                throw new InvalidArgumentException('Open price and discounts are not permitted offline.');
            }
            $productId = (int) ($line['product_id'] ?? 0);
            $priceVersionId = (int) ($line['price_version_id'] ?? 0);
            $quantity = (string) ($line['quantity'] ?? '');
            $unitPrice = (string) ($line['unit_price'] ?? '');
            if ($productId < 1 || $priceVersionId < 1 || ! preg_match('/^\d+$/', $quantity) || bccomp($quantity, '0', 0) <= 0 || ! preg_match('/^\d+(?:\.\d{1,3})?$/', $unitPrice)) {
                throw new InvalidArgumentException('Offline lines require a standard cached product, price, and positive quantity.');
            }
            $price = PriceLine::query()->where('product_id', $productId)->where('store_id', $device->store_id)->where('price_version_id', $priceVersionId)->first();
            if ($price === null || bccomp((string) $price->amount, $unitPrice, 3) !== 0) {
                throw new InvalidArgumentException('The supplied price is not the approved cached standard price.');
            }
            $lines[] = [
                'product_id' => $productId,
                'quantity' => DecimalMoney::normalize($quantity, 6),
                'unit_price' => DecimalMoney::normalize($unitPrice, 3),
                'price_version_id' => $priceVersionId,
            ];
        }

        $amount = trim((string) ($payload['payment']['amount'] ?? ''));
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Offline payment amount must be a positive two-decimal amount.');
        }
        $amount = DecimalMoney::normalize($amount, 2);
        $totals = $this->calculator->calculate(array_map(static fn (array $line): array => [
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'discount_amount' => '0.00',
        ], $lines));
        $payable = $method->isCash()
            ? bcadd($totals['total'], $this->calculator->cashRoundingAdjustment($totals['total']), 2)
            : $totals['total'];
        if (bccomp($amount, $payable, 2) !== 0) {
            throw new InvalidArgumentException('Offline payment amount must exactly settle the restricted sale total.');
        }

        return [
            'local_uuid' => $uuid,
            'captured_at' => Carbon::parse((string) ($payload['captured_at'] ?? now()))->toAtomString(),
            'price_cached_at' => $priceCachedAt->toAtomString(),
            'lines' => $lines,
            'payment' => ['payment_method_id' => $method->id, 'amount' => $amount],
        ];
    }

    /** @param numeric-string $amount */
    private function assertQueueCapacity(OfflineDevice $device, string $amount): void
    {
        $queued = OfflineTransaction::query()->where('offline_device_id', $device->id)
            ->whereIn('state', [OfflineTransactionState::Queued->value, OfflineTransactionState::Conflict->value])
            ->lockForUpdate()->get(['canonical_payload']);
        if ($queued->count() >= (int) config('offline.limits.max_transactions')) {
            throw new InvalidArgumentException('The offline transaction queue has reached its configured limit.');
        }
        if (bccomp($amount, (string) config('offline.limits.max_transaction_value'), 2) > 0) {
            throw new InvalidArgumentException('The offline transaction exceeds its configured value limit.');
        }

        $cumulative = '0.00';
        foreach ($queued as $transaction) {
            $cumulative = bcadd($cumulative, (string) data_get($transaction->canonical_payload, 'payment.amount', '0.00'), 2);
        }
        if (bccomp(bcadd($cumulative, $amount, 2), (string) config('offline.limits.max_cumulative_value'), 2) > 0) {
            throw new InvalidArgumentException('The offline queue would exceed its configured cumulative value limit.');
        }
    }
}
