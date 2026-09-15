<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReleasePartyOperatingOrderAction
{
    public function execute(User $actor, PartyOperatingOrder $order): PartyOperatingOrder
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.approve');
        return DB::transaction(function () use ($actor, $order): PartyOperatingOrder {
            $order = PartyOperatingOrder::query()->with(['booking', 'invoice'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'draft') throw new InvalidArgumentException(__('Only a draft Party operating order can be released.'));
            if ($order->booking->status !== 'confirmed' || ! in_array($order->invoice->state, ['active_working', 'frozen_for_operation'], true)) throw new InvalidArgumentException(__('The Party booking and working invoice must be confirmed before release.'));
            $before = $order->only(['status', 'released_at', 'released_by']);
            $order->update(['status' => 'released', 'released_by' => $actor->id, 'released_at' => now(), 'lock_version' => $order->lock_version + 1]);
            $order->booking->update(['status' => 'in_operation', 'updated_by' => $actor->id, 'lock_version' => $order->booking->lock_version + 1]);
            $order->invoice->update(['state' => 'frozen_for_operation', 'updated_by' => $actor->id, 'lock_version' => $order->invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('party', 'party_operating_order_released', $order, $before, $order->only(['status', 'released_at', 'released_by']), (int) $order->branch_id, (int) $order->store_id);
            return $order->fresh(['booking', 'invoice', 'lines']);
        }, 5);
    }
}
