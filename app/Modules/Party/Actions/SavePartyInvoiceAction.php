<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Product;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyInvoiceLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SavePartyInvoiceAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, PartyInvoice $invoice, array $data): PartyInvoice
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.edit');

        return DB::transaction(function () use ($actor, $invoice, $data): PartyInvoice {
            $invoice = PartyInvoice::query()->with('booking')->lockForUpdate()->findOrFail($invoice->id);
            if (! in_array($invoice->state, ['draft', 'active_working'], true) || $invoice->booking->isClosed()) {
                throw new InvalidArgumentException(__('This Party invoice is locked after final close or operational freeze.'));
            }
            $before = $invoice->only(['state', 'subtotal', 'total_amount', 'notes']);
            if (array_key_exists('notes', $data)) {
                $invoice->update(['notes' => filled($data['notes']) ? trim((string) $data['notes']) : null, 'updated_by' => $actor->id]);
            }
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($actor, $invoice, $data['lines']);
            }
            $total = bcadd((string) $invoice->lines()->sum('line_total'), '0', 4);
            $invoice->update(['subtotal' => $total, 'total_amount' => $total, 'balance_due' => bcsub($total, (string) $invoice->paid_amount, 4), 'updated_by' => $actor->id, 'lock_version' => $invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('party', 'party_invoice_edited', $invoice, $before, $invoice->only(['state', 'subtotal', 'total_amount', 'notes']), (int) $invoice->booking->branch_id, (int) $invoice->booking->store_id, reasonText: filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null);

            return $invoice->fresh('lines');
        }, 5);
    }

    private function replaceLines(User $actor, PartyInvoice $invoice, mixed $raw): void
    {
        if (! is_array($raw) || $raw === []) {
            throw new InvalidArgumentException(__('A Party invoice requires at least one line.'));
        }
        $allowed = ['service', 'consumable', 'rental_asset', 'other'];
        $existing = $invoice->lines()->get()->keyBy('line_number');
        $seen = [];
        foreach (array_values($raw) as $index => $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException(__('Each Party line must be structured.'));
            }
            $type = trim((string) ($line['line_type'] ?? ''));
            if (! in_array($type, $allowed, true)) {
                throw new InvalidArgumentException(__('Retail lines cannot be added to a Party invoice.'));
            }
            $quantity = trim((string) ($line['quantity'] ?? ''));
            $price = trim((string) ($line['unit_price'] ?? ''));
            $description = trim((string) ($line['description'] ?? $line['description_en'] ?? $line['description_ar'] ?? ''));
            if ($description === '' || ! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0 || ! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $price)) {
                throw new InvalidArgumentException(__('Party line description, quantity, and price are invalid.'));
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
            $number = $index + 1;
            $existingLine = $existing[$number] ?? null;
            $asset = null;
            $reservationId = $existingLine?->asset_reservation_id;
            if ($rentalAssetId !== null) {
                $asset = RentalAsset::query()->visibleTo($actor)->whereKey($rentalAssetId)->where('branch_id', $invoice->booking->branch_id)->where('store_id', $invoice->booking->store_id)->whereIn('status', ['available', 'reserved'])->first();
                if ($asset === null) {
                    throw new InvalidArgumentException(__('The selected rental asset is not available in this Party scope.'));
                }
                if ($existingLine?->asset_reservation_id !== null && (int) $existingLine->rental_asset_id !== $rentalAssetId) {
                    throw new InvalidArgumentException(__('Changing a reserved Party asset requires the approved reschedule flow.'));
                }
                if ($invoice->state === 'active_working' && $reservationId === null) {
                    throw new InvalidArgumentException(__('A new Party rental asset must be reserved through booking confirmation before operation.'));
                }
            }
            $attributes = ['line_type' => $type, 'product_id' => $productId, 'rental_asset_id' => $rentalAssetId, 'asset_reservation_id' => $reservationId, 'description_ar' => trim((string) ($line['description_ar'] ?? $description)), 'description_en' => trim((string) ($line['description_en'] ?? $description)), 'quantity' => bcadd($quantity, '0', 6), 'unit_price' => bcadd($price, '0', 4), 'discount_amount' => '0.0000', 'tax_amount' => '0.0000', 'line_total' => bcmul($quantity, $price, 4), 'resource_key' => $asset?->code ?? (filled($line['resource_key'] ?? null) ? trim((string) $line['resource_key']) : null), 'source_reference' => filled($line['source_reference'] ?? null) ? trim((string) $line['source_reference']) : null];
            if (isset($existing[$number])) {
                $existing[$number]->mutateDraft($attributes);
            } else {
                PartyInvoiceLine::query()->create(['party_invoice_id' => $invoice->id, 'line_number' => $number, ...$attributes]);
            }
            $seen[$number] = true;
        }
        foreach ($existing as $number => $line) {
            if (! isset($seen[$number])) {
                $line->deleteDraft();
            }
        }
    }
}
