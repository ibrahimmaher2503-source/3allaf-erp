<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Actions\InspectAssetAction;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class InspectPartyRentalAssetAction
{
    public function execute(User $actor, PartyOperatingOrder $order, PartyOperatingOrderLine $line, string $resultingStatus, string $assessment): AssetEvent
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.create');
        if (! in_array($resultingStatus, ['available', 'damaged', 'under_maintenance', 'lost'], true)) throw new InvalidArgumentException(__('The Party asset inspection outcome is invalid.'));
        if (trim($assessment) === '') throw new InvalidArgumentException(__('Party asset inspection findings are required.'));

        return DB::transaction(function () use ($actor, $order, $line, $resultingStatus, $assessment): AssetEvent {
            $order = PartyOperatingOrder::query()->lockForUpdate()->findOrFail($order->id);
            $line = PartyOperatingOrderLine::query()->lockForUpdate()->findOrFail($line->id);
            if ((int) $line->party_operating_order_id !== (int) $order->id || $line->line_type !== 'rental_asset' || $line->rental_asset_id === null || $line->asset_return_id === null) {
                throw new InvalidArgumentException(__('Only a returned rental asset line from this Party order can be inspected.'));
            }
            if (! in_array($order->status, ['released', 'in_progress'], true)) throw new InvalidArgumentException(__('Rental assets can only be inspected for an active Party order.'));
            $asset = RentalAsset::query()->visibleTo($actor)->whereKey($line->rental_asset_id)->where('branch_id', $order->branch_id)->where('store_id', $order->store_id)->firstOrFail();
            $return = AssetReturn::query()->whereKey($line->asset_return_id)->where('asset_id', $asset->id)->firstOrFail();
            $event = app(InspectAssetAction::class)->execute($actor, $asset, $return, $resultingStatus, trim($assessment));
            $before = $line->only(['asset_return_id', 'asset_inspection_event_id']);
            $line->update(['asset_inspection_event_id' => $event->id]);
            app(RecordAuditEvent::class)->execute('party', 'party_asset_inspected', $event, $before, $line->only(['asset_return_id', 'asset_inspection_event_id']), $order->branch_id, $order->store_id, reasonText: trim($assessment), metadata: ['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $asset->id, 'return_id' => $return->id]);
            return $event;
        }, 5);
    }
}
