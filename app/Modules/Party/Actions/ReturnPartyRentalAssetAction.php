<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Actions\ReturnAssetAction;
use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReturnPartyRentalAssetAction
{
    public function execute(User $actor, PartyOperatingOrder $order, PartyOperatingOrderLine $line, string $conditionAfter, string $idempotencyKey): AssetReturn
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.create');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') throw new InvalidArgumentException(__('A Party asset return idempotency key is required.'));
        if (trim($conditionAfter) === '') throw new InvalidArgumentException(__('A post-return asset condition is required.'));

        return DB::transaction(function () use ($actor, $order, $line, $conditionAfter, $idempotencyKey): AssetReturn {
            $order = PartyOperatingOrder::query()->with('booking')->lockForUpdate()->findOrFail($order->id);
            $line = PartyOperatingOrderLine::query()->lockForUpdate()->findOrFail($line->id);
            if ((int) $line->party_operating_order_id !== (int) $order->id || $line->line_type !== 'rental_asset' || $line->rental_asset_id === null || $line->asset_checkout_id === null) {
                throw new InvalidArgumentException(__('Only a checked-out rental asset line from this Party order can be returned.'));
            }
            if (! in_array($order->status, ['released', 'in_progress'], true)) throw new InvalidArgumentException(__('Rental assets can only be returned for an active Party order.'));
            $asset = RentalAsset::query()->visibleTo($actor)->whereKey($line->rental_asset_id)->where('branch_id', $order->branch_id)->where('store_id', $order->store_id)->firstOrFail();
            $checkout = AssetCheckout::query()->whereKey($line->asset_checkout_id)->where('asset_id', $asset->id)->firstOrFail();
            $return = app(ReturnAssetAction::class)->execute($actor, $asset, $checkout, [
                'location_after' => $order->booking->location,
                'condition_after' => trim($conditionAfter),
                'notes' => 'Party operating order asset return.',
                'idempotency_key' => $idempotencyKey,
            ]);
            $before = $line->only(['asset_checkout_id', 'asset_return_id']);
            $line->update(['asset_return_id' => $return->id]);
            app(RecordAuditEvent::class)->execute('party', 'party_asset_returned', $return, $before, $line->only(['asset_checkout_id', 'asset_return_id']), $order->branch_id, $order->store_id, reasonText: 'Referenced Party asset return', metadata: ['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $asset->id, 'checkout_id' => $checkout->id]);
            return $return;
        }, 5);
    }
}
