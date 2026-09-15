<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ResolveTransferDifferenceAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id, string $differenceType, string $differenceReason): StockTransfer
    {
        Gate::authorize('transfers.difference');

        return DB::transaction(function () use ($id, $differenceType, $differenceReason): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: false, destination: true);
            if ($transfer->status !== 'difference_review' || $transfer->difference_status !== 'under_review') {
                throw new InvalidArgumentException(__('Only transfers under difference review can be resolved.'));
            }
            if (! in_array($differenceType, ['shortage', 'damage', 'refusal'], true) || trim($differenceReason) === '') {
                throw new InvalidArgumentException(__('A valid difference type and reason are required.'));
            }

            $lines = $transfer->lines->filter(fn ($line): bool => bccomp((string) $line->difference_quantity, '0', 6) > 0);
            if ($lines->isEmpty()) {
                throw new InvalidArgumentException(__('The transfer has no unresolved quantity.'));
            }
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $lines->each(fn ($line) => $line->mutateApprovedParentLine(['difference_type' => $differenceType, 'difference_reason' => $differenceReason]));
            $transfer->mutateApprovedDocument([
                'status' => 'received',
                'difference_status' => 'resolved',
                'reason_code' => $differenceType,
                'reason_notes' => $differenceReason,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'lock_version' => $transfer->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('inventory', 'resolve_transfer_difference', $transfer, $before, $transfer->only(['status', 'difference_status', 'approved_by', 'approved_at', 'lock_version']), storeId: $transfer->destination_store_id, reasonCode: $differenceType, reasonText: $differenceReason, metadata: ['resolved_quantity' => $lines->sum('difference_quantity'), 'resolved_lines' => $lines->pluck('id')->values()->all()]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
