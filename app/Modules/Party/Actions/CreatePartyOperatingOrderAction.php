<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyOperatingOrderLine;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreatePartyOperatingOrderAction
{
    public function execute(User $actor, PartyBooking $booking, PartyInvoice $invoice, string $idempotencyKey): PartyOperatingOrder
    {
        Gate::forUser($actor)->authorize('party_operating_orders_consumables.create');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A Party operating-order idempotency key is required.'));
        }
        return DB::transaction(function () use ($actor, $booking, $invoice, $idempotencyKey): PartyOperatingOrder {
            $booking = PartyBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $invoice = PartyInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);
            if ((int) $invoice->party_booking_id !== (int) $booking->id || ! in_array($booking->status, ['confirmed', 'rescheduled'], true)) throw new InvalidArgumentException(__('An operating order requires a confirmed Party booking.'));
            if (! in_array($invoice->state, ['active_working', 'frozen_for_operation'], true)) throw new InvalidArgumentException(__('The Party working invoice is not ready for operation.'));
            $existing = PartyOperatingOrder::query()->where('idempotency_key', trim($idempotencyKey))->lockForUpdate()->first();
            if ($existing !== null) return $existing->load('lines');
            $order = PartyOperatingOrder::query()->create(['order_number' => app(AllocateDocumentNumber::class)->executeForBranch('party_operating_order', (int) $booking->branch_id), 'party_booking_id' => $booking->id, 'party_invoice_id' => $invoice->id, 'branch_id' => $booking->branch_id, 'store_id' => $booking->store_id, 'status' => 'draft', 'created_by' => $actor->id, 'idempotency_key' => trim($idempotencyKey)]);
            foreach ($invoice->lines as $line) {
                if ($line->line_type === 'rental_asset' && ($line->rental_asset_id === null || $line->asset_reservation_id === null)) throw new InvalidArgumentException(__('Every rental asset operating line must have an authoritative Party reservation.'));
                PartyOperatingOrderLine::query()->create(['party_operating_order_id' => $order->id, 'party_invoice_line_id' => $line->id, 'line_type' => $line->line_type, 'product_id' => $line->product_id, 'rental_asset_id' => $line->rental_asset_id, 'resource_key' => $line->resource_key, 'asset_reservation_id' => $line->asset_reservation_id, 'description' => $line->description_en, 'planned_quantity' => $line->line_type === 'consumable' ? $line->quantity : '0.000000', 'unit' => $line->line_type === 'consumable' ? 'unit' : null]);
            }
            app(RecordAuditEvent::class)->execute('party', 'party_operating_order_created', $order, null, $order->only(['order_number', 'status', 'party_booking_id', 'party_invoice_id']), (int) $booking->branch_id, (int) $booking->store_id, metadata: ['line_count' => $invoice->lines->count()]);
            return $order->load('lines');
        }, 5);
    }
}
