<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAccountAdjustment;
use App\Modules\Customer\Models\CustomerReceipt;
use App\Modules\Customer\Models\CustomerReceiptAllocation;
use App\Modules\Platform\Models\Company;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use Illuminate\Support\Collection;

final class CustomerBalance
{
    /** Read-only account totals for one paginated customer list, in authorized stores. */
    public function summaryForCustomers(array $customerIds, User $actor, string $currencyCode): Collection
    {
        abort_unless($actor->can('customers.view') && $actor->can('pos_sales.view') && $actor->can('pos_sales.payment_view'), 403);
        $customerIds = Customer::query()->visibleTo($actor)->whereIn('id', $customerIds)->pluck('id');
        $sales = Sale::query()->visibleTo($actor)->whereIn('customer_id', $customerIds)->where('currency_code', $currencyCode)->where('status', 'approved');
        $totals = (clone $sales)->selectRaw('customer_id, SUM(payable_total) AS total')->groupBy('customer_id')->pluck('total', 'customer_id');
        $saleIds = (clone $sales)->select('id');
        $payments = SalePayment::query()->whereIn('sale_id', clone $saleIds)->join('sales', 'sales.id', '=', 'sale_payments.sale_id')->selectRaw('sales.customer_id, SUM(sale_payments.amount) AS total')->groupBy('sales.customer_id')->pluck('total', 'customer_id');
        $credits = RetailReturn::query()->whereIn('source_sale_id', clone $saleIds)->where('retail_returns.status', 'completed')->join('sales', 'sales.id', '=', 'retail_returns.source_sale_id')->selectRaw('sales.customer_id, SUM(CASE WHEN ar_reduction_value = 0 AND actual_refund_value = 0 THEN settlement_value ELSE ar_reduction_value END) AS total')->groupBy('sales.customer_id')->pluck('total', 'customer_id');
        $receipts = CustomerReceipt::query()->whereIn('customer_id', $customerIds)->where('currency_code', $currencyCode)->where('status', 'approved')->whereIn('store_id', \App\Modules\Platform\Models\Store::query()->visibleTo($actor)->select('id'))->selectRaw('customer_id, SUM(amount) AS total')->groupBy('customer_id')->pluck('total', 'customer_id');
        $adjustments = CustomerAccountAdjustment::query()->whereIn('customer_id', $customerIds)->where('status', 'approved')->where(fn ($query) => $query->whereNull('sale_id')->orWhereIn('sale_id', clone $saleIds))->selectRaw('customer_id, SUM(amount) AS total')->groupBy('customer_id')->pluck('total', 'customer_id');

        return $customerIds->mapWithKeys(function ($id) use ($totals, $payments, $credits, $receipts, $adjustments): array {
            $purchased = (string) ($totals[$id] ?? '0.0000');
            $paid = bcadd((string) ($payments[$id] ?? 0), (string) ($receipts[$id] ?? 0), 4);
            $balance = bcadd(bcsub(bcsub($purchased, $paid, 4), (string) ($credits[$id] ?? 0), 4), (string) ($adjustments[$id] ?? 0), 4);
            return [$id => ['purchased' => $purchased, 'paid' => $paid, 'balance' => $balance]];
        });
    }

    /** @return numeric-string */
    public function for(Customer $customer, string $currencyCode = 'EGP'): string
    {
        $currencyCode = strtoupper(trim($currencyCode));
        $sales = Sale::query()->where('customer_id', $customer->id)->where('currency_code', $currencyCode)->where('status', 'approved')->get(['id', 'payable_total']);
        $saleIds = $sales->pluck('id');
        $payments = SalePayment::query()->whereIn('sale_id', $saleIds)->selectRaw('sale_id, SUM(amount) AS total')->groupBy('sale_id')->pluck('total', 'sale_id');
        $credits = RetailReturn::query()->whereIn('source_sale_id', $saleIds)->where('status', 'completed')->selectRaw('source_sale_id, SUM(CASE WHEN ar_reduction_value = 0 AND actual_refund_value = 0 THEN settlement_value ELSE ar_reduction_value END) AS total')->groupBy('source_sale_id')->pluck('total', 'source_sale_id');
        $adjustments = $this->sum(CustomerAccountAdjustment::query()->where('customer_id', $customer->id)->where('status', 'approved')->pluck('amount'));
        $receipts = $this->sum(CustomerReceipt::query()->where('customer_id', $customer->id)->where('currency_code', $currencyCode)->where('status', 'approved')->pluck('amount'));
        $balance = '0.0000';
        foreach ($sales as $sale) {
            $balance = bcadd($balance, bcsub(bcsub((string) $sale->payable_total, (string) ($payments[$sale->id] ?? 0), 4), (string) ($credits[$sale->id] ?? 0), 4), 4);
        }

        return bcadd(bcsub($balance, $receipts, 4), $adjustments, 4);
    }

