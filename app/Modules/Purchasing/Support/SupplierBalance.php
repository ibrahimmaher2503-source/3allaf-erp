<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\SupplierAccountAdjustment;
use App\Modules\Purchasing\Models\SupplierPaymentAllocation;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SupplierBalance
{
    /** @return numeric-string */
    public function for(Supplier $supplier, string $currencyCode = 'EGP'): string
    {
        $currencyCode = $this->currency($currencyCode);
        $invoices = PurchaseInvoice::query()->where('supplier_id', $supplier->id)->where('status', 'approved')->where($this->invoiceCurrency($currencyCode))->sum('total_amount');
        $returns = PurchaseReturn::query()->where('supplier_id', $supplier->id)->where('status', 'approved')->whereHas('purchaseInvoice', fn ($query) => $query->where($this->invoiceCurrency($currencyCode)))->sum('total_amount');
        $payments = SupplierPaymentAllocation::query()->whereHas('payment', fn ($query) => $query->where('supplier_id', $supplier->id)->where('currency_code', $currencyCode)->where('status', 'approved'))->sum('amount');
        $debits = SupplierAccountAdjustment::query()->where('supplier_id', $supplier->id)->where('currency_code', $currencyCode)->where('status', 'approved')->where('direction', 'debit')->sum('amount');
        $credits = SupplierAccountAdjustment::query()->where('supplier_id', $supplier->id)->where('currency_code', $currencyCode)->where('status', 'approved')->where('direction', 'credit')->sum('amount');

        return bcsub(bcadd(bcsub((string) $invoices, (string) $returns, 4), (string) $debits, 4), bcadd((string) $payments, (string) $credits, 4), 4);
    }

    /** @return numeric-string */
    public function paidForInvoice(PurchaseInvoice $invoice): string
    {
        return bcadd((string) $invoice->supplierPaymentAllocations()->whereHas('payment', fn ($query) => $query->where('status', 'approved'))->sum('amount'), '0', 4);
    }

    /** @return numeric-string */
    public function outstandingForInvoice(PurchaseInvoice $invoice): string
    {
        if ($invoice->status !== 'approved') return '0.0000';
        $returns = (string) $invoice->supplierReturns()->where('status', 'approved')->sum('total_amount');
        $debits = (string) $invoice->supplierAccountAdjustments()->where('status', 'approved')->where('direction', 'debit')->sum('amount');
        $credits = (string) $invoice->supplierAccountAdjustments()->where('status', 'approved')->where('direction', 'credit')->sum('amount');

        return bcsub(bcadd(bcsub((string) $invoice->total_amount, $returns, 4), $debits, 4), bcadd($this->paidForInvoice($invoice), $credits, 4), 4);
    }

    public function paymentStatusForInvoice(PurchaseInvoice $invoice): string
    {
        if (bccomp($this->outstandingForInvoice($invoice), '0', 4) <= 0) return 'paid';

        return bccomp($this->paidForInvoice($invoice), '0', 4) > 0 ? 'partially_paid' : 'unpaid';
    }

    /**
     * Oldest first by snapshotted due date, then document date and invoice id.
     * Invoices without a due date follow due-dated invoices.
     *
     * @return Collection<int, PurchaseInvoice>
     */
    public function outstandingInvoices(Supplier $supplier, User $actor, Company $company, string $currencyCode = 'EGP'): Collection
    {
        $currencyCode = $this->currency($currencyCode);
        $storeIds = Store::query()->visibleTo($actor)->where('company_id', $company->id)->select('id');

        return PurchaseInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('store_id', $storeIds)
            ->where('status', 'approved')
            ->where($this->invoiceCurrency($currencyCode))
            ->with('store')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->each(function (PurchaseInvoice $invoice): void {
                $paid = $this->paidForInvoice($invoice);
                $outstanding = $this->outstandingForInvoice($invoice);
                $invoice->setAttribute('current_paid', $paid);
                $invoice->setAttribute('current_outstanding', $outstanding);
                $invoice->setAttribute('current_payment_status', bccomp($outstanding, '0', 4) <= 0 ? 'paid' : (bccomp($paid, '0', 4) > 0 ? 'partially_paid' : 'unpaid'));
            })
            ->filter(fn (PurchaseInvoice $invoice): bool => bccomp((string) $invoice->current_outstanding, '0', 4) > 0)
            ->values();
    }

    private function currency(string $currencyCode): string
    {
        $currencyCode = strtoupper(trim($currencyCode));
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) throw new InvalidArgumentException(__('Currency code must contain three letters.'));

        return $currencyCode;
    }

    private function invoiceCurrency(string $currencyCode): \Closure
    {
        return static fn ($query) => $query->where('currency_code', $currencyCode)->when($currencyCode === 'EGP', fn ($currency) => $currency->orWhereNull('currency_code'));
    }
}
