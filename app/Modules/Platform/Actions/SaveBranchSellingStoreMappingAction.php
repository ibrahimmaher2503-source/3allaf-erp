<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveBranchSellingStoreMappingAction
{
    /**
     * Map a branch to a POS selling store with strict effective date ordering, duplicate prevention, DB transaction, correlation ID, and audit log.
     */
    public function execute(int $branchId, int $storeId, ?string $approvalNotes = null): BranchSellingStore
    {
        Gate::authorize('branches_stores.edit');
        $user = Auth::user();

        return DB::transaction(function () use ($branchId, $storeId, $approvalNotes, $user) {
            // Serialize mappings for a branch. Without a row lock, two
            // concurrent requests can both observe no active mapping (or the
            // same old mapping) and create multiple active rows.
            $branch = Branch::visibleTo($user)->lockForUpdate()->findOrFail($branchId);
            if ($branch->status !== 'active') {
                throw new InvalidArgumentException(__('Branch must be active to map a POS selling store.'));
            }

            $store = Store::visibleTo($user)->findOrFail($storeId);
            if ($store->type !== 'selling') {
                throw new InvalidArgumentException(__('Selected store must be of type Selling Store.'));
            }

            if ((int) $store->branch_id !== (int) $branch->id) {
                throw new InvalidArgumentException(__('Selected selling store must belong to the selected branch.'));
            }

            if ($store->status !== 'active') {
                throw new InvalidArgumentException(__('Selected selling store must be active.'));
            }

            // Check if this branch is already actively mapped to this exact store
            $currentActive = BranchSellingStore::query()->where('branch_id', $branch->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($currentActive && $currentActive->store_id === $store->id) {
                return $currentActive;
            }

            $now = now();

            // Close existing active mapping for this branch cleanly with effective_to ordering
            if ($currentActive) {
                $currentActive->update([
                    'status' => 'inactive',
                    'effective_to' => $now,
                ]);
            }

            // Create new active mapping record
            $mapping = BranchSellingStore::create([
                'branch_id' => $branch->id,
                'store_id' => $store->id,
                'effective_from' => $now,
                'effective_to' => null,
                'status' => 'active',
                // The historical column name is retained, but this is a direct audited edit,
                // not an approval workflow. Archive/delete remains approval-backed elsewhere.
                'approval_notes' => $approvalNotes,
                'created_by' => $user?->id,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'map_branch_selling_store',
                source: $mapping,
                after: $mapping->getAttributes(),
                branchId: $branch->id,
                storeId: $store->id,
                reasonText: $approvalNotes,
            );

            return $mapping;
        });
    }
}
