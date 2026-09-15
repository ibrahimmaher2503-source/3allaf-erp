<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Retail\Enums\OfflineTransactionState;
use App\Modules\Retail\Models\OfflineConflict;
use App\Modules\Retail\Models\OfflineDevice;
use App\Modules\Retail\Models\OfflineTransaction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\OfflinePosPolicy;
use Illuminate\Support\Facades\DB;

final class SyncOfflineTransactionsAction
{
    public function __construct(private readonly OfflinePosPolicy $policy, private readonly RetailSaleAction $sales) {}

    /** @return array{accepted: int, conflicted: int} */
    public function execute(User $actor, OfflineDevice $device, string $token): array
    {
        return DB::transaction(function () use ($actor, $device, $token): array {
            $device = $this->policy->assertDeviceAccess($actor, $device, $token);
            if (! $actor->hasPermission('offline_queue_conflicts.submit')) {
                abort(403);
            }
            $batch = DB::table('offline_sync_batches')->insertGetId([
                'offline_device_id' => $device->id,
                'submitted_by' => $actor->id,
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $accepted = 0;
            $conflicted = 0;
            $transactions = OfflineTransaction::query()->where('offline_device_id', $device->id)
                ->where('state', OfflineTransactionState::Queued->value)->orderBy('captured_at')->lockForUpdate()->get();
            foreach ($transactions as $transaction) {
                if ($this->syncOne($actor, $transaction, $batch)) {
                    $accepted++;
                } else {
                    $conflicted++;
                }
            }
            DB::table('offline_sync_batches')->where('id', $batch)->update([
                'state' => 'completed', 'accepted_count' => $accepted, 'conflicted_count' => $conflicted, 'updated_at' => now(),
            ]);

            return compact('accepted', 'conflicted');
        });
    }

    private function syncOne(User $actor, OfflineTransaction $transaction, int $batchId): bool
    {
        if ($transaction->expires_at->isPast()) {
            return $this->conflict($actor, $transaction, $batchId, 'expiry', $transaction->expires_at->toAtomString(), now()->toAtomString());
        }
        $shift = PosShift::query()->lockForUpdate()->find($transaction->shift_id);
        if ($shift === null || $shift->status->value !== 'open' || ! DB::table('active_pos_shift_assignments')
            ->where('shift_id', $transaction->shift_id)->where('cashier_id', $actor->id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists()) {
            return $this->conflict($actor, $transaction, $batchId, 'shift', 'open', $shift?->status?->value ?? 'missing');
        }
        $payload = $transaction->canonical_payload;
        foreach ($payload['lines'] as $line) {
            $price = PriceLine::query()->lockForUpdate()->where('product_id', $line['product_id'])->where('store_id', $transaction->store_id)
                ->where('price_version_id', $line['price_version_id'])->first();
            if ($price === null || bccomp((string) $price->amount, (string) $line['unit_price'], 3) !== 0) {
                return $this->conflict($actor, $transaction, $batchId, 'price', (string) $line['unit_price'], $price === null ? 'missing' : DecimalMoney::normalize((string) $price->amount, 3));
            }
            $balance = StockBalance::query()->lockForUpdate()->where('product_id', $line['product_id'])->where('store_id', $transaction->store_id)->first();
            if ($balance === null || bccomp((string) $balance->on_hand, (string) $line['quantity'], 6) < 0) {
                return $this->conflict($actor, $transaction, $batchId, 'stock', (string) $line['quantity'], $balance === null ? '0.000000' : (string) $balance->on_hand);
            }
        }
        $method = PaymentMethod::query()->findOrFail((int) $payload['payment']['payment_method_id']);
        if ($method->status !== 'active' || ! $method->offline_eligible || ! in_array((string) $method->type, ['cash', 'manual_electronic'], true)
            || ! (bool) config('offline.payments.'.($method->isCash() ? 'cash' : 'manual_electronic'))) {
            return $this->conflict($actor, $transaction, $batchId, 'payment', 'offline_eligible', 'no_longer_eligible');
        }
        $store = Store::query()->findOrFail($transaction->store_id);
        $sale = $this->sales->create(
            $actor,
            $store,
            array_map(static fn (array $line): array => ['product_id' => $line['product_id'], 'quantity' => $line['quantity']], $payload['lines']),
            'OFFLINE:'.$transaction->offline_device_id.':'.$transaction->local_uuid,
            false,
            [['method' => $method, 'amount' => $payload['payment']['amount']]],
        );
        $transaction->update([
            'state' => OfflineTransactionState::Accepted,
            'server_sale_id' => $sale->id,
            'offline_sync_batch_id' => $batchId,
            'synced_at' => now(),
        ]);
        app(RecordAuditEvent::class)->execute('retail', 'offline_transaction_accepted', $transaction, ['state' => OfflineTransactionState::Queued->value], ['state' => OfflineTransactionState::Accepted->value, 'server_sale_id' => $sale->id], (int) $transaction->branch_id, (int) $transaction->store_id, metadata: ['actor_id' => $actor->id]);

        return true;
    }

    private function conflict(User $actor, OfflineTransaction $transaction, int $batchId, string $field, string $localValue, string $serverValue): bool
    {
        OfflineConflict::query()->updateOrCreate(
            ['offline_transaction_id' => $transaction->id, 'field' => $field],
            ['local_value' => $localValue, 'server_value' => $serverValue],
        );
        $transaction->update(['state' => OfflineTransactionState::Conflict, 'offline_sync_batch_id' => $batchId, 'synced_at' => now()]);
        app(RecordAuditEvent::class)->execute('retail', 'offline_transaction_conflicted', $transaction, ['state' => OfflineTransactionState::Queued->value], ['state' => OfflineTransactionState::Conflict->value, 'field' => $field], (int) $transaction->branch_id, (int) $transaction->store_id, metadata: ['actor_id' => $actor->id]);

        return false;
    }
}
