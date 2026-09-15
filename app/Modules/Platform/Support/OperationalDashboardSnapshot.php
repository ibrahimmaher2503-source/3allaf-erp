<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use Illuminate\Database\Eloquent\Builder;

final class OperationalDashboardSnapshot
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $storeIds = Store::query()->visibleTo($user)->select('id');
        $today = now()->toDateString();
        $canDecideApprovals = collect([
            'pricing_labels.approve', 'purchase_orders.approve', 'purchase_invoices_supplier_returns.approve',
            'purchase_returns.approve', 'inventory_stock_card.approve', 'stock_counts.reconcile',
        ])->contains(fn (string $permission): bool => $user->can($permission));
        $sales = Sale::query()->visibleTo($user)->approved();
        $purchases = PurchaseInvoice::query()->whereIn('store_id', clone $storeIds)->where('status', 'approved');
        $trendStart = now()->startOfDay()->subDays(6);
        $salesTrend = $user->can('pos_sales.view') ? (clone $sales)->where('approved_at', '>=', $trendStart)->selectRaw('DATE(approved_at) as day, SUM(payable_total) as total')->groupBy('day')->pluck('total', 'day') : collect();
        $purchaseTrend = $user->can('purchase_invoices_supplier_returns.view') ? (clone $purchases)->where('invoice_date', '>=', $trendStart->toDateString())->selectRaw('invoice_date as day, SUM(total_amount) as total')->groupBy('invoice_date')->pluck('total', 'day') : collect();
        $stockBalances = StockBalance::query()->whereIn('store_id', clone $storeIds);

        return [
            'sales' => $user->can('pos_sales.view') ? [
                'total' => (string) (clone $sales)->whereDate('approved_at', $today)->sum('payable_total'),
                'count' => (clone $sales)->whereDate('approved_at', $today)->count(),
                'recent' => (clone $sales)->with(['store:id,code,name_ar,name_en', 'cashier:id,name'])->latest('approved_at')->latest('id')->limit(5)->get(['id', 'store_id', 'cashier_id', 'document_number', 'payable_total', 'approved_at']),
            ] : null,
            'purchases' => $user->can('purchase_invoices_supplier_returns.view') ? [
                'total' => (string) (clone $purchases)->whereDate('invoice_date', $today)->sum('total_amount'),
                'count' => (clone $purchases)->whereDate('invoice_date', $today)->count(),
                'recent' => (clone $purchases)->with(['store:id,code,name_ar,name_en', 'supplier:id,name_ar,name_en'])->latest('invoice_date')->latest('id')->limit(5)->get(['id', 'store_id', 'supplier_id', 'invoice_number', 'total_amount', 'status', 'invoice_date']),
            ] : null,
            'low_stock' => $user->can('inventory_stock_card.view') ? (clone $stockBalances)
                ->whereRaw('(on_hand - reserved) > 0')->whereRaw('(on_hand - reserved) <= ?', [5])
                ->with(['product:id,item_code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->orderByRaw('(on_hand - reserved) asc')->limit(6)->get() : collect(),
            'low_stock_count' => $user->can('inventory_stock_card.view') ? (clone $stockBalances)->whereRaw('(on_hand - reserved) > 0')->whereRaw('(on_hand - reserved) <= ?', [5])->count() : null,
            'out_of_stock_count' => $user->can('inventory_stock_card.view') ? (clone $stockBalances)->whereRaw('(on_hand - reserved) <= 0')->count() : null,
            'recent_movements' => $user->can('inventory_stock_card.view') ? StockMovement::query()
                ->whereIn('store_id', clone $storeIds)->with(['product:id,item_code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('posted_at')->latest('id')->limit(5)->get() : collect(),
            'trend' => collect(range(6, 0))->map(fn (int $days): array => ['day' => now()->subDays($days)->toDateString(), 'sales' => (string) ($salesTrend[now()->subDays($days)->toDateString()] ?? '0'), 'purchases' => (string) ($purchaseTrend[now()->subDays($days)->toDateString()] ?? '0')]),
            'top_products' => $user->can('pos_sales.view') ? SaleLine::query()->whereIn('sale_id', (clone $sales)->select('id'))->selectRaw('product_id, SUM(quantity) as units')->with('product:id,item_code,name_ar,name_en')->groupBy('product_id')->orderByDesc('units')->limit(5)->get() : collect(),
            'open_shifts' => $user->can('shifts_cash_movements.view') ? PosShift::query()->visibleTo($user)->open()->count() : null,
            'pending_orders' => $user->can('purchase_orders.view') ? PurchaseOrder::query()->whereIn('store_id', clone $storeIds)->whereIn('status', ['submitted', 'approved', 'partially_received'])->count() : null,
            'pending_returns' => $user->can('purchase_returns.view') ? PurchaseReturn::query()->whereIn('store_id', clone $storeIds)->where('status', 'submitted')->count() : null,
            'pending_approvals' => $canDecideApprovals ? ApprovalRecord::query()->visibleTo($user)->where('approval_state', ApprovalState::Pending)->count() : null,
        ];
    }
}
