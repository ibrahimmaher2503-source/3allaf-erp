<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Actions\CheckoutAssetAction;
use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CheckoutPartyRentalAssetAction
{
    public function execute(User $actor, PartyOperatingOrder $order, PartyOperatingOrderLine $line, string $idempotencyKey): AssetCheckout
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.create');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') throw new InvalidArgumentException(__('A Party asset checkout idempotency key is required.'));

        return DB::transaction(function () use ($actor, $order, $line, $idempotencyKey): AssetCheckout {
            $order = PartyOperatingOrder::query()->with('booking')->lockForUpdate()->findOrFail($order->id);
            $line = PartyOperatingOrderLine::query()->lockForUpdate()->findOrFail($line->id);
            $this->assertLine($order, $line);
            if (! in_array($order->status, ['released', 'in_progress'], true)) throw new InvalidArgumentException(__('Rental assets can only be checked out for a released Party order.'));
            $asset = RentalAsset::query()->visibleTo($actor)->whereKey($line->rental_asset_id)->where('branch_id', $order->branch_id)->where('store_id', $order->store_id)->firstOrFail();
            $reservation = AssetReservation::query()->whereKey($line->asset_reservation_id)->where('asset_id', $asset->id)->firstOrFail();
            $checkout = app(CheckoutAssetAction::class)->execute($actor, $asset, $reservation, [
                'source_reference' => $order->order_number,
                'location_after' => $order->booking->location,
                'notes' => 'Party operating order asset checkout.',
                'idempotency_key' => $idempotencyKey,
            ]);
            $before = $line->only(['asset_reservation_id', 'asset_checkout_id']);
            $line->update(['asset_checkout_id' => $checkout->id]);
            app(RecordAuditEvent::class)->execute('party', 'party_asset_checked_out', $checkout, $before, $line->only(['asset_reservation_id', 'asset_checkout_id']), $order->branch_id, $order->store_id, metadata: ['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $asset->id, 'reservation_id' => $reservation->id]);
            return $checkout;
        }, 5);
    }

    private function assertLine(PartyOperatingOrder $order, PartyOperatingOrderLine $line): void
    {
        if ((int) $line->party_operating_order_id !== (int) $order->id || $line->line_type !== 'rental_asset' || $line->rental_asset_id === null || $line->asset_reservation_id === null) {
            throw new InvalidArgumentException(__('Only a reserved rental asset line from this Party order can be checked out.'));
        }
    }
}
