<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Policies\SupplierReturnPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ApprovePurchaseReturnAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actor, $actorId, $id, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with(['lines', 'reason'])
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status === 'approved') {
                return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
            }
            if ($return->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted supplier returns can be approved.'));
            }
            if ($return->created_by === $actorId && ! $actor->canBypassApproval()) {
                throw new InvalidArgumentException(__('The supplier return creator cannot approve the same return.'));
            }
            if ($return->reason === null || ! $return->reason->is_active) {
                throw new InvalidArgumentException(__('An active supplier return reason is required.'));
            }
            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_returns')
                ->where('source_id', (string) $return->id)
                ->where('requested_action', 'approve')
                ->where('store_id', $return->store_id)
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();
            $configuredLimit = app(SupplierReturnPolicy::class)->approvalLimit();
            if ($configuredLimit !== null && $this->compare($return->total_amount, $configuredLimit) > 0 && ! Gate::forUser($actor)->allows('purchase_returns.approve_over_limit')) {
                throw new InvalidArgumentException(__('This supplier return exceeds the configured approval limit and requires elevated approval permission.'));
            }
            if ($approval->source_type !== 'purchase_returns'
                || $approval->source_id !== (string) $return->id
                || $approval->requested_action !== 'approve'
                || $approval->store_id !== $return->store_id
                || $approval->approval_state !== ApprovalState::Pending) {
                throw new InvalidArgumentException(__('The approval request does not match this supplier return.'));
            }
            if ($approval->source_version !== null && $approval->source_version !== (string) $return->lock_version) {
                throw ValidationException::withMessages(['source_version' => __('The approval request is stale. Reload the source record and try again.')]);
            }
            if ($approval->requester_id === $actorId && ! $actor->canBypassApproval()) {
                throw ValidationException::withMessages(['approver' => __('A requester cannot approve their own request.')]);
            }
            Gate::forUser($actor)->authorize('decide', $approval);

            $invoice = PurchaseInvoice::query()
                ->where('store_id', $return->store_id)
                ->where('supplier_id', $return->supplier_id)
                ->lockForUpdate()
                ->findOrFail($return->purchase_invoice_id);
            if ($invoice->status !== 'approved') {
                throw new InvalidArgumentException(__('The source purchase invoice is no longer eligible for this supplier return.'));
            }

            $returnLines = $return->lines
                ->sortBy(static fn (PurchaseReturnLine $line): string => sprintf('%020d:%020d:%020d', $line->product_id, $line->purchase_invoice_line_id, $line->id))
                ->values();
            $sourceLineIds = [];
            foreach ($returnLines as $returnLine) {
                if ($returnLine->purchase_return_id !== $return->id) {
                    throw new InvalidArgumentException(__('Every supplier return line must belong to the selected supplier return.'));
                }
                if (isset($sourceLineIds[$returnLine->purchase_invoice_line_id])) {
                    throw new InvalidArgumentException(__('A source invoice line can appear only once.'));
                }
                $sourceLineIds[$returnLine->purchase_invoice_line_id] = true;
            }
            $sourceLines = PurchaseInvoiceLine::query()
                ->where('purchase_invoice_id', $invoice->id)
                ->whereIn('id', array_keys($sourceLineIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $linePlans = [];
            $movementKeys = [];
            $requiredQuantities = [];
            foreach ($returnLines as $returnLine) {
                $sourceLine = $sourceLines->get($returnLine->purchase_invoice_line_id);
                if ($sourceLine === null || $sourceLine->purchase_invoice_id !== $invoice->id) {
                    throw new InvalidArgumentException(__('Every supplier return line must reference a line from the source invoice.'));
                }
                if ($returnLine->product_id !== $sourceLine->product_id) {
                    throw new InvalidArgumentException(__('Supplier return products must match their source invoice lines.'));
                }
                $quantity = $this->decimal($returnLine->quantity);
                if ($this->compare($quantity, '0') <= 0) {
                    throw new InvalidArgumentException(__('Return quantity must be greater than zero.'));
                }
                if ($this->compare($returnLine->unit_cost, $sourceLine->unit_cost) !== 0) {
                    throw new InvalidArgumentException(__('Supplier return cost must equal the original purchase invoice line cost.'));
                }
                $alreadyReturned = $this->decimal((string) PurchaseReturnLine::query()
                    ->where('purchase_invoice_line_id', $sourceLine->id)
                    ->where('id', '!=', $returnLine->id)
                    ->whereHas('purchaseReturn', static fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled', 'reversed']))
                    ->sum('quantity'));
                $received = $this->decimal($sourceLine->quantity_received);
                $remaining = bcsub($received, $alreadyReturned, 6);
                if ($this->compare($quantity, $received) > 0 || $this->compare($quantity, $remaining) > 0) {
                    throw new InvalidArgumentException(__('Return quantity exceeds the remaining quantity from the original invoice line.'));
                }
                $totalCost = $this->decimal($returnLine->total_cost);
                $movementKey = 'purchase-return:'.$return->id.':line:'.$returnLine->id;
                $movementKeys[$movementKey] = true;
                $linePlans[$returnLine->id] = [
                    'return_line' => $returnLine,
                    'quantity' => $quantity,
                    'unit_cost' => $this->decimal($returnLine->unit_cost),
                    'total_cost' => $totalCost,
                    'movement_key' => $movementKey,
                ];
            }

            $existingMovements = StockMovement::query()
                ->whereIn('idempotency_key', array_keys($movementKeys))
                ->orderBy('idempotency_key')
                ->lockForUpdate()
                ->get()
                ->keyBy('idempotency_key');
            foreach ($linePlans as $lineId => $plan) {
                $returnLine = $plan['return_line'];
                $existingMovement = $existingMovements->get($plan['movement_key']);
                if ($existingMovement !== null) {
                    if ($existingMovement->product_id !== $returnLine->product_id
                        || $existingMovement->store_id !== $return->store_id
                        || $existingMovement->movement_type !== 'purchase_return'
                        || $this->compare($existingMovement->quantity, bcsub('0', $plan['quantity'], 6)) !== 0
                        || $this->compare($existingMovement->unit_cost, $plan['unit_cost']) !== 0
                        || $this->compare($existingMovement->total_cost, bcsub('0', $plan['total_cost'], 4)) !== 0
                        || $this->compare($existingMovement->consumed_cost, '0') !== 0
                        || $existingMovement->source_type !== PurchaseReturn::class
                        || $existingMovement->source_id !== $return->id
                        || $existingMovement->source_line_id !== $returnLine->id
                        || $existingMovement->reversal_of_id !== null) {
                        throw new InvalidArgumentException(__('An existing stock movement does not match this supplier return line.'));
                    }
                    $linePlans[$lineId]['existing_movement'] = $existingMovement;

                    continue;
                }
                $productId = $returnLine->product_id;
                $requiredQuantities[$productId] = bcadd($requiredQuantities[$productId] ?? '0', $plan['quantity'], 6);
            }

            $balances = StockBalance::query()
                ->where('store_id', $return->store_id)
                ->whereIn('product_id', array_keys($requiredQuantities))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');
            foreach ($requiredQuantities as $productId => $requiredQuantity) {
                $balance = $balances->get($productId);
                if ($balance === null || $this->compare($balance->on_hand, $requiredQuantity) < 0) {
                    throw new InvalidArgumentException(__('Cannot approve the supplier return because current on-hand stock is insufficient.'));
                }
            }

            $balanceStates = [];
            foreach ($linePlans as $lineId => $plan) {
                if (isset($plan['existing_movement'])) {
                    continue;
                }
                $productId = $plan['return_line']->product_id;
                $balance = $balances->get($productId);
                $state = $balanceStates[$productId] ?? [
                    'on_hand' => $this->decimal($balance->on_hand),
                    'total_value' => $this->decimal($balance->total_value),
                    'version' => $balance->version,
                ];
                $newQuantity = bcsub($state['on_hand'], $plan['quantity'], 6);
                $newValue = bcsub($state['total_value'], $plan['total_cost'], 4);
                $newAverage = $this->compare($newQuantity, '0') === 0 ? '0.0000' : bcdiv($newValue, $newQuantity, 4);
                $linePlans[$lineId]['balance'] = $balance;
                $linePlans[$lineId]['balance_update'] = [
                    'on_hand' => $newQuantity,
                    'average_cost' => $newAverage,
                    'total_value' => $newValue,
                    'version' => $state['version'] + 1,
                ];
                $balanceStates[$productId] = ['on_hand' => $newQuantity, 'total_value' => $newValue, 'version' => $state['version'] + 1];
            }

            $movementIds = [];
            foreach ($linePlans as $plan) {
                if (isset($plan['existing_movement'])) {
                    continue;
                }
                $returnLine = $plan['return_line'];
                $movement = StockMovement::query()->create([
                    'product_id' => $returnLine->product_id,
                    'store_id' => $return->store_id,
                    'movement_type' => 'purchase_return',
                    'quantity' => bcsub('0', $plan['quantity'], 6),
                    'unit_cost' => $plan['unit_cost'],
                    'total_cost' => bcsub('0', $plan['total_cost'], 4),
                    'consumed_cost' => 0,
                    'source_type' => PurchaseReturn::class,
                    'source_id' => $return->id,
                    'source_line_id' => $returnLine->id,
                    'idempotency_key' => $plan['movement_key'],
                    'posted_at' => now(),
                    'created_by' => $actorId,
                ]);
                $movementIds[] = $movement->id;
                $plan['balance']->update($plan['balance_update']);
            }

            app(ApprovalRecordTransition::class)->execute(
                record: $approval,
                state: ApprovalState::Approved,
                event: 'approval_approved',
                attributes: ['approver_id' => $actorId, 'decision_note' => 'Supplier return stock/cost posting approved.', 'decided_at' => now()],
                expectedSourceVersion: (string) $return->lock_version,
                authorize: function (ApprovalRecord $locked) use ($actor): mixed {
                    if ($locked->requester_id === $actor->id && ! $actor->canBypassApproval()) {
                        throw ValidationException::withMessages(['approver' => __('A requester cannot approve their own request.')]);
                    }

                    return Gate::forUser($actor)->authorize('decide', $locked);
                },
            );
            $before = $return->only(['return_number', 'status', 'total_amount', 'lock_version']);
            $return->update([
                'return_number' => $return->return_number ?: app(AllocatePurchaseReturnNumberAction::class)->execute((int) $return->store->branch_id),
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => $return->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'approve_supplier_return',
                source: $return,
                before: $before,
                after: $return->only(['return_number', 'status', 'approved_at', 'approved_by', 'lock_version']),
                storeId: $return->store_id,
                reasonCode: $return->reason->code,
                metadata: [
                    'stock_posted' => true,
                    'movement_ids' => $movementIds,
                    'approval_record_id' => $approval->id,
                    'approval_limit' => $configuredLimit,
                    'cost_source' => 'original_purchase_invoice_line_unit_cost',
                    'wac_recalculated' => true,
                ],
            );

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
