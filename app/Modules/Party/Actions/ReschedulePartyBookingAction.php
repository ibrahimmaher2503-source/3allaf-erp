<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReschedulePartyBookingAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, PartyBooking $booking, array $data): PartyBooking
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.edit');
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reschedule reason is required.'));
        }

        [$partyDate, $startsAt, $endsAt, $timezone, $location] = $this->schedule($data);

        return DB::transaction(function () use ($actor, $booking, $partyDate, $startsAt, $endsAt, $timezone, $location, $reason): PartyBooking {
            $booking = PartyBooking::query()->with(['invoice.lines', 'operatingOrders'])->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status, ['draft', 'tentative', 'confirmed', 'rescheduled'], true)) {
                throw new InvalidArgumentException(__('Only a booking that has not entered operation can be rescheduled.'));
            }
            if ($booking->operatingOrders->whereNotIn('status', ['cancelled'])->isNotEmpty() || $booking->invoice->payments()->exists()) {
                throw new InvalidArgumentException(__('A booking with operations or payments requires a controlled cancellation or settlement decision.'));
            }

            $conflict = PartyBooking::query()
                ->whereKeyNot($booking->id)
                ->where('store_id', $booking->store_id)
                ->whereIn('status', ['tentative', 'confirmed', 'rescheduled', 'in_operation', 'completed_pending_settlement'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->where(function ($query) use ($location, $booking): void {
                    $query->whereRaw('LOWER(TRIM(location)) = ?', [mb_strtolower($location)]);
                    foreach ((array) $booking->resource_keys as $resourceKey) {
                        $query->orWhereJsonContains('resource_keys', (string) $resourceKey);
                    }
                })
                ->exists();
            if ($conflict) {
                throw new InvalidArgumentException(__('The new Party schedule conflicts with an existing booking or resource reservation.'));
            }

            $before = $booking->only(['status', 'party_date', 'starts_at', 'ends_at', 'timezone', 'location']);
            $this->releaseReservations($booking);
            $booking->update([
                'party_date' => $partyDate,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'location' => $location,
                'status' => 'rescheduled',
                'change_reason' => $reason,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'updated_by' => $actor->id,
                'lock_version' => $booking->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute('party', 'party_booking_rescheduled', $booking, $before, $booking->only(['status', 'party_date', 'starts_at', 'ends_at', 'timezone', 'location']), (int) $booking->branch_id, (int) $booking->store_id, reasonText: $reason);

            return $booking->fresh(['invoice.lines']);
        }, 5);
    }

    /** @param array<string, mixed> $data @return array{string, string, string, string, string} */
    private function schedule(array $data): array
    {
        $partyDate = trim((string) ($data['party_date'] ?? ''));
        $timezone = trim((string) ($data['timezone'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        try {
            $startsAt = Carbon::createFromFormat('Y-m-d H:i', $partyDate.' '.trim((string) ($data['start_time'] ?? '')), $timezone);
            $endsAt = Carbon::createFromFormat('Y-m-d H:i', $partyDate.' '.trim((string) ($data['end_time'] ?? '')), $timezone);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('The new Party schedule is invalid.'));
        }
        if ($location === '' || $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException(__('The new Party end time must be after its start time and a location is required.'));
        }

        return [$partyDate, $startsAt->utc()->format('Y-m-d H:i:s'), $endsAt->utc()->format('Y-m-d H:i:s'), $timezone, $location];
    }

    private function releaseReservations(PartyBooking $booking): void
    {
        foreach ($booking->invoice->lines->whereNotNull('asset_reservation_id') as $line) {
            $reservation = AssetReservation::query()->lockForUpdate()->find($line->asset_reservation_id);
            if ($reservation?->status === 'reserved') {
                $reservation->update(['status' => 'cancelled', 'lock_version' => $reservation->lock_version + 1]);
                $asset = RentalAsset::query()->lockForUpdate()->find($reservation->asset_id);
                if ($asset !== null && $asset->status === 'reserved' && ! $asset->reservations()->where('status', 'reserved')->exists()) {
                    $asset->mutate(['status' => 'available', 'lock_version' => $asset->lock_version + 1]);
                }
            }
            $line->mutateDraft(['asset_reservation_id' => null]);
        }
    }
}
