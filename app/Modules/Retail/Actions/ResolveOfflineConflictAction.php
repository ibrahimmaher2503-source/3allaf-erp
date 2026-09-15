<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Enums\OfflineTransactionState;
use App\Modules\Retail\Models\OfflineConflict;
use App\Modules\Retail\Models\OfflineTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ResolveOfflineConflictAction
{
    public function execute(User $reviewer, OfflineTransaction $transaction, string $disposition, string $reason): OfflineTransaction
    {
        $reason = trim($reason);
        if ($reason === '' || $disposition !== 'reject') {
            throw new InvalidArgumentException('Only rejection is available until a source correction workflow exists.');
        }
        if (! $reviewer->hasPermission('offline_queue_conflicts.approve')
            || ! $reviewer->canAccessBranch((int) $transaction->branch_id)
            || ! $reviewer->canAccessStore((int) $transaction->store_id)) {
            abort(403);
        }

        return DB::transaction(function () use ($reviewer, $transaction, $disposition, $reason): OfflineTransaction {
            $transaction = OfflineTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->state !== OfflineTransactionState::Conflict) {
                throw new InvalidArgumentException('Only an unresolved offline conflict can be reviewed.');
            }
            OfflineConflict::query()->where('offline_transaction_id', $transaction->id)->whereNull('reviewed_at')->update([
                'disposition' => $disposition, 'reviewed_by' => $reviewer->id, 'reason' => $reason, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $transaction->update(['state' => OfflineTransactionState::Rejected]);
            app(RecordAuditEvent::class)->execute('retail', 'offline_conflict_reviewed', $transaction, ['state' => OfflineTransactionState::Conflict->value], ['state' => $transaction->state->value, 'disposition' => $disposition], (int) $transaction->branch_id, (int) $transaction->store_id, reasonText: $reason, metadata: ['actor_id' => $reviewer->id]);

            return $transaction;
        });
    }
}
