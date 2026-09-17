<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\Platform\Models\Store;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SalesReport
{
    public const EXPORT_KEYS = ['sales_summary', 'sales_by_product'];

    public const EXPORT_ROW_LIMIT = 5000;

    private const TIMEZONE = 'Africa/Cairo';

    private const PAGE_SIZE = 25;

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function summary(User $user, array $filters = [], bool $forExport = false): array
    {
        $scope = $this->scope($user, $filters);
        $metrics = $this->metrics($scope);
        $previous = $this->metrics($this->previousScope($scope));
        $invoices = $this->invoices($scope, $forExport, 'invoice_page');

        return [
            'filters' => $scope['filters'],
            'metrics' => $metrics,
            'previous' => $previous,
            'comparison' => [
                'net_sales' => $this->percentageChange($metrics['net_sales'], $previous['net_sales']),
                'invoice_count' => $this->percentageChange((string) $metrics['invoice_count'], (string) $previous['invoice_count']),
                'average_invoice' => $this->percentageChange($metrics['average_invoice'], $previous['average_invoice']),
            ],
            'trend' => $this->trend($scope),
            'payments' => $this->paymentBreakdown($scope),
            'cashiers' => $this->salesByCashier($scope),
            'invoices' => $invoices,
            'fresh_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function byProduct(User $user, array $filters = [], bool $forExport = false): array
    {
        $scope = $this->scope($user, $filters);
        $rows = $this->productRows($scope);
        $selected = $this->selectedProductKey($scope['filters']);
        $detail = $selected === null ? null : $this->productDetail($scope, $selected[0], $selected[1], $forExport);

        return [
            'filters' => $scope['filters'],
            'metrics' => $this->metrics($scope),
            'products' => $forExport
                ? $rows->take(self::EXPORT_ROW_LIMIT)->values()
                : $this->paginate($rows, self::PAGE_SIZE, 'product_page'),
            'product_total' => $rows->count(),
            'selected_product' => $selected,
            'detail' => $detail,
            'fresh_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, int>  $customerIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function customer(User $user, array $customerIds, array $filters = []): array
    {
        Gate::forUser($user)->authorize('customers.view');
        Gate::forUser($user)->authorize('dashboard_reports.view');
        Gate::forUser($user)->authorize('pos_sales.view');
        Gate::forUser($user)->authorize('pos_sales.payment_view');

        $filters['customer_ids'] = array_values(array_unique(array_map('intval', $customerIds)));
        $filters['default_range'] = 'month';
        $scope = $this->scope($user, $filters);

        return [
            'filters' => $scope['filters'],
            'metrics' => $this->metrics($scope),
            'last_purchase_at' => (clone $scope['sales'])->max('s.approved_at'),
            'invoices' => $this->invoices($scope, false, 'sales_page'),
            'products' => $this->productRows($scope)->take(10)->values(),
            'fresh_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function export(User $user, string $key, array $filters): array
    {
        abort_unless(in_array($key, self::EXPORT_KEYS, true), 422);
        $report = $key === 'sales_summary'
            ? $this->summary($user, $filters, true)
            : $this->byProduct($user, $filters, true);

        $sections = $key === 'sales_summary'
            ? $this->summaryExportSections($report)
            : $this->productExportSections($report);

        return [
            'title' => $key === 'sales_summary' ? __('Sales Summary') : __('Sales by Product'),
            'filters' => $report['filters'],
            'modules' => ['sales'],
            'fresh_at' => $report['fresh_at'],
            'kpis' => $report['metrics'],
            'sources' => ['sales_label' => 'Approved sales', 'timezone' => self::TIMEZONE],
            'sales' => collect(),
            'assets' => collect(),
            'export' => [
                'row_limit' => self::EXPORT_ROW_LIMIT,
                'total_filtered_rows' => collect($sections)->sum(fn (array $section): int => count($section['rows'])),
                'exported_row_count' => collect($sections)->sum(fn (array $section): int => count($section['rows'])),
                'truncated' => false,
            ],
            'detail_sections' => $sections,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode([
            $snapshot['filters'], $snapshot['kpis'], $snapshot['detail_sections'], $snapshot['sources'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function scope(User $user, array $filters): array
    {
        Gate::forUser($user)->authorize('dashboard_reports.view');
        Gate::forUser($user)->authorize('pos_sales.view');
        Gate::forUser($user)->authorize('pos_sales.payment_view');

        $today = CarbonImmutable::now(self::TIMEZONE);
        $defaultFrom = ($filters['default_range'] ?? null) === 'month' ? $today->startOfMonth() : $today;
        $fromLocal = $this->localDate($filters['date_from'] ?? null, $defaultFrom)->startOfDay();
        $toLocal = $this->localDate($filters['date_to'] ?? null, $today)->endOfDay();
        throw_if($fromLocal->greaterThan($toLocal), ValidationException::withMessages(['date_to' => __('The end date must be on or after the start date.')]));
        throw_if($fromLocal->diffInDays($toLocal) > 366, ValidationException::withMessages(['date_to' => __('The selected reporting period may not exceed 366 days.')]));

        $storeIds = Store::query()->visibleTo($user)->where('status', 'active')->pluck('id');
        $storeId = $this->id($filters['store_id'] ?? null);
        if ($storeId !== null) {
            throw_unless($storeIds->contains($storeId), ValidationException::withMessages(['store_id' => __('The selected store is outside your access scope.')]));
            $storeIds = collect([$storeId]);
        }

        $normalized = [
            'date_from' => $fromLocal->toDateString(),
            'date_to' => $toLocal->toDateString(),
            'branch_id' => null,
            'store_id' => $storeId,
            'user_id' => $this->id($filters['user_id'] ?? null),
            'customer_id' => $this->id($filters['customer_id'] ?? null),
            'customer_ids' => array_values(array_filter((array) ($filters['customer_ids'] ?? []), fn ($id): bool => (int) $id > 0)),
            'customer_group_id' => $this->id($filters['customer_group_id'] ?? null),
            'payment_method_id' => $this->id($filters['payment_method_id'] ?? null),
            'product_id' => $this->id($filters['product_id'] ?? null),
            'category_id' => $this->id($filters['category_id'] ?? null),
            'product_unit_id' => $this->id($filters['product_unit_id'] ?? null),
            'detail_product_id' => $this->id($filters['detail_product_id'] ?? null),
            'detail_product_unit_id' => ($filters['detail_product_unit_id'] ?? null) === 'legacy' ? 'legacy' : $this->id($filters['detail_product_unit_id'] ?? null),
            'document_status' => 'approved',
        ];

        $sales = DB::table('sales as s')
            ->where('s.status', 'approved')
            ->whereBetween('s.approved_at', [$fromLocal->utc(), $toLocal->utc()])
            ->whereIn('s.store_id', $storeIds);
        $returns = DB::table('retail_returns as rr')
            ->join('sales as source_sale', 'source_sale.id', '=', 'rr.source_sale_id')
            ->where('rr.status', 'completed')
            ->whereBetween('rr.completed_at', [$fromLocal->utc(), $toLocal->utc()])
            ->whereIn('rr.store_id', $storeIds);

        $this->applyDimensions($sales, $returns, $normalized);

        return [
            'filters' => $normalized,
            'sales' => $sales,
            'returns' => $returns,
            'from_local' => $fromLocal,
            'to_local' => $toLocal,
            'user' => $user,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyDimensions(Builder $sales, Builder $returns, array $filters): void
    {
        if ($filters['user_id']) {
            $sales->where('s.cashier_id', $filters['user_id']);
            $returns->where('source_sale.cashier_id', $filters['user_id']);
        }
        $customerIds = $filters['customer_ids'] ?: array_filter([$filters['customer_id']]);
        if ($customerIds) {
            $sales->whereIn('s.customer_id', $customerIds);
            $returns->whereIn('source_sale.customer_id', $customerIds);
        }
        if ($filters['customer_group_id']) {
            $customers = DB::table('customers')->select('id')->where('customer_group_id', $filters['customer_group_id']);
            $sales->whereIn('s.customer_id', clone $customers);
            $returns->whereIn('source_sale.customer_id', clone $customers);
        }
        if ($filters['payment_method_id']) {
            $sales->whereExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('sale_payments as filter_payment')->whereColumn('filter_payment.sale_id', 's.id')->where('filter_payment.payment_method_id', $filters['payment_method_id']));
            $returns->whereExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('retail_return_settlements as filter_settlement')->whereColumn('filter_settlement.retail_return_id', 'rr.id')->where('filter_settlement.payment_method_id', $filters['payment_method_id']));
        }
        if ($filters['product_id'] || $filters['category_id'] || $filters['product_unit_id']) {
            $sales->whereExists(function (Builder $q) use ($filters): void {
                $q->selectRaw('1')->from('sale_lines as filter_line')->join('products as filter_product', 'filter_product.id', '=', 'filter_line.product_id')->whereColumn('filter_line.sale_id', 's.id');
                $q->when($filters['product_id'], fn (Builder $line, int $id): Builder => $line->where('filter_line.product_id', $id));
                $q->when($filters['category_id'], fn (Builder $line, int $id): Builder => $line->where('filter_product.category_id', $id));
                $q->when($filters['product_unit_id'], fn (Builder $line, int $id): Builder => $line->where('filter_line.product_unit_id', $id));
            });
            $returns->whereExists(function (Builder $q) use ($filters): void {
                $q->selectRaw('1')->from('retail_return_lines as filter_return_line')->join('sale_lines as filter_source_line', 'filter_source_line.id', '=', 'filter_return_line.sale_line_id')->join('products as filter_product', 'filter_product.id', '=', 'filter_source_line.product_id')->whereColumn('filter_return_line.retail_return_id', 'rr.id');
                $q->when($filters['product_id'], fn (Builder $line, int $id): Builder => $line->where('filter_source_line.product_id', $id));
                $q->when($filters['category_id'], fn (Builder $line, int $id): Builder => $line->where('filter_product.category_id', $id));
                $q->when($filters['product_unit_id'], fn (Builder $line, int $id): Builder => $line->where('filter_source_line.product_unit_id', $id));
            });
        }
    }

    /** @param array<string, mixed> $scope @return array<string, mixed> */
    private function metrics(array $scope): array
    {
        $sales = $scope['sales'];
        $returns = $scope['returns'];
        $checkoutPayments = DB::table('sale_payments')->whereIn('sale_id', (clone $sales)->select('s.id'));
        $grossBeforeDiscount = (string) (clone $sales)->sum('s.subtotal');
        $discounts = (string) (clone $sales)->sum('s.discount_total');
        $grossSales = bcsub($grossBeforeDiscount, $discounts, 2);
        $returnValue = (string) (clone $returns)->sum('rr.eligible_value');
        $netSales = bcsub($grossSales, $returnValue, 2);
        $collected = (string) (clone $checkoutPayments)->sum('amount');
        $invoiceCount = (clone $sales)->distinct()->count('s.id');

        $paymentTotals = DB::table('sale_payments')->select('sale_id')->selectRaw('SUM(amount) AS collected')->groupBy('sale_id');
        $credit = (string) (clone $sales)
            ->leftJoinSub($paymentTotals, 'checkout', 'checkout.sale_id', '=', 's.id')
            ->selectRaw('COALESCE(SUM(GREATEST(s.payable_total - COALESCE(checkout.collected, 0), 0)), 0) AS total')
            ->value('total');

        return [
            'gross_before_discount' => $this->money($grossBeforeDiscount),
            'gross_sales' => $this->money($grossSales),
            'discounts' => $this->money($discounts),
            'returns' => $this->money($returnValue),
            'net_sales' => $this->money($netSales),
            'collected_at_sale' => $this->money($collected),
            'credit_created' => $this->money($credit),
            'invoice_count' => $invoiceCount,
            'average_invoice' => $invoiceCount === 0 ? '0.00' : $this->money(bcdiv($netSales, (string) $invoiceCount, 4)),
        ];
    }

    /** @param array<string, mixed> $scope @return array<string, mixed> */
    private function previousScope(array $scope): array
    {
        $days = $scope['from_local']->startOfDay()->diffInDays($scope['to_local']->startOfDay()) + 1;
        $to = $scope['from_local']->subDay()->endOfDay();
        $from = $to->subDays($days - 1)->startOfDay();

        return $this->scope($scope['user'], [...$scope['filters'], 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    /** @param array<string, mixed> $scope @return Collection<int, array<string, mixed>> */
    private function trend(array $scope): Collection
    {
        $days = collect(CarbonPeriod::create($scope['from_local']->startOfDay(), $scope['to_local']->startOfDay()))
            ->mapWithKeys(fn ($day): array => [$day->format('Y-m-d') => ['date' => $day->format('Y-m-d'), 'sales' => '0.00', 'returns' => '0.00', 'net' => '0.00']]);

        foreach ((clone $scope['sales'])->get(['s.approved_at', 's.subtotal', 's.discount_total']) as $sale) {
            $date = CarbonImmutable::parse($sale->approved_at, 'UTC')->setTimezone(self::TIMEZONE)->toDateString();
            if ($days->has($date)) {
                $row = $days[$date];
                $row['sales'] = $this->money(bcadd($row['sales'], bcsub((string) $sale->subtotal, (string) $sale->discount_total, 2), 2));
                $days[$date] = $row;
            }
        }
        foreach ((clone $scope['returns'])->get(['rr.completed_at', 'rr.eligible_value']) as $return) {
            $date = CarbonImmutable::parse($return->completed_at, 'UTC')->setTimezone(self::TIMEZONE)->toDateString();
            if ($days->has($date)) {
                $row = $days[$date];
                $row['returns'] = $this->money(bcadd($row['returns'], (string) $return->eligible_value, 2));
                $days[$date] = $row;
            }
        }

        return $days->map(function (array $row): array {
            $row['net'] = $this->money(bcsub($row['sales'], $row['returns'], 2));

            return $row;
        })->values();
    }

    /** @param array<string, mixed> $scope @return Collection<int, object> */
    private function paymentBreakdown(array $scope): Collection
    {
        return DB::table('sale_payments as sp')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->whereIn('sp.sale_id', (clone $scope['sales'])->select('s.id'))
            ->selectRaw('COALESCE(pm.code, sp.method_code) AS code, COALESCE(pm.name_ar, sp.method_code) AS name_ar, COALESCE(pm.name_en, sp.method_code) AS name_en, SUM(sp.amount) AS amount')
            ->groupBy('pm.code', 'sp.method_code', 'pm.name_ar', 'pm.name_en')
            ->orderBy('code')
            ->get();
    }

    /** @param array<string, mixed> $scope @return Collection<int, array<string, mixed>> */
    private function salesByCashier(array $scope): Collection
    {
        $payments = DB::table('sale_payments')->select('sale_id')->selectRaw('SUM(amount) AS amount')->groupBy('sale_id');
        $sales = (clone $scope['sales'])
            ->join('users as cashier', 'cashier.id', '=', 's.cashier_id')
            ->leftJoinSub($payments, 'checkout', 'checkout.sale_id', '=', 's.id')
            ->select('s.cashier_id', 'cashier.name')
            ->selectRaw('COUNT(DISTINCT s.id) AS invoice_count, SUM(s.subtotal - s.discount_total) AS gross_sales, SUM(COALESCE(checkout.amount,0)) AS collected')
            ->groupBy('s.cashier_id', 'cashier.name')->get()->keyBy('cashier_id');
        $returns = (clone $scope['returns'])->select('source_sale.cashier_id')->selectRaw('SUM(rr.eligible_value) AS returns')->groupBy('source_sale.cashier_id')->pluck('returns', 'cashier_id');

        return $sales->map(fn (object $row): array => [
            'cashier_id' => (int) $row->cashier_id,
            'cashier' => (string) $row->name,
            'invoice_count' => (int) $row->invoice_count,
            'net_sales' => $this->money(bcsub((string) $row->gross_sales, (string) ($returns[$row->cashier_id] ?? 0), 2)),
            'collected' => $this->money((string) $row->collected),
        ])->sortByDesc('net_sales')->values();
    }

    /** @param array<string, mixed> $scope */
    private function invoices(array $scope, bool $forExport, string $pageName): Collection|LengthAwarePaginator
    {
        $payments = DB::table('sale_payments')->select('sale_id')->selectRaw('SUM(amount) AS amount')->groupBy('sale_id');
        $returns = DB::table('retail_returns')->where('status', 'completed')->select('source_sale_id')->selectRaw('SUM(eligible_value) AS amount')->groupBy('source_sale_id');
        $query = (clone $scope['sales'])
            ->leftJoin('customers as customer', 'customer.id', '=', 's.customer_id')
            ->leftJoin('customer_groups as customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
            ->join('stores as store', 'store.id', '=', 's.store_id')
            ->join('users as cashier', 'cashier.id', '=', 's.cashier_id')
            ->leftJoinSub($payments, 'checkout', 'checkout.sale_id', '=', 's.id')
            ->leftJoinSub($returns, 'sale_returns', 'sale_returns.source_sale_id', '=', 's.id')
            ->select(['s.id', 's.document_number', 's.approved_at', 's.status', 's.payment_status', 's.subtotal', 's.discount_total', 's.payable_total', 'customer.name_ar as customer_name_ar', 'customer.name_en as customer_name_en', 'customer_group.name_ar as group_name_ar', 'customer_group.name_en as group_name_en', 'store.name_ar as store_name_ar', 'store.name_en as store_name_en', 'cashier.name as cashier'])
            ->selectRaw('COALESCE(checkout.amount, 0) AS paid, GREATEST(s.payable_total - COALESCE(checkout.amount,0), 0) AS outstanding, COALESCE(sale_returns.amount, 0) AS returns')
            ->orderByDesc('s.approved_at')->orderByDesc('s.id');

        return $forExport
            ? $query->limit(self::EXPORT_ROW_LIMIT)->get()
            : $query->paginate(self::PAGE_SIZE, ['*'], $pageName)->withQueryString();
    }

    /** @param array<string, mixed> $scope @return Collection<int, array<string, mixed>> */
    private function productRows(array $scope): Collection
    {
        $sales = DB::table('sale_lines as sl')
            ->joinSub((clone $scope['sales'])->select('s.id'), 'qualified_sales', 'qualified_sales.id', '=', 'sl.sale_id')
            ->join('products as product', 'product.id', '=', 'sl.product_id')
            ->leftJoin('categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('product_units as product_unit', 'product_unit.id', '=', 'sl.product_unit_id')
            ->leftJoin('units as unit', 'unit.id', '=', 'product_unit.unit_id')
            ->select(['sl.product_id', 'sl.product_unit_id', 'product.item_code', 'product.name_ar', 'product.name_en', 'product.unit_of_measure', 'category.name_en as category_name_en', 'category.name_ar as category_name_ar', 'unit.code as unit_code', 'unit.name_ar as unit_name_ar', 'unit.name_en as unit_name_en'])
            ->selectRaw('SUM(COALESCE(sl.entered_quantity, sl.quantity)) AS sold_quantity, SUM(sl.gross_amount) AS gross_sales, SUM(sl.discount_amount + sl.allocated_invoice_discount) AS discount, COUNT(DISTINCT sl.sale_id) AS invoice_count')
            ->groupBy(['sl.product_id', 'sl.product_unit_id', 'product.item_code', 'product.name_ar', 'product.name_en', 'product.unit_of_measure', 'category.name_en', 'category.name_ar', 'unit.code', 'unit.name_ar', 'unit.name_en']);
        $this->applyLineDimensions($sales, $scope['filters']);
        $sales = $sales->get();

        $returns = DB::table('retail_return_lines as rrl')
            ->joinSub((clone $scope['returns'])->select('rr.id'), 'qualified_returns', 'qualified_returns.id', '=', 'rrl.retail_return_id')
            ->join('sale_lines as sl', 'sl.id', '=', 'rrl.sale_line_id')
            ->join('products as product', 'product.id', '=', 'sl.product_id')
            ->leftJoin('categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('product_units as product_unit', 'product_unit.id', '=', 'sl.product_unit_id')
            ->leftJoin('units as unit', 'unit.id', '=', 'product_unit.unit_id')
            ->select(['sl.product_id', 'sl.product_unit_id', 'product.item_code', 'product.name_ar', 'product.name_en', 'product.unit_of_measure', 'category.name_en as category_name_en', 'category.name_ar as category_name_ar', 'unit.code as unit_code', 'unit.name_ar as unit_name_ar', 'unit.name_en as unit_name_en'])
            ->selectRaw('SUM(rrl.quantity / COALESCE(NULLIF(sl.conversion_factor_snapshot, 0), 1)) AS returned_quantity, SUM(rrl.eligible_value) AS returns_value')
            ->groupBy(['sl.product_id', 'sl.product_unit_id', 'product.item_code', 'product.name_ar', 'product.name_en', 'product.unit_of_measure', 'category.name_en', 'category.name_ar', 'unit.code', 'unit.name_ar', 'unit.name_en']);
        $this->applyLineDimensions($returns, $scope['filters']);
        $returns = $returns->get();

        return $sales->concat($returns)->groupBy(fn (object $row): string => $row->product_id.':'.($row->product_unit_id ?? 0))->map(function (Collection $parts): array {
            $first = $parts->first();
            $sold = $parts->reduce(fn (string $total, object $row): string => bcadd($total, (string) ($row->sold_quantity ?? 0), 6), '0');
            $returned = $parts->reduce(fn (string $total, object $row): string => bcadd($total, (string) ($row->returned_quantity ?? 0), 6), '0');
            $gross = $parts->reduce(fn (string $total, object $row): string => bcadd($total, (string) ($row->gross_sales ?? 0), 2), '0');
            $discount = $parts->reduce(fn (string $total, object $row): string => bcadd($total, (string) ($row->discount ?? 0), 2), '0');
            $returnsValue = $parts->reduce(fn (string $total, object $row): string => bcadd($total, (string) ($row->returns_value ?? 0), 2), '0');
            $netQuantity = bcsub($sold, $returned, 6);
            $netSales = bcsub(bcsub($gross, $discount, 2), $returnsValue, 2);

            return [
                'product_id' => (int) $first->product_id,
                'product_unit_id' => $first->product_unit_id ? (int) $first->product_unit_id : null,
                'item_code' => (string) $first->item_code,
                'name_ar' => (string) $first->name_ar,
                'name_en' => (string) $first->name_en,
                'category_name_ar' => (string) ($first->category_name_ar ?? ''),
                'category_name_en' => (string) ($first->category_name_en ?? ''),
                'unit_code' => (string) ($first->unit_code ?? $first->unit_of_measure),
                'unit_name_ar' => (string) ($first->unit_name_ar ?? $first->unit_of_measure),
                'unit_name_en' => (string) ($first->unit_name_en ?? $first->unit_of_measure),
                'sold_quantity' => $this->quantity($sold),
                'returned_quantity' => $this->quantity($returned),
                'net_quantity' => $this->quantity($netQuantity),
                'gross_sales' => $this->money($gross),
                'discount' => $this->money($discount),
                'returns_value' => $this->money($returnsValue),
                'net_sales' => $this->money($netSales),
                'average_price' => bccomp($netQuantity, '0', 6) === 0 ? '0.0000' : bcround(bcdiv($netSales, $netQuantity, 6), 4),
                'invoice_count' => (int) $parts->sum(fn (object $row): int => (int) ($row->invoice_count ?? 0)),
            ];
        })->sort(fn (array $a, array $b): int => bccomp($b['net_sales'], $a['net_sales'], 2))->values();
    }

    /** @param array<string, mixed> $scope */
    private function productDetail(array $scope, int $productId, ?int $productUnitId, bool $forExport): Collection|LengthAwarePaginator
    {
        $qualifiedReturns = DB::table('retail_return_lines as rrl')
            ->joinSub((clone $scope['returns'])->select('rr.id'), 'qualified_returns', 'qualified_returns.id', '=', 'rrl.retail_return_id')
            ->select('rrl.sale_line_id')->selectRaw('SUM(rrl.quantity) AS returned_base_quantity, SUM(rrl.eligible_value) AS returned_value')->groupBy('rrl.sale_line_id');
        $query = DB::table('sale_lines as sl')
            ->joinSub((clone $scope['sales'])->select('s.id'), 'qualified_sales', 'qualified_sales.id', '=', 'sl.sale_id')
            ->join('sales as sale', 'sale.id', '=', 'sl.sale_id')
            ->leftJoin('customers as customer', 'customer.id', '=', 'sale.customer_id')
            ->leftJoin('customer_groups as customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
            ->leftJoin('product_units as product_unit', 'product_unit.id', '=', 'sl.product_unit_id')
            ->leftJoin('units as unit', 'unit.id', '=', 'product_unit.unit_id')
            ->leftJoinSub($qualifiedReturns, 'returned', 'returned.sale_line_id', '=', 'sl.id')
            ->where('sl.product_id', $productId)
            ->when($productUnitId, fn (Builder $q, int $id): Builder => $q->where('sl.product_unit_id', $id), fn (Builder $q): Builder => $q->whereNull('sl.product_unit_id'))
            ->select(['sl.id', 'sale.id as sale_id', 'sale.document_number', 'sale.approved_at', 'customer.name_ar as customer_name_ar', 'customer.name_en as customer_name_en', 'customer_group.name_ar as group_name_ar', 'customer_group.name_en as group_name_en', 'unit.code as unit_code', 'unit.name_ar as unit_name_ar', 'unit.name_en as unit_name_en', 'sl.entered_quantity', 'sl.entered_unit_price', 'sl.quantity', 'sl.unit_price', 'sl.discount_amount', 'sl.allocated_invoice_discount', 'sl.net_amount', 'sl.conversion_factor_snapshot'])
            ->selectRaw('COALESCE(returned.returned_base_quantity / COALESCE(NULLIF(sl.conversion_factor_snapshot,0),1),0) AS returned_quantity, COALESCE(returned.returned_value,0) AS returned_value')
            ->orderByDesc('sale.approved_at')->orderByDesc('sl.id');

        return $forExport ? $query->limit(self::EXPORT_ROW_LIMIT)->get() : $query->paginate(self::PAGE_SIZE, ['*'], 'detail_page')->withQueryString();
    }

    /** @param array<string, mixed> $filters @return array{0:int,1:?int}|null */
    private function selectedProductKey(array $filters): ?array
    {
        $productId = $this->id($filters['detail_product_id'] ?? null);
        if ($productId === null) {
            return null;
        }
        $unit = $filters['detail_product_unit_id'] ?? null;

        return [$productId, $unit === 'legacy' ? null : $this->id($unit)];
    }

    /** @param array<string, mixed> $filters */
    private function applyLineDimensions(Builder $query, array $filters): void
    {
        $query->when($filters['product_id'], fn (Builder $q, int $id): Builder => $q->where('sl.product_id', $id));
        $query->when($filters['category_id'], fn (Builder $q, int $id): Builder => $q->where('product.category_id', $id));
        $query->when($filters['product_unit_id'], fn (Builder $q, int $id): Builder => $q->where('sl.product_unit_id', $id));
    }

    /** @param array<string, mixed> $report @return array<int, array<string, mixed>> */
    private function summaryExportSections(array $report): array
    {
        $invoices = collect($report['invoices']);

        return [[
            'title' => __('Sales Summary'),
            'columns' => ['invoice' => __('Invoice'), 'date' => __('Date'), 'customer' => __('Customer'), 'group' => __('Customer Group'), 'cashier' => __('Cashier'), 'gross' => __('Gross'), 'discount' => __('Discount'), 'net' => __('Net'), 'paid' => __('Paid'), 'outstanding' => __('Outstanding'), 'status' => __('Status')],
            'rows' => $invoices->map(fn (object $row): array => ['invoice' => $row->document_number, 'date' => $row->approved_at, 'customer' => $row->customer_name_en ?: $row->customer_name_ar, 'group' => $row->group_name_en ?: $row->group_name_ar, 'cashier' => $row->cashier, 'gross' => $row->subtotal, 'discount' => $row->discount_total, 'net' => bcsub((string) $row->subtotal, (string) $row->discount_total, 2), 'paid' => $row->paid, 'outstanding' => $row->outstanding, 'status' => $row->status])->all(),
        ]];
    }

    /** @param array<string, mixed> $report @return array<int, array<string, mixed>> */
    private function productExportSections(array $report): array
    {
        return [[
            'title' => __('Sales by Product'),
            'columns' => ['product' => __('Product'), 'sku' => __('SKU'), 'category' => __('Category'), 'unit' => __('Selling Unit'), 'sold' => __('Gross Quantity Sold'), 'returned' => __('Returned Quantity'), 'net_quantity' => __('Net Quantity'), 'gross' => __('Gross Sales'), 'discount' => __('Discount'), 'returns' => __('Returns Value'), 'net' => __('Net Sales'), 'average' => __('Average Selling Price'), 'invoices' => __('Invoice Count')],
            'rows' => collect($report['products'])->map(fn (array $row): array => ['product' => $row['name_en'] ?: $row['name_ar'], 'sku' => $row['item_code'], 'category' => $row['category_name_en'] ?: $row['category_name_ar'], 'unit' => $row['unit_code'], 'sold' => $row['sold_quantity'], 'returned' => $row['returned_quantity'], 'net_quantity' => $row['net_quantity'], 'gross' => $row['gross_sales'], 'discount' => $row['discount'], 'returns' => $row['returns_value'], 'net' => $row['net_sales'], 'average' => $row['average_price'], 'invoices' => $row['invoice_count']])->all(),
        ]];
    }

    private function localDate(mixed $value, CarbonImmutable $default): CarbonImmutable
    {
        if (! filled($value)) {
            return $default;
        }
        $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $value, self::TIMEZONE);
        throw_unless($date !== false && $date->format('Y-m-d') === (string) $value, ValidationException::withMessages(['date_from' => __('The selected date is invalid.')]));

        return $date;
    }

    private function id(mixed $value): ?int
    {
        if (! filled($value)) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        throw_if($id === false, ValidationException::withMessages(['filter' => __('The selected filter is invalid.')]));

        return $id;
    }

    private function money(string $value): string
    {
        return bcround($value, 2);
    }

    private function quantity(string $value): string
    {
        return rtrim(rtrim(bcround($value, 6), '0'), '.') ?: '0';
    }

    /** @return array{percent:?float,direction:string} */
    private function percentageChange(string $current, string $previous): array
    {
        if (bccomp($previous, '0', 4) === 0) {
            return ['percent' => bccomp($current, '0', 4) === 0 ? 0.0 : null, 'direction' => bccomp($current, '0', 4) > 0 ? 'up' : 'flat'];
        }
        $percent = round(((float) $current - (float) $previous) / abs((float) $previous) * 100, 1);

        return ['percent' => $percent, 'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat')];
    }

    /** @param Collection<int, mixed> $rows */
    private function paginate(Collection $rows, int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query($pageName, 1));

        return new Paginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => request()->url(), 'query' => request()->query(), 'pageName' => $pageName]);
    }
}
