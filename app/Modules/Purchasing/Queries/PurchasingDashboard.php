<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Queries;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Database\Eloquent\Builder;

final class PurchasingDashboard
{
    /** @return array<string, mixed> */
    public function for(User $actor, ?int $storeId = null): array
    {
        $orders = $this->visibleOrders($actor, $storeId);
        $invoices = $this->visibleInvoices($actor, $storeId);
        $returns = $this->visibleReturns($actor, $storeId);
        $periodStart = today()->subDays(6);

        $approvedPeriodInvoices = (clone $invoices)
            ->where('purchase_invoices.status', 'approved')
            ->whereDate('purchase_invoices.invoice_date', '>=', $periodStart);

        $trendRows = (clone $approvedPeriodInvoices)
            ->selectRaw('DATE(purchase_invoices.invoice_date) as purchase_day, COUNT(*) as invoice_count, COALESCE(SUM(purchase_invoices.total_amount), 0) as total')
            ->groupByRaw('DATE(purchase_invoices.invoice_date)')
            ->pluck('total', 'purchase_day');

        return [
            'currency_code' => (clone $approvedPeriodInvoices)->value('purchase_invoices.currency_code') ?: 'EGP',
            'today_total' => (string) (clone $invoices)->where('purchase_invoices.status', 'approved')->whereDate('purchase_invoices.invoice_date', today())->sum('purchase_invoices.total_amount'),
            'today_count' => (clone $invoices)->where('purchase_invoices.status', 'approved')->whereDate('purchase_invoices.invoice_date', today())->count(),
            'period_total' => (string) (clone $approvedPeriodInvoices)->sum('purchase_invoices.total_amount'),
            'period_count' => (clone $approvedPeriodInvoices)->count(),
            'open_orders' => (clone $orders)->whereIn('purchase_orders.status', ['draft', 'submitted', 'approved', 'partially_received'])->count(),
            'receiving' => [
                'pending' => (clone $orders)->where('purchase_orders.status', 'approved')->count(),
                'partial' => (clone $orders)->where('purchase_orders.status', 'partially_received')->count(),
                'completed' => (clone $orders)->whereIn('purchase_orders.status', ['received', 'closed'])->count(),
            ],
            'returns_requiring_action' => (clone $returns)->whereIn('purchase_returns.status', ['draft', 'submitted'])->count(),
            'pending_approvals' => ApprovalRecord::query()->visibleTo($actor)
                ->whereIn('approval_records.source_type', ['purchase_orders', 'purchase_invoices', 'purchase_returns'])
                ->where('approval_records.approval_state', ApprovalState::Pending->value)
                ->count(),
            'trend' => collect(range(6, 0))->map(function (int $offset) use ($trendRows): array {
                $date = today()->subDays($offset);

                return ['date' => $date, 'total' => (float) ($trendRows[$date->toDateString()] ?? 0)];
            }),
            'top_suppliers' => (clone $approvedPeriodInvoices)
                ->join('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
                ->select(['suppliers.id', 'suppliers.code', 'suppliers.name_ar', 'suppliers.name_en'])
                ->selectRaw('COUNT(purchase_invoices.id) as invoice_count, COALESCE(SUM(purchase_invoices.total_amount), 0) as purchase_total')
                ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name_ar', 'suppliers.name_en')
                ->orderByDesc('purchase_total')->limit(5)->get(),
            'top_products' => PurchaseInvoiceLine::query()
                ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_lines.purchase_invoice_id')
                ->join('products', 'products.id', '=', 'purchase_invoice_lines.product_id')
                ->whereIn('purchase_invoices.store_id', Store::query()->visibleTo($actor)->select('id'))
                ->when($storeId !== null, fn (Builder $query) => $query->where('purchase_invoices.store_id', $storeId))
                ->where('purchase_invoices.status', 'approved')
                ->whereDate('purchase_invoices.invoice_date', '>=', $periodStart)
                ->select(['products.id', 'products.item_code', 'products.name_ar', 'products.name_en'])
                ->selectRaw('COALESCE(SUM(purchase_invoice_lines.quantity), 0) as units, COALESCE(SUM(purchase_invoice_lines.line_total), 0) as purchase_total')
                ->groupBy('products.id', 'products.item_code', 'products.name_ar', 'products.name_en')
                ->orderByDesc('purchase_total')->limit(5)->get(),
            'recent_invoices' => (clone $invoices)->with(['supplier:id,code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('purchase_invoices.invoice_date')->latest('purchase_invoices.id')->limit(5)->get(),
            'recent_orders' => (clone $orders)->with(['supplier:id,code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('purchase_orders.order_date')->latest('purchase_orders.id')->limit(5)->get(),
            'recent_returns' => (clone $returns)->with(['supplier:id,code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('purchase_returns.return_date')->latest('purchase_returns.id')->limit(5)->get(),
        ];
    }

    private function visibleOrders(User $actor, ?int $storeId): Builder
    {
        return PurchaseOrder::query()->where(function (Builder $query) use ($actor): void {
            $query->whereIn('purchase_orders.store_id', Store::query()->visibleTo($actor)->select('id'))
                ->orWhereIn('purchase_orders.branch_id', Branch::query()->visibleTo($actor)->select('id'));
        })->when($storeId !== null, fn (Builder $query) => $query->where('purchase_orders.store_id', $storeId));
    }

    private function visibleInvoices(User $actor, ?int $storeId): Builder
    {
        return PurchaseInvoice::query()->whereIn('purchase_invoices.store_id', Store::query()->visibleTo($actor)->select('id'))->when($storeId !== null, fn (Builder $query) => $query->where('purchase_invoices.store_id', $storeId));
    }

    private function visibleReturns(User $actor, ?int $storeId): Builder
    {
        return PurchaseReturn::query()->whereIn('purchase_returns.store_id', Store::query()->visibleTo($actor)->select('id'))->when($storeId !== null, fn (Builder $query) => $query->where('purchase_returns.store_id', $storeId));
    }
}
