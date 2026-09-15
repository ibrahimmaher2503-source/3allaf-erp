<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitStockCountAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockCount
    {
        Gate::authorize('stock_counts.submit');

        return DB::transaction(function () use ($id): StockCount {
            $count = StockCount::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($count->status !== 'in_progress') {
                throw new InvalidArgumentException(__('Only an open stock count can be submitted.'));
            }
            if ($count->manager_id !== null && (int) $count->manager_id !== (int) auth()->id() && ! auth()->user()?->can('stock_counts.close')) abort(403);
            if ($count->lines->contains(fn ($line): bool => ! $line->is_counted || $line->completed_at === null)) {
                throw new InvalidArgumentException(__('Every scoped product/location must be counted or explicitly confirmed as zero before entry can close.'));
            }
            foreach ($count->lines as $line) {
                $this->scope->execute((int) $line->store_id);
                $toCutoff = StockMovement::query()->where('product_id',$line->product_id)->where('store_id',$line->store_id)->where('posted_at','>',$count->reference_at)->where('posted_at','<=',$line->completed_at)->sum('quantity');
                $afterCutoff = StockMovement::query()->where('product_id',$line->product_id)->where('store_id',$line->store_id)->where('posted_at','>',$line->completed_at)->sum('quantity');
                $expectedAtCutoff=bcadd((string)$line->reference_on_hand,(string)$toCutoff,6);
                $expectedClosing=bcadd($expectedAtCutoff,(string)$afterCutoff,6);
                $normalizedClosing=bcadd((string)$line->counted_quantity,(string)$afterCutoff,6);
                $line->update(['movement_quantity_after_reference'=>bcadd((string)$toCutoff,(string)$afterCutoff,6),'movement_after_completion'=>$afterCutoff,'expected_quantity'=>$expectedClosing,'normalized_closing_quantity'=>$normalizedClosing,'variance_quantity'=>bcsub($normalizedClosing,$expectedClosing,6)]);
            }
            $before = $count->only(['status', 'lock_version']);
            $count->update(['status' => 'submitted', 'entry_closed_at'=>now(), 'submitted_at' => now(), 'lock_version' => $count->lock_version + 1]);
            $branchId = Store::query()->whereKey($count->store_id)->value('branch_id');
            app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'stock_counts',
                sourceId: (string) $count->id,
                sourceVersion: (string) $count->lock_version,
                requestedAction: 'reconcile',
                requestPermission: 'stock_counts.submit',
                decisionPermission: 'stock_counts.reconcile',
                branchId: $branchId === null ? null : (int) $branchId,
                storeId: $count->store_id,
                limitContext: ['uncounted_lines' => 0, 'locations'=>$count->locations()->count()],
                idempotencyKey: 'stock-count-reconciliation:'.$count->id.':'.$count->lock_version,
            ));
            app(RecordAuditEvent::class)->execute('inventory', 'submit_stock_count', $count, $before, $count->only(['status', 'submitted_at', 'lock_version']), storeId: $count->store_id, metadata: ['uncounted_lines' => $count->lines->where('is_counted', false)->count()]);

            return $count->fresh(['store', 'lines.product']);
        });
    }
}
