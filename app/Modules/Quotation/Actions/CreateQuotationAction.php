<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateQuotationAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, array $data): Quotation
    {
        Gate::forUser($user)->authorize('quotations.create');
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = Quotation::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_unless($user->is_super_admin || $user->canAccessBranch($existing->branch_id) || $user->canAccessStore($existing->store_id), 403);
                return $existing->fresh('lines');
            }
        }
        $activity = (string) ($data['activity_type'] ?? '');
        if (! in_array($activity, ['retail', 'party'], true)) throw ValidationException::withMessages(['activity_type' => __('Choose retail or party activity.')]);
        $store = Store::query()->findOrFail((int) $data['store_id']);
        abort_unless((int) $store->branch_id === (int) $data['branch_id'], 422);
        abort_unless($user->is_super_admin || $user->canAccessBranch($store->branch_id) || $user->canAccessStore($store->id), 403);
        if ($data['customer_id'] ?? null) abort_unless(Customer::query()->whereKey($data['customer_id'])->where(function ($q) use ($store): void { $q->whereHas('scopes', fn ($s) => $s->where('branch_id', $store->branch_id)->orWhere('store_id', $store->id)); })->exists(), 404);
        $lines = $this->validateLines($activity, (array) ($data['lines'] ?? []));
        if ($lines === []) throw ValidationException::withMessages(['lines' => __('At least one compatible quotation line is required.')]);

        return DB::transaction(function () use ($user, $data, $store, $activity, $lines, $idempotencyKey): Quotation {
            $quotation = Quotation::create([
                'public_id' => (string) Str::uuid(), 'quotation_number' => app(AllocateDocumentNumber::class)->executeForBranch('quotation', (int) $store->branch_id),
                'activity_type' => $activity, 'customer_id' => $data['customer_id'] ?? null, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                'valid_until' => $data['valid_until'], 'status' => 'draft', 'currency_code' => $data['currency_code'] ?? 'EGP',
                'terms' => $data['terms'] ?? null, 'notes' => $data['notes'] ?? null, 'subtotal' => 0, 'total' => 0,
                'source_type' => $data['source_type'] ?? null, 'source_id' => $data['source_id'] ?? null, 'source_reference' => $data['source_reference'] ?? null,
                'created_by' => $user->id, 'idempotency_key' => $idempotencyKey,
            ]);
            $subtotal = 0.0;
            foreach ($lines as $index => $line) {
                $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $subtotal += $lineTotal;
                $quotation->lines()->create([...$line, 'line_number' => $index + 1, 'line_total' => $lineTotal]);
            }
            $quotation->mutate(['subtotal' => $subtotal, 'total' => $subtotal]);
            app(RecordAuditEvent::class)->execute('quotations', 'quotation_created', $quotation, after: ['quotation_number' => $quotation->quotation_number, 'activity_type' => $quotation->activity_type, 'customer_id' => $quotation->customer_id, 'subtotal' => $quotation->subtotal, 'total' => $quotation->total, 'posting_effect' => false], branchId: $quotation->branch_id, storeId: $quotation->store_id, metadata: ['line_count' => count($lines), 'non_posting' => true]);
            return $quotation->fresh('lines');
        }, 5);
    }

    /** @param array<int, array<string, mixed>> $lines @return array<int, array<string, mixed>> */
    public function validateLines(string $activity, array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            $lineType = (string) ($line['line_type'] ?? '');
            if ($activity === 'retail' && $lineType !== 'product') throw ValidationException::withMessages(['lines' => __('Retail quotations accept product lines only.')]);
            if ($activity === 'party' && ! in_array($lineType, ['service', 'asset'], true)) throw ValidationException::withMessages(['lines' => __('Party quotations accept service or party-asset lines only.')]);
            if ($activity === 'retail' && ! filled($line['product_id'] ?? null)) throw ValidationException::withMessages(['lines' => __('Each retail quotation line must reference a product.')]);
            if ($activity === 'retail' && ! Product::query()->sellable()->whereKey((int) $line['product_id'])->exists()) throw ValidationException::withMessages(['lines' => __('Each retail quotation product must be an active sellable SKU.')]);
            $quantity = (float) ($line['quantity'] ?? 0); $price = (float) ($line['unit_price'] ?? -1);
            if ($quantity <= 0 || $price < 0) throw ValidationException::withMessages(['lines' => __('Quotation quantities and prices must be valid.')]);
            $result[] = ['line_type' => $lineType, 'product_id' => $line['product_id'] ?? null, 'description_ar' => trim((string) ($line['description_ar'] ?? '')), 'description_en' => trim((string) ($line['description_en'] ?? '')), 'quantity' => $quantity, 'unit_price' => $price, 'source_reference' => $line['source_reference'] ?? null];
        }
        return $result;
    }
}
