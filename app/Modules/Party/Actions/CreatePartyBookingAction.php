<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerChild;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyInvoiceLine;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreatePartyBookingAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Store $store, array $data): PartyBooking
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.create');

        $store = Store::query()->visibleTo($actor)->whereKey($store->id)->where('status', 'active')->firstOrFail();
        if ($store->branch_id === null || $store->type !== 'party') {
            throw new InvalidArgumentException(__('A Party booking must use an active Party store linked to a branch.'));
        }
        $store->loadMissing('company');
        $customer = Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey((int) ($data['customer_id'] ?? 0))->where('status', 'active')->firstOrFail();
        $child = null;
        if (filled($data['child_id'] ?? null)) {
            $child = CustomerChild::query()->where('customer_id', $customer->id)->whereKey((int) $data['child_id'])->where('status', 'active')->firstOrFail();
        }

        $normalized = $this->normalize($data, $store);
        $payloadHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A Party booking idempotency key is required.'));
        }

        try {
            return DB::transaction(function () use ($actor, $store, $customer, $child, $normalized, $payloadHash, $idempotencyKey): PartyBooking {
                $existing = PartyBooking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw new InvalidArgumentException(__('This Party booking idempotency key was already used with different data.'));
                    }

                    return $existing->load('invoice.lines');
                }

                $normalized = $this->resolveRentalAssets($actor, $store, $normalized);
                $this->assertNoConflict($store, $normalized['starts_at'], $normalized['ends_at'], $normalized['location'], $normalized['resource_keys']);
                $booking = PartyBooking::query()->create([
                    'booking_number' => app(AllocateDocumentNumber::class)->executeForBranch('party_booking', (int) $store->branch_id),
                    'customer_id' => $customer->id,
                    'child_id' => $child?->id,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'party_date' => $normalized['party_date'],
                    'starts_at' => $normalized['starts_at'],
                    'ends_at' => $normalized['ends_at'],
                    'timezone' => $normalized['timezone'],
                    'location' => $normalized['location'],
                    'primary_contact' => $normalized['primary_contact'],
                    'secondary_contact' => $normalized['secondary_contact'],
                    'notes' => $normalized['notes'],
                    'responsibilities' => $normalized['responsibilities'],
                    'resource_keys' => $normalized['resource_keys'],
                    'status' => 'draft',
                    'created_by' => $actor->id,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                ]);
                $invoice = PartyInvoice::query()->create([
                    'party_booking_id' => $booking->id,
                    'invoice_number' => app(AllocateDocumentNumber::class)->executeForBranch('party_invoice', (int) $store->branch_id),
                    'state' => 'draft',
                    'currency_code' => strtoupper((string) ($store->company?->currency_code ?? '')),
                    'created_by' => $actor->id,
                    'idempotency_key' => 'party-invoice:'.$idempotencyKey,
                ]);
                $this->writeLines($invoice, $normalized['lines']);
                $this->recalculate($invoice);
                app(RecordAuditEvent::class)->execute('party', 'party_booking_created', $booking, null, $booking->only(['booking_number', 'status', 'party_date', 'starts_at', 'ends_at', 'location', 'customer_id', 'child_id']), (int) $store->branch_id, (int) $store->id, metadata: ['invoice_id' => $invoice->id, 'line_count' => count($normalized['lines'])]);
                app(RecordAuditEvent::class)->execute('party', 'party_invoice_created', $invoice, null, $invoice->only(['invoice_number', 'state', 'total_amount']), (int) $store->branch_id, (int) $store->id, metadata: ['booking_id' => $booking->id]);

                return $booking->load('invoice.lines');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PartyBooking::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && hash_equals((string) $existing->payload_hash, $payloadHash)) {
                return $existing->load('invoice.lines');
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(array $data, Store $store): array
    {
        $timezone = trim((string) ($data['timezone'] ?? $store->company?->timezone ?? 'UTC'));
        $date = trim((string) ($data['party_date'] ?? ''));
        $start = trim((string) ($data['start_time'] ?? ''));
        $end = trim((string) ($data['end_time'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $contact = trim((string) ($data['primary_contact'] ?? ''));
        if ($date === '' || $start === '' || $end === '' || $location === '' || $contact === '') {
            throw new InvalidArgumentException(__('Party date, time, location, and primary contact are required.'));
        }
        try {
            $startsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$start, $timezone);
            $endsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$end, $timezone);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('Party date and time must be valid.'));
        }
        if ($startsAt === false || $endsAt === false || $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException(__('Party end time must be after the start time.'));
        }
        $lines = $this->normalizeLines($data['lines'] ?? []);

        return [
            'party_date' => $date,
            'starts_at' => $startsAt->utc()->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->utc()->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'location' => $location,
            'primary_contact' => $contact,
            'secondary_contact' => filled($data['secondary_contact'] ?? null) ? trim((string) $data['secondary_contact']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            'responsibilities' => array_values(array_filter(array_map('trim', (array) ($data['responsibilities'] ?? [])))),
            'resource_keys' => array_values(array_unique(array_filter(array_map(static fn (mixed $line): string => trim((string) ($line['resource_key'] ?? '')), $lines)))),
            'lines' => $lines,
        ];
    }

    /** @param mixed $raw @return list<array<string, mixed>> */
    private function normalizeLines(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw new InvalidArgumentException(__('At least one Party service or planned line is required.'));
        }
        $allowed = ['service', 'consumable', 'rental_asset', 'other'];
        $lines = [];
        foreach (array_values($raw) as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException(__('Each Party invoice line must be a structured Party line.'));
            }
            $type = trim((string) ($line['line_type'] ?? ''));
            if (! in_array($type, $allowed, true)) {
                throw new InvalidArgumentException(__('Retail lines cannot be added to a Party invoice.'));
            }
            $quantity = trim((string) ($line['quantity'] ?? ''));
            $price = trim((string) ($line['unit_price'] ?? ''));
            if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0) {
                throw new InvalidArgumentException(__('Party line quantity must be positive.'));
            }
            if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $price)) {
                throw new InvalidArgumentException(__('Party line price must be a non-negative decimal.'));
            }
            $productId = filled($line['product_id'] ?? null) ? (int) $line['product_id'] : null;
            if ($type === 'consumable' && $productId === null) {
                throw new InvalidArgumentException(__('A consumable Party line requires a product source.'));
            }
            if ($type === 'consumable' && ! Product::query()->sellable()->whereKey($productId)->exists()) {
                throw new InvalidArgumentException(__('A consumable Party line requires an active catalog product.'));
            }
            if ($type !== 'consumable' && $productId !== null) {
                throw new InvalidArgumentException(__('Only consumable Party lines may select a catalog product.'));
            }
            $rentalAssetId = filled($line['asset_id'] ?? null) ? (int) $line['asset_id'] : null;
            if ($type === 'rental_asset' && $rentalAssetId === null) {
                throw new InvalidArgumentException(__('A rental Party line must select an actual rental asset.'));
            }
            if ($type !== 'rental_asset' && $rentalAssetId !== null) {
                throw new InvalidArgumentException(__('Only rental asset Party lines may select a rental asset.'));
            }
            $description = trim((string) ($line['description'] ?? $line['description_en'] ?? $line['description_ar'] ?? ''));
            if ($description === '') {
                throw new InvalidArgumentException(__('Every Party line requires a description.'));
            }
            $lines[] = ['line_type' => $type, 'product_id' => $productId, 'rental_asset_id' => $rentalAssetId, 'description_ar' => trim((string) ($line['description_ar'] ?? $description)), 'description_en' => trim((string) ($line['description_en'] ?? $description)), 'quantity' => bcadd($quantity, '0', 6), 'unit_price' => bcadd($price, '0', 4), 'resource_key' => filled($line['resource_key'] ?? null) ? trim((string) $line['resource_key']) : null, 'source_reference' => filled($line['source_reference'] ?? null) ? trim((string) $line['source_reference']) : null];
        }

        return $lines;
    }

    /** @param list<array<string, mixed>> $lines */
    private function writeLines(PartyInvoice $invoice, array $lines): void
    {
        foreach ($lines as $index => $line) {
            PartyInvoiceLine::query()->create(['party_invoice_id' => $invoice->id, 'line_number' => $index + 1, ...$line, 'discount_amount' => '0.0000', 'tax_amount' => '0.0000', 'line_total' => bcmul((string) $line['quantity'], (string) $line['unit_price'], 4)]);
        }
    }

    private function recalculate(PartyInvoice $invoice): void
    {
        $subtotal = bcadd((string) $invoice->lines()->sum('line_total'), '0', 4);
        $invoice->update(['subtotal' => $subtotal, 'total_amount' => $subtotal, 'balance_due' => $subtotal]);
    }

    /** @param array<string, mixed> $normalized @return array<string, mixed> */
    private function resolveRentalAssets(User $actor, Store $store, array $normalized): array
    {
        $resourceKeys = (array) $normalized['resource_keys'];
        foreach ($normalized['lines'] as &$line) {
            if ($line['line_type'] !== 'rental_asset') {
                continue;
            }
            $asset = RentalAsset::query()->visibleTo($actor)->whereKey((int) $line['rental_asset_id'])->where('branch_id', $store->branch_id)->where('store_id', $store->id)->whereIn('status', ['available', 'reserved'])->first();
            if ($asset === null) {
                throw new InvalidArgumentException(__('The selected rental asset is not available in this Party store or scope.'));
            }
            $line['resource_key'] = $asset->code;
            $resourceKeys[] = $asset->code;
        }
        unset($line);
        $normalized['resource_keys'] = array_values(array_unique(array_filter(array_map('strval', $resourceKeys))));

        return $normalized;
    }

    /** @param list<string> $resourceKeys */
    private function assertNoConflict(Store $store, string $startsAt, string $endsAt, string $location, array $resourceKeys): void
    {
        $store->newQuery()->whereKey($store->id)->lockForUpdate()->first();
        $candidates = PartyBooking::query()->where('store_id', $store->id)->whereIn('status', ['tentative', 'confirmed', 'rescheduled', 'in_operation', 'completed_pending_settlement', 'closed'])->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->get(['id', 'location', 'resource_keys']);
        foreach ($candidates as $candidate) {
            $sameLocation = mb_strtolower(trim((string) $candidate->location)) === mb_strtolower($location);
            $sameResource = array_intersect($resourceKeys, array_map('strval', (array) $candidate->resource_keys)) !== [];
            if ($sameLocation || $sameResource) {
                throw new InvalidArgumentException(__('The Party schedule conflicts with an existing booking or resource reservation.'));
            }
        }
    }
}