    /** @return numeric-string */
    public function outstandingForSale(Sale $sale): string
    {
        $paid = $this->paidForSale($sale);
        $credits = $this->sum(RetailReturn::query()->where('source_sale_id', $sale->id)->where('status', 'completed')->selectRaw('CASE WHEN ar_reduction_value = 0 AND actual_refund_value = 0 THEN settlement_value ELSE ar_reduction_value END AS amount')->pluck('amount'));

        $outstanding = bcsub(bcsub((string) $sale->payable_total, $paid, 4), $credits, 4);

        return bccomp($outstanding, '0', 4) > 0 ? $outstanding : '0.0000';
    }

    /** @return numeric-string */
    public function paidForSale(Sale $sale): string
    {
        $payments = $this->sum(SalePayment::query()->where('sale_id', $sale->id)->pluck('amount'));
        $receipts = $this->sum(CustomerReceiptAllocation::query()->where('sale_id', $sale->id)->whereHas('receipt', fn ($query) => $query->where('status', 'approved'))->pluck('amount'));

        return bcadd($payments, $receipts, 4);
    }

    public function paymentStatusForSale(Sale $sale): string
    {
        if (bccomp($this->outstandingForSale($sale), '0', 4) <= 0) {
            return 'paid';
        }

        return bccomp($this->paidForSale($sale), '0', 4) > 0 ? 'partial' : 'unpaid';
    }

    /**
     * Oldest first by immutable approval timestamp, then sale id.
     *
     * @return Collection<int, Sale>
     */
    public function outstandingInvoices(Customer $customer, User $actor, Company $company, string $currencyCode = 'EGP'): Collection
    {
        $currencyCode = strtoupper(trim($currencyCode));

        return Sale::query()
            ->visibleTo($actor)
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->where('currency_code', $currencyCode)
            ->whereHas('store', fn ($query) => $query->where('company_id', $company->id))
            ->with('store')
            ->orderBy('approved_at')
            ->orderBy('id')
            ->get()
            ->each(function (Sale $sale): void {
                $paid = $this->paidForSale($sale);
                $outstanding = $this->outstandingForSale($sale);
                $sale->setAttribute('current_paid', $paid);
                $sale->setAttribute('current_outstanding', $outstanding);
                $sale->setAttribute('current_payment_status', bccomp($outstanding, '0', 4) <= 0 ? 'paid' : (bccomp($paid, '0', 4) > 0 ? 'partial' : 'unpaid'));
            })
            ->filter(fn (Sale $sale): bool => bccomp((string) $sale->current_outstanding, '0', 4) > 0)
            ->values();
    }

    /** @return numeric-string */
    public function unappliedCreditFor(Customer $customer, Company $company, string $currencyCode = 'EGP'): string
    {
        $receiptIds = CustomerReceipt::query()
            ->where('customer_id', $customer->id)
            ->where('currency_code', strtoupper(trim($currencyCode)))
            ->where('status', 'approved')
            ->whereHas('store', fn ($query) => $query->where('company_id', $company->id))
            ->pluck('id');
        $receipts = (string) CustomerReceipt::query()->whereIn('id', $receiptIds)->sum('amount');
        $allocated = (string) CustomerReceiptAllocation::query()->whereIn('customer_receipt_id', $receiptIds)->sum('amount');

        return bcsub($receipts, $allocated, 4);
    }

    /** @param iterable<mixed> $amounts @return numeric-string */
    private function sum(iterable $amounts): string
    {
        $total = '0.0000';
        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) $amount, 4);
        }

        return $total;
    }
}
