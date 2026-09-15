<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\SupplierAccountAdjustment;
use App\Modules\Purchasing\Support\SupplierBalance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PostSupplierAccountAdjustmentAction
{
    public function execute(User $actor, Supplier $supplier, string $direction, string $amount, string $reason, string $idempotencyKey, ?PurchaseInvoice $invoice = null, ?string $adjustmentDate = null, string $currencyCode = 'EGP', ?string $reference = null): SupplierAccountAdjustment
    {
        Gate::forUser($actor)->authorize('purchase_invoices.approve');
        $direction = strtolower(trim($direction));
        if (! in_array($direction, ['debit', 'credit'], true)) throw new InvalidArgumentException(__('Supplier adjustment direction must be debit or credit.'));
        $amount = $this->money($amount);
        $reason = trim($reason);
        $idempotencyKey = trim($idempotencyKey);
        $currencyCode = strtoupper(trim($currencyCode));
        $adjustmentDate ??= now()->toDateString();
        if ($reason === '' || $idempotencyKey === '') throw new InvalidArgumentException(__('Supplier adjustment reason and idempotency key are required.'));
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) throw new InvalidArgumentException(__('Currency code must contain three letters.'));
        $dateParts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $adjustmentDate, $matches) ? $matches : [];
        if ($dateParts === [] || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) throw new InvalidArgumentException(__('Supplier adjustment date is invalid.'));

        $payload = ['supplier_id' => (int) $supplier->id, 'invoice_id' => $invoice?->id, 'direction' => $direction, 'amount' => $amount, 'reason' => $reason, 'date' => $adjustmentDate, 'currency' => $currencyCode, 'reference' => $reference];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $supplier, $invoice, $direction, $amount, $reason, $idempotencyKey, $adjustmentDate, $currencyCode, $reference, $payloadHash): SupplierAccountAdjustment {
                $invoice = $invoice === null ? null : PurchaseInvoice::query()
                    ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                    ->lockForUpdate()
                    ->findOrFail($invoice->id);
                $existing = SupplierAccountAdjustment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) return $this->replay($existing, $payloadHash);
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
                if ($supplier->status !== 'active') throw new InvalidArgumentException(__('Adjustments can only be posted for an active supplier.'));
                if ($invoice !== null) {
                    if ((int) $invoice->supplier_id !== (int) $supplier->id || $invoice->status !== 'approved') throw new InvalidArgumentException(__('A supplier adjustment may reference only an approved invoice for the same supplier.'));
                    if (strtoupper((string) ($invoice->currency_code ?: 'EGP')) !== $currencyCode) throw new InvalidArgumentException(__('Supplier adjustment currency must match the referenced invoice.'));
                    if ($direction === 'credit' && bccomp($amount, app(SupplierBalance::class)->outstandingForInvoice($invoice), 4) > 0) throw new InvalidArgumentException(__('A supplier credit adjustment exceeds the invoice outstanding amount.'));
                }

                $adjustment = SupplierAccountAdjustment::query()->create([
                    'supplier_id' => $supplier->id, 'purchase_invoice_id' => $invoice?->id, 'adjustment_date' => $adjustmentDate,
                    'currency_code' => $currencyCode, 'direction' => $direction, 'amount' => $amount, 'reason' => $reason,
                    'reference' => filled($reference) ? trim($reference) : null, 'status' => 'approved', 'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash, 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(),
                ]);
                app(RecordAuditEvent::class)->execute('procurement', 'supplier_account_adjustment_posted', $adjustment, null, $adjustment->only(['direction', 'amount', 'currency_code', 'reason']), reasonText: $reason, metadata: ['supplier_id' => $supplier->id, 'purchase_invoice_id' => $invoice?->id, 'idempotency_key' => $idempotencyKey]);
                return $adjustment->fresh(['supplier', 'purchaseInvoice']);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = SupplierAccountAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) return $this->replay($existing, $payloadHash);
            throw $exception;
        }
    }

    private function replay(SupplierAccountAdjustment $adjustment, string $payloadHash): SupplierAccountAdjustment
    {
        if (! hash_equals((string) $adjustment->payload_hash, $payloadHash)) throw new InvalidArgumentException(__('This supplier adjustment idempotency key was already used with different data.'));
        return $adjustment->loadMissing(['supplier', 'purchaseInvoice']);
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value) || bccomp($value, '0', 4) <= 0) throw new InvalidArgumentException(__('Supplier adjustment amount must be a positive decimal.'));
        return bcadd($value, '0', 4);
    }
}
