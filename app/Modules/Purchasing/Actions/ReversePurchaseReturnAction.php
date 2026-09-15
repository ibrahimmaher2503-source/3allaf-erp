<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ExecuteCorrection;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Data\CorrectionReferenceData;
use App\Modules\Platform\Enums\CorrectionType;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReversePurchaseReturnAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.reverse');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reversal reason is required.'));
        }

        return DB::transaction(function () use ($actor, $actorId, $id, $reason, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status === 'reversed') {
                return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
            }
            if ($return->status !== 'approved') {
                throw new InvalidArgumentException(__('Only approved supplier returns can be reversed.'));
            }
            if (($return->approved_by === $actorId || $return->created_by === $actorId) && ! $actor->canBypassApproval()) {
                throw new InvalidArgumentException(__('The creator or original approver cannot reverse this supplier return.'));
            }
            $returnLines = $return->lines
                ->sortBy(static fn ($line): string => sprintf('%020d:%020d:%020d', $line->product_id, $line->purchase_invoice_line_id, $line->id))
                ->values();
            if ($returnLines->isEmpty()) {
                throw new InvalidArgumentException(__('The supplier return has no stock movements to reverse.'));
            }
            $lineIds = [];
            foreach ($returnLines as $line) {
                if ($line->purchase_return_id !== $return->id) {
                    throw new InvalidArgumentException(__('Every supplier return line must belong to the selected supplier return.'));
                }
                $lineIds[] = $line->id;
            }

            $originalMovements = StockMovement::query()
                ->where('source_type', PurchaseReturn::class)
                ->where('source_id', $return->id)
                ->where('movement_type', 'purchase_return')
                ->orderBy('source_line_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('source_line_id');
            if ($originalMovements->flatten(1)->count() !== count($lineIds)) {
                throw new InvalidArgumentException(__('The original stock movements do not match this supplier return.'));
            }
            $plans = [];
            $reversalKeys = [];
            foreach ($returnLines as $line) {
                $matches = $originalMovements->get($line->id);
                if ($matches === null || $matches->count() !== 1) {
                    throw new InvalidArgumentException(__('The original stock movement is missing; reversal is blocked.'));
                }
                $original = $matches->first();
                $lineQuantity = $this->decimal($line->quantity);
                $lineUnitCost = $this->decimal($line->unit_cost);
                $lineTotalCost = $this->decimal($line->total_cost);
                if ($this->compare($lineQuantity, '0') <= 0
                    || $original->product_id !== $line->product_id
                    || $original->store_id !== $return->store_id
                    || $original->movement_type !== 'purchase_return'
                    || $this->compare($original->quantity, bcsub('0', $lineQuantity, 6)) !== 0
                    || $this->compare($original->unit_cost, $lineUnitCost) !== 0
                    || $this->compare($original->total_cost, bcsub('0', $lineTotalCost, 4)) !== 0
                    || $this->compare($original->consumed_cost, '0') !== 0
                    || $original->source_type !== PurchaseReturn::class
                    || $original->source_id !== $return->id
                    || $original->source_line_id !== $line->id
                    || $original->idempotency_key !== 'purchase-return:'.$return->id.':line:'.$line->id
                    || $original->reversal_of_id !== null) {
                    throw new InvalidArgumentException(__('The original stock movement does not match its supplier return line.'));
                }
                $quantity = bcsub('0', $this->decimal($original->quantity), 6);
                $cost = bcsub('0', $this->decimal($original->total_cost), 4);
                $key = 'purchase-return-reversal:'.$return->id.':line:'.$line->id;
                $reversalKeys[$key] = true;
                $plans[$line->id] = ['line' => $line, 'original' => $original, 'key' => $key, 'quantity' => $quantity, 'cost' => $cost];
            }

            $existingReversals = StockMovement::query()
                ->whereIn('idempotency_key', array_keys($reversalKeys))
                ->orderBy('idempotency_key')
                ->lockForUpdate()
                ->get()
                ->keyBy('idempotency_key');
            $requiredQuantities = [];
            $requiredValues = [];
            foreach ($plans as $lineId => $plan) {
                $line = $plan['line'];
                $original = $plan['original'];
                $existing = $existingReversals->get($plan['key']);
                if ($existing !== null) {
                    if ($existing->product_id !== $line->product_id
                        || $existing->store_id !== $return->store_id
                        || $existing->movement_type !== 'purchase_return_reversal'
                        || $this->compare($existing->quantity, $plan['quantity']) !== 0
                        || $this->compare($existing->unit_cost, $original->unit_cost) !== 0
                        || $this->compare($existing->total_cost, $plan['cost']) !== 0
                        || $this->compare($existing->consumed_cost, '0') !== 0
                        || $existing->source_type !== PurchaseReturn::class
                        || $existing->source_id !== $return->id
                        || $existing->source_line_id !== $line->id
                        || $existing->reversal_of_id !== $original->id) {
                        throw new InvalidArgumentException(__('An existing reversal movement does not match its supplier return line.'));
                    }
                    $plans[$lineId]['existing_reversal'] = $existing;

                    continue;
                }
                $productId = $line->product_id;
                $requiredQuantities[$productId] = bcadd($requiredQuantities[$productId] ?? '0', $plan['quantity'], 6);
                $requiredValues[$productId] = bcadd($requiredValues[$productId] ?? '0', $plan['cost'], 4);
            }

            $balances = StockBalance::query()
                ->where('store_id', $return->store_id)
                ->whereIn('product_id', array_keys($requiredQuantities))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');
            foreach (array_keys($requiredQuantities) as $productId) {
                if ($balances->get($productId) === null) {
                    throw new InvalidArgumentException(__('The stock balance required for reversal is missing.'));
                }
                $this->decimal($requiredQuantities[$productId]);
                $this->decimal($requiredValues[$productId]);
            }

            $balanceStates = [];
            foreach ($plans as $lineId => $plan) {
                if (isset($plan['existing_reversal'])) {
                    continue;
                }
                $productId = $plan['line']->product_id;
                $balance = $balances->get($productId);
                $state = $balanceStates[$productId] ?? ['on_hand' => $this->decimal($balance->on_hand), 'total_value' => $this->decimal($balance->total_value), 'version' => $balance->version];
                $newQuantity = bcadd($state['on_hand'], $plan['quantity'], 6);
                $newValue = bcadd($state['total_value'], $plan['cost'], 4);
                $average = $this->compare($newQuantity, '0') === 0 ? '0.0000' : bcdiv($newValue, $newQuantity, 4);
                $plans[$lineId]['balance'] = $balance;
                $plans[$lineId]['balance_update'] = ['on_hand' => $newQuantity, 'total_value' => $newValue, 'average_cost' => $average, 'version' => $state['version'] + 1];
                $balanceStates[$productId] = ['on_hand' => $newQuantity, 'total_value' => $newValue, 'version' => $state['version'] + 1];
            }

            $reversalMovementIds = [];
            foreach ($plans as $plan) {
                if (isset($plan['existing_reversal'])) {
                    $reversalMovementIds[] = (int) $plan['existing_reversal']->id;

                    continue;
                }
                $line = $plan['line'];
                $original = $plan['original'];
                $reversal = StockMovement::query()->create(['product_id' => $line->product_id, 'store_id' => $return->store_id, 'movement_type' => 'purchase_return_reversal', 'quantity' => $plan['quantity'], 'unit_cost' => $original->unit_cost, 'total_cost' => $plan['cost'], 'consumed_cost' => 0, 'source_type' => PurchaseReturn::class, 'source_id' => $return->id, 'source_line_id' => $line->id, 'idempotency_key' => $plan['key'], 'reversal_of_id' => $original->id, 'posted_at' => now(), 'created_by' => $actorId]);
                $reversalMovementIds[] = $reversal->id;
                $plan['balance']->update($plan['balance_update']);
            }
            $before = $return->only(['status', 'lock_version']);
            $requestId = Context::get('request_id') ?? (string) Str::uuid();
            $reference = new CorrectionReferenceData(
                originalSourceType: $return->sourceType(), originalSourceId: $return->sourceId(),
                originalSourceVersion: $return->sourceVersion(), originalSourceHash: $return->sourceHash(),
                correctionType: CorrectionType::Reversal, correctionSourceType: StockMovement::class,
                correctionSourceId: (string) min($reversalMovementIds), reason: $reason,
                requestedBy: $actorId, approvedBy: $actorId,
                branchId: $return->sourceBranchId(), storeId: $return->sourceStoreId(),
                requestId: $requestId, idempotencyKey: 'purchase-return-reversal:'.$return->id, createdAt: now(),
            );
            app(ExecuteCorrection::class)->execute(
                $reference,
                $return,
                $actor,
                [CorrectionType::Reversal],
                fn (User $actor): mixed => Gate::forUser($actor)->authorize('purchase_returns.reverse'),
                function () use ($actorId, $return, $reason): PurchaseReturn {
                    $return->mutateApprovedDocument(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $actorId, 'reversal_reason' => $reason, 'updated_by' => $actorId, 'lock_version' => $return->lock_version + 1]);

                    return $return;
                },
            );
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'reverse_supplier_return', source: $return, before: $before, after: $return->only(['status', 'reversed_at', 'reversed_by', 'lock_version']), storeId: $return->store_id, reasonText: $reason, metadata: ['reversal_movement_type' => 'purchase_return_reversal', 'reversal_movement_ids' => $reversalMovementIds, 'correction_request_id' => $requestId]);

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal value.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }
}
