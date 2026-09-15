<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Actions\ReserveAssetAction;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ConfirmPartyBookingAction
{
    public function execute(User $actor, PartyBooking $booking, ?string $reason = null): PartyBooking
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.approve');

        return DB::transaction(function () use ($actor, $booking, $reason): PartyBooking {
            $booking = PartyBooking::query()->with('invoice')->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status, ['draft', 'tentative', 'rescheduled'], true)) {
                throw new InvalidArgumentException(__('Only a draft or rescheduled Party booking can be confirmed.'));
            }
            $store = Store::query()->visibleTo($actor)->whereKey($booking->store_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $conflicts = PartyBooking::query()->where('id', '<>', $booking->id)->where('store_id', $booking->store_id)->whereIn('status', ['tentative', 'confirmed', 'rescheduled', 'in_operation', 'completed_pending_settlement', 'closed'])->where('starts_at', '<', $booking->ends_at)->where('ends_at', '>', $booking->starts_at)->get(['id', 'location', 'resource_keys']);
            foreach ($conflicts as $candidate) {
                $sameLocation = mb_strtolower(trim((string) $candidate->location)) === mb_strtolower(trim((string) $booking->location));
                $sameResource = array_intersect(array_map('strval', (array) $booking->resource_keys), array_map('strval', (array) $candidate->resource_keys)) !== [];
                if ($sameLocation || $sameResource) {
                    throw new InvalidArgumentException(__('The Party schedule conflicts with an existing booking or resource reservation.'));
                }
            }
            foreach ($booking->invoice->lines()->where('line_type', 'rental_asset')->lockForUpdate()->get() as $line) {
                $asset = RentalAsset::query()->visibleTo($actor)->whereKey($line->rental_asset_id)->where('branch_id', $booking->branch_id)->where('store_id', $booking->store_id)->whereIn('status', ['available', 'reserved'])->first();
                if ($asset === null) {
                    throw new InvalidArgumentException(__('The selected rental asset is no longer available in this Party scope.'));
                }
                $reservation = app(ReserveAssetAction::class)->execute($actor, $asset, [
                    'starts_at' => $booking->starts_at->toIso8601String(),
                    'ends_at' => $booking->ends_at->toIso8601String(),
                    'timezone' => $booking->timezone,
                    'source_type' => 'party_booking',
                    'source_id' => (string) $booking->id,
                    'source_reference' => $booking->booking_number,
                    'idempotency_key' => 'party-booking:'.$booking->id.':asset:'.$asset->id.':schedule:'.hash('sha256', $booking->starts_at->toIso8601String().'|'.$booking->ends_at->toIso8601String()),
                ]);
                $line->mutateDraft(['resource_key' => $asset->code, 'asset_reservation_id' => $reservation->id]);
            }
            $before = $booking->only(['status', 'confirmed_at', 'confirmed_by']);
            $booking->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'change_reason' => filled($reason) ? trim($reason) : null, 'updated_by' => $actor->id, 'lock_version' => $booking->lock_version + 1]);
            $booking->invoice->update(['state' => 'active_working', 'updated_by' => $actor->id, 'lock_version' => $booking->invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('party', 'party_booking_confirmed', $booking, $before, $booking->only(['status', 'confirmed_at', 'confirmed_by']), (int) $store->branch_id, (int) $store->id, reasonText: filled($reason) ? trim($reason) : null);

            return $booking->fresh('invoice.lines');
        }, 5);
    }
}
