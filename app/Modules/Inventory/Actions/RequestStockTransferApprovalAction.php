<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RequestStockTransferApprovalAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockTransfer
    {
        Gate::authorize('transfers.submit');

        return DB::transaction(function () use ($id): StockTransfer {
            $transfer = StockTransfer::query()->with(['lines', 'sourceStore'])->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer);

            if ($transfer->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted transfers can be sent for approval.'));
            }
            if ($transfer->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A transfer must contain at least one line before approval.'));
            }

            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'stock_transfers',
                sourceId: (string) $transfer->id,
                sourceVersion: (string) $transfer->lock_version,
                requestedAction: 'approve',
                requestPermission: 'transfers.submit',
                decisionPermission: 'transfers.approve',
                branchId: $transfer->sourceStore?->branch_id,
                storeId: $transfer->source_store_id,
                reasonCode: $transfer->reason_code,
                reasonText: $transfer->reason_notes,
                idempotencyKey: 'stock-transfer-approval:'.$transfer->id.':'.$transfer->lock_version,
            ));

            app(RecordAuditEvent::class)->execute(
                'inventory',
                'request_stock_transfer_approval',
                $transfer,
                after: $transfer->only(['status', 'lock_version']),
                branchId: $transfer->sourceStore?->branch_id,
                storeId: $transfer->source_store_id,
                reasonCode: $transfer->reason_code,
                reasonText: $transfer->reason_notes,
                metadata: ['approval_record_id' => $approval->id],
            );

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
