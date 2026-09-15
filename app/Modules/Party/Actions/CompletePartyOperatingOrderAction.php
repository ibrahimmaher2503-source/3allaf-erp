<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CompletePartyOperatingOrderAction
{
    public function execute(User $actor, PartyOperatingOrder $order): PartyOperatingOrder
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.approve');
        return DB::transaction(function () use ($actor, $order): PartyOperatingOrder {
            $order = PartyOperatingOrder::query()->with(['booking', 'lines'])->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, ['released', 'in_progress'], true)) throw new InvalidArgumentException(__('Only an active Party operating order can be completed.'));
            foreach ($order->lines as $line) {
                $accounted = bcadd((string) $line->consumed_quantity, (string) $line->returned_quantity, 6);
                if (bccomp($line->planned_quantity, $line->issued_quantity, 6) > 0 || bccomp($line->issued_quantity, $accounted, 6) !== 0) throw new InvalidArgumentException(__('Every Party consumable must be issued and reconciled before completion.'));
                if ($line->line_type === 'rental_asset' && ($line->asset_reservation_id === null || $line->asset_checkout_id === null || $line->asset_return_id === null || $line->asset_inspection_event_id === null)) throw new InvalidArgumentException(__('Every Party rental asset must be checked out, returned, and inspected before completion.'));
            }
            $before = $order->only(['status', 'completed_at', 'completed_by']);
            $order->update(['status' => 'completed', 'completed_by' => $actor->id, 'completed_at' => now(), 'lock_version' => $order->lock_version + 1]);
            $order->booking->update(['status' => 'completed_pending_settlement', 'updated_by' => $actor->id, 'lock_version' => $order->booking->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('party', 'party_operating_order_completed', $order, $before, $order->only(['status', 'completed_at', 'completed_by']), (int) $order->branch_id, (int) $order->store_id);
            return $order->fresh(['booking', 'lines']);
        }, 5);
    }
}
