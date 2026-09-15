<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use App\Support\ProductQuantity;

final class RecordPartyConsumableActualAction
{
    public function execute(User $actor, PartyOperatingOrder $order, PartyOperatingOrderLine $line, string $consumedQuantity): PartyOperatingOrderLine
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.edit');
        $consumedQuantity = $this->quantity($consumedQuantity);

        return DB::transaction(function () use ($order, $line, $consumedQuantity): PartyOperatingOrderLine {
            $order = PartyOperatingOrder::query()->lockForUpdate()->findOrFail($order->id);
            $line = PartyOperatingOrderLine::query()->lockForUpdate()->findOrFail($line->id);

            if ((int) $line->party_operating_order_id !== (int) $order->id || $line->line_type !== 'consumable') {
                throw new InvalidArgumentException(__('Only a consumable line from this Party order can record actual consumption.'));
            }
            if (! in_array($order->status, ['released', 'in_progress'], true)) {
                throw new InvalidArgumentException(__('Actual consumption can only be recorded for an active Party order.'));
            }

            $maximum = bcsub((string) $line->issued_quantity, (string) $line->returned_quantity, 6);
            if (bccomp($consumedQuantity, $maximum, 6) > 0) {
                throw new InvalidArgumentException(__('Actual consumption cannot exceed the issued quantity after returns.'));
            }

            $before = $line->only(['consumed_quantity', 'returned_quantity', 'issued_quantity']);
            $line->update(['consumed_quantity' => $consumedQuantity]);
            if ($order->status === 'released') {
                $order->update(['status' => 'in_progress', 'lock_version' => $order->lock_version + 1]);
            }

            app(RecordAuditEvent::class)->execute(
                'party',
                'party_consumable_actual_recorded',
                $line,
                $before,
                $line->only(['consumed_quantity', 'returned_quantity', 'issued_quantity']),
                (int) $order->branch_id,
                (int) $order->store_id,
                metadata: ['order_id' => $order->id, 'line_id' => $line->id],
            );

            return $line->fresh();
        }, 5);
    }

    private function quantity(string $value): string
    {
        $value = ProductQuantity::normalize($value);
        if (bccomp($value, '0', 0) < 0) {
            throw new InvalidArgumentException(__('Actual consumption must be a non-negative quantity.'));
        }

        return (string) $value;
    }
}
