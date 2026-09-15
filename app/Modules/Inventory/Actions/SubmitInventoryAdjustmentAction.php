<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitInventoryAdjustmentAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): InventoryAdjustment
    {
        Gate::authorize('inventory_stock_card.submit');

        $submitted = DB::transaction(function () use ($id): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->execute($adjustment->store_id);
            if ($adjustment->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft adjustments can be submitted.'));
            }
            if (trim($adjustment->reason_code) === '') {
                throw new InvalidArgumentException(__('An adjustment reason is required.'));
            }
            $before = $adjustment->only(['status', 'lock_version']);
            $adjustment->update(['status' => 'submitted', 'submitted_by' => Auth::id(), 'submitted_at' => now(), 'lock_version' => $adjustment->lock_version + 1]);
            $branchId = Store::query()->whereKey($adjustment->store_id)->value('branch_id');
            app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'inventory_adjustments',
                sourceId: (string) $adjustment->id,
                sourceVersion: (string) $adjustment->lock_version,
                requestedAction: 'approve',
                requestPermission: 'inventory_stock_card.submit',
                decisionPermission: 'inventory_stock_card.approve',
                branchId: $branchId === null ? null : (int) $branchId,
                storeId: $adjustment->store_id,
                reasonCode: $adjustment->reason_code,
                reasonText: $adjustment->reason_notes,
                idempotencyKey: 'inventory-adjustment-approval:'.$adjustment->id.':'.$adjustment->lock_version,
            ));
            app(RecordAuditEvent::class)->execute('inventory', 'submit_inventory_adjustment', $adjustment, $before, $adjustment->only(['status', 'submitted_by', 'submitted_at', 'lock_version']), storeId: $adjustment->store_id, reasonCode: $adjustment->reason_code, reasonText: $adjustment->reason_notes);

            return $adjustment->fresh(['store', 'lines.product']);
        });

        if (Auth::user()?->canBypassApproval()) {
            return app(ApproveInventoryAdjustmentAction::class)->execute($submitted->id);
        }

        return $submitted;
    }
}
