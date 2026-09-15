<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ApproveStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockTransfer
    {
        Gate::authorize('transfers.approve');

        return DB::transaction(function () use ($id): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer);
            app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
            if ($transfer->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted transfers can be approved.'));
            }
            if ($transfer->source_store_id === $transfer->destination_store_id) {
                throw new InvalidArgumentException(__('Source and destination stores must be different.'));
            }
            $approval = ApprovalRecord::query()
                ->where('source_type', 'stock_transfers')
                ->where('source_id', (string) $transfer->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->first();
            if ($approval === null) {
                throw ValidationException::withMessages([
                    'approval' => __('This transfer has no pending approval request. Submit it for approval before approving.'),
                ]);
            }
            $before = $transfer->only(['status', 'lock_version']);
            $transfer->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(ApproveRequest::class)->execute($approval, (string) $before['lock_version'], decisionNote: __('Stock transfer approved.'));
            app(RecordAuditEvent::class)->execute('inventory', 'approve_stock_transfer', $transfer, $before, $transfer->only(['status', 'approved_by', 'approved_at', 'lock_version']), storeId: $transfer->source_store_id, metadata: ['approval_record_id' => $approval->id]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
