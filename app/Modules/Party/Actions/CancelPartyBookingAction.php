<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CancelPartyBookingAction
{
    public function execute(User $actor, PartyBooking $booking, string $reason): PartyBooking
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.cancel');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A cancellation reason is required.'));
        }

        return DB::transaction(function () use ($actor, $booking, $reason): PartyBooking {
            $booking = PartyBooking::query()->with(['invoice.lines', 'operatingOrders'])->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status, ['draft', 'tentative', 'confirmed', 'rescheduled'], true)) {
                throw new InvalidArgumentException(__('Only a booking that has not entered operation can be cancelled.'));
            }
            if ($booking->invoice->payments()->exists() || $booking->operatingOrders->whereNotIn('status', ['cancelled'])->isNotEmpty()) {
                throw new InvalidArgumentException(__('A booking with payments or operations requires a referenced settlement decision.'));
            }

            foreach ($booking->invoice->lines->whereNotNull('asset_reservation_id') as $line) {
                $reservation = AssetReservation::query()->lockForUpdate()->find($line->asset_reservation_id);
                if ($reservation?->status === 'reserved') {
                    $reservation->update(['status' => 'cancelled', 'lock_version' => $reservation->lock_version + 1]);
                    $asset = RentalAsset::query()->lockForUpdate()->find($reservation->asset_id);
                    if ($asset !== null && $asset->status === 'reserved' && ! $asset->reservations()->where('status', 'reserved')->exists()) {
                        $asset->mutate(['status' => 'available', 'lock_version' => $asset->lock_version + 1]);
                    }
                }
            }

            $before = $booking->only(['status', 'change_reason']);
            $booking->update(['status' => 'cancelled', 'change_reason' => $reason, 'updated_by' => $actor->id, 'lock_version' => $booking->lock_version + 1]);
            $booking->invoice->update(['state' => 'cancelled', 'updated_by' => $actor->id, 'lock_version' => $booking->invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('party', 'party_booking_cancelled', $booking, $before, $booking->only(['status', 'change_reason']), (int) $booking->branch_id, (int) $booking->store_id, reasonText: $reason);

            return $booking->fresh(['invoice']);
        }, 5);
    }
}
