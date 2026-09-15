<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\CashControl\Actions\RecordCashTransactionAction;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Support\SupplierBalance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordSupplierPaymentAction
{
    /** @param array<int, array{purchase_invoice_id:int, amount:string}> $allocations */
    public function execute(User $actor, Supplier $supplier, PaymentMethod $method, string $amount, array $allocations, string $idempotencyKey, ?string $paymentDate = null, string $currencyCode = 'EGP', ?int $cashAccountId = null, ?string $reference = null, ?string $evidenceReference = null, ?string $notes = null): SupplierPayment
    {
        Gate::forUser($actor)->authorize('purchase_invoices.approve');
        $amount = $this->money($amount);
        $currencyCode = $this->currency($currencyCode);
        $paymentDate = $this->date($paymentDate ?? now()->toDateString());
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A supplier payment idempotency key is required.'));
        }

        $allocations = collect($allocations)->map(fn (array $allocation): array => [
            'purchase_invoice_id' => (int) ($allocation['purchase_invoice_id'] ?? 0),
            'amount' => $this->money((string) ($allocation['amount'] ?? '')),
        ])->sortBy('purchase_invoice_id')->values()->all();
        if ($allocations === [] || count(array_unique(array_column($allocations, 'purchase_invoice_id'))) !== count($allocations)) {
            throw new InvalidArgumentException(__('Supplier payment allocations must reference distinct invoices.'));
        }
        if (bccomp(array_reduce($allocations, fn (string $total, array $allocation): string => bcadd($total, $allocation['amount'], 4), '0.0000'), $amount, 4) !== 0) {
            throw new InvalidArgumentException(__('Supplier payment allocations must equal the payment amount.'));
        }

        $payload = ['supplier_id' => (int) $supplier->id, 'method_id' => (int) $method->id, 'amount' => $amount, 'allocations' => $allocations, 'payment_date' => $paymentDate, 'currency_code' => $currencyCode, 'cash_account_id' => $cashAccountId, 'reference' => $reference, 'evidence_reference' => $evidenceReference, 'notes' => $notes];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $supplier, $method, $amount, $allocations, $idempotencyKey, $paymentDate, $currencyCode, $cashAccountId, $reference, $evidenceReference, $notes, $payloadHash): SupplierPayment {
                $invoiceIds = array_column($allocations, 'purchase_invoice_id');
                $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
                $invoices = PurchaseInvoice::query()->whereIn('store_id', $visibleStoreIds)->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($invoices->count() !== count($invoiceIds)) {
                    throw new InvalidArgumentException(__('Every supplier payment allocation must reference an existing invoice.'));
                }
                $existing = SupplierPayment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replay($existing, $payloadHash);
                }
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
                if ($supplier->status !== 'active') {
                    throw new InvalidArgumentException(__('Payments can only be recorded for an active supplier.'));
                }
                $method = PaymentMethod::query()->whereKey($method->id)->where('status', 'active')->firstOrFail();
                if ((bool) $method->requires_evidence && ! filled($evidenceReference)) {
                    throw new InvalidArgumentException(__('This payment method requires evidence reference.'));
                }

                foreach ($allocations as $allocation) {
                    $invoice = $invoices->get($allocation['purchase_invoice_id']);
                    if ((int) $invoice->supplier_id !== (int) $supplier->id || $invoice->status !== 'approved') {
                        throw new InvalidArgumentException(__('Supplier payments may allocate only to approved invoices for the same supplier.'));
                    }
                    if (strtoupper((string) ($invoice->currency_code ?: 'EGP')) !== $currencyCode) {
                        throw new InvalidArgumentException(__('Supplier payment currency must match every allocated invoice.'));
                    }
                    if (bccomp($allocation['amount'], app(SupplierBalance::class)->outstandingForInvoice($invoice), 4) > 0) {
                        throw new InvalidArgumentException(__('A supplier payment allocation exceeds the invoice outstanding amount.'));
                    }
                }
                $cashAccount = null;
                if ($method->isCash() && $cashAccountId === null) {
                    throw new InvalidArgumentException(__('Cash supplier payments require a cash account.'));
                }
                if (! $method->isCash() && $cashAccountId !== null) {
                    throw new InvalidArgumentException(__('Non-cash supplier payments cannot use a cash account.'));
                }
                if ($cashAccountId !== null) {
                    $companyIds = Store::query()->whereIn('id', $invoices->pluck('store_id'))->pluck('company_id')->filter()->unique();
                    if ($companyIds->count() !== 1) {
                        throw new InvalidArgumentException(__('Supplier payment allocations must belong to one company when a cash account is selected.'));
                    }
                    $cashAccount = CashAccount::query()->where('company_id', $companyIds->first())->where('currency_code', $currencyCode)->where('type', 'cash')->where('status', 'active')->lockForUpdate()->findOrFail($cashAccountId);
                }

                $payment = SupplierPayment::query()->create([
                    'supplier_id' => $supplier->id, 'payment_method_id' => $method->id, 'cash_account_id' => $cashAccountId,
                    'payment_date' => $paymentDate, 'currency_code' => $currencyCode, 'amount' => $amount,
                    'reference' => filled($reference) ? trim($reference) : null, 'evidence_reference' => filled($evidenceReference) ? trim($evidenceReference) : null,
                    'notes' => filled($notes) ? trim($notes) : null, 'status' => 'approved', 'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash, 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(),
                ]);
                $payment->allocations()->createMany($allocations);
                if ($cashAccount !== null) {
                    app(RecordCashTransactionAction::class)->execute($actor, $cashAccount, bcsub('0', $amount, 4), 'supplier_payment', __('Supplier payment'), 'supplier-payment:'.$payment->id.':cash', $paymentDate, SupplierPayment::class, $payment->id, $reference);
                }
                app(RecordAuditEvent::class)->execute('procurement', 'supplier_payment_recorded', $payment, null, ['amount' => $amount, 'currency_code' => $currencyCode, 'allocation_count' => count($allocations)], metadata: ['supplier_id' => $supplier->id, 'idempotency_key' => $idempotencyKey]);

                return $payment->fresh(['supplier', 'paymentMethod', 'allocations.purchaseInvoice']);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = SupplierPayment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->replay($existing, $payloadHash);
            }
            throw $exception;
        }
    }

    private function replay(SupplierPayment $payment, string $payloadHash): SupplierPayment
    {
        if (! hash_equals((string) $payment->payload_hash, $payloadHash)) {
            throw new InvalidArgumentException(__('This supplier payment idempotency key was already used with different data.'));
        }

        return $payment->loadMissing(['supplier', 'paymentMethod', 'allocations.purchaseInvoice']);
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value) || bccomp($value, '0', 4) <= 0) {
            throw new InvalidArgumentException(__('Supplier payment amount must be a positive decimal.'));
        }

        return bcadd($value, '0', 4);
    }

    private function currency(string $value): string
    {
        $value = strtoupper(trim($value));
        if (! preg_match('/^[A-Z]{3}$/', $value)) {
            throw new InvalidArgumentException(__('Currency code must contain three letters.'));
        }

        return $value;
    }

    private function date(string $value): string
    {
        $parts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) ? $matches : [];
        if ($parts === [] || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new InvalidArgumentException(__('Supplier payment date is invalid.'));
        }

        return $value;
    }
}
