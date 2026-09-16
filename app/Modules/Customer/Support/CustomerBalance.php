<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAccountAdjustment;
use App\Modules\Customer\Models\CustomerReceipt;
use App\Modules\Customer\Models\CustomerReceiptAllocation;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;

final class CustomerBalance
{
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
        $payments = $this->sum(SalePayment::query()->where('sale_id', $sale->id)->pluck('amount'));
        $receipts = $this->sum(CustomerReceiptAllocation::query()->where('sale_id', $sale->id)->whereHas('receipt', fn ($query) => $query->where('status', 'approved'))->pluck('amount'));
        $credits = $this->sum(RetailReturn::query()->where('source_sale_id', $sale->id)->where('status', 'completed')->selectRaw('CASE WHEN ar_reduction_value = 0 AND actual_refund_value = 0 THEN settlement_value ELSE ar_reduction_value END AS amount')->pluck('amount'));

        $outstanding = bcsub(bcsub(bcsub((string) $sale->payable_total, $payments, 4), $receipts, 4), $credits, 4);

        return bccomp($outstanding, '0', 4) > 0 ? $outstanding : '0.0000';
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
