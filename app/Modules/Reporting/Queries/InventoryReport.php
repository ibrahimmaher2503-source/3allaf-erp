<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Support\InventoryMovementLabel;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class InventoryReport
{
    public const EXPORT_KEYS = ['stock_movement', 'inventory_valuation'];

    private const TIMEZONE = 'Africa/Cairo';

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function movementCard(User $user, array $filters, bool $export = false): array
    {
        $scope = $this->scope($user, $filters, true);
        $from = $scope['from_utc'];
        $to = $scope['to_utc'];
        $productId = $scope['product']->id;
        $storeId = $scope['store']->id;

        $ledger = StockMovement::query()->where('product_id', $productId)->where('store_id', $storeId);
        $opening = $this->decimal((clone $ledger)->where('posted_at', '<', $from)->sum('quantity'), 6);
        $period = (clone $ledger)->whereBetween('posted_at', [$from, $to]);
        $incoming = $this->decimal((clone $period)->where('quantity', '>', 0)->sum('quantity'), 6);
        $outgoing = $this->decimal((clone $period)->where('quantity', '<', 0)->sum(DB::raw('ABS(quantity)')), 6);
        $closing = bcadd($opening, bcsub($incoming, $outgoing, 6), 6);

        $rowsQuery = $this->movementRows($productId, $storeId, $from, $to, $scope['movement_type'], $scope['reference']);
        if ($export) {
            $count = (clone $rowsQuery)->count();
            throw_if($count > 50000, ValidationException::withMessages(['date_from' => __('Narrow the report period before exporting more than 50,000 movements.') ]));
            $rows = $rowsQuery->orderBy('sm.posted_at')->orderBy('sm.id')->get();
            $start = $opening;
            $paginator = null;
        } else {
            $page = max(1, (int) ($filters['movement_page'] ?? 1));
            $perPage = 50;
            $count = (clone $rowsQuery)->count();
            $rows = (clone $rowsQuery)->orderBy('sm.posted_at')->orderBy('sm.id')->forPage($page, $perPage)->get();
            $prior = '0.000000';
            if ($first = $rows->first()) {
                $prior = $this->decimal((clone $period)
                    ->where(fn ($query) => $query->where('posted_at', '<', $first->posted_at)->orWhere(fn ($same) => $same->where('posted_at', $first->posted_at)->where('id', '<', $first->id)))
                    ->sum('quantity'), 6);
            }
            $start = bcadd($opening, $prior, 6);
            $paginator = new LengthAwarePaginator($rows, $count, $perPage, $page, ['path' => request()->url(), 'pageName' => 'movement_page']);
            $paginator->appends(request()->except('movement_page'));
        }

        $running = $start;
        $rows->each(function (object $row) use (&$running): void {
            $running = bcadd($running, (string) $row->base_quantity, 6);
            $row->running_balance = $running;
            $row->movement_label = InventoryMovementLabel::label((string) $row->movement_type);
            $row->source_label = $this->sourceLabel((string) $row->source_type);
            $row->source_url = $this->sourceUrl((string) $row->source_type, (int) $row->source_id);
        });

        return [
            'filters' => $scope['filters'], 'product' => $scope['product'], 'store' => $scope['store'],
            'base_unit' => $scope['base_unit'], 'opening' => $opening, 'incoming' => $incoming,
            'outgoing' => $outgoing, 'net' => bcsub($incoming, $outgoing, 6), 'closing' => $closing,
            'rows' => $paginator ?? $rows, 'row_count' => $count,
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function valuation(User $user, array $filters, bool $export = false): array
    {
        Gate::forUser($user)->authorize('inventory_stock_card.cost_view');
        $scope = $this->scope($user, $filters, false);
        $query = DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('product_units as bpu', fn ($join) => $join->on('bpu.product_id', '=', 'p.id')->where('bpu.is_base_unit', true))
            ->leftJoin('units as bu', 'bu.id', '=', 'bpu.unit_id')
            ->where('sm.store_id', $scope['store']->id)->where('sm.posted_at', '<=', $scope['to_utc'])
            ->when($scope['product_id'], fn ($q, int $id) => $q->where('p.id', $id))
            ->when($scope['category_id'], fn ($q, int $id) => $q->where('p.category_id', $id))
            ->groupBy('p.id', 'p.item_code', 'p.name_ar', 'p.name_en', 'c.name_ar', 'c.name_en', 'bu.code', 'p.unit_of_measure')
            ->selectRaw('p.id as product_id, p.item_code, p.name_ar, p.name_en, c.name_ar as category_ar, c.name_en as category_en, COALESCE(bu.code, p.unit_of_measure) as base_unit, SUM(sm.quantity) as quantity_as_of, SUM(sm.total_cost) as value_as_of, MAX(sm.posted_at) as last_movement_at');

        $summary = DB::query()->fromSub(clone $query, 'v')->selectRaw('COUNT(*) as total_products, SUM(CASE WHEN quantity_as_of > 0 THEN 1 ELSE 0 END) as positive_products, SUM(CASE WHEN quantity_as_of = 0 THEN 1 ELSE 0 END) as zero_products, SUM(CASE WHEN quantity_as_of < 0 THEN 1 ELSE 0 END) as negative_products, COALESCE(SUM(value_as_of), 0) as total_value')->first();
        if ($export) {
            $count = (int) $summary->total_products;
            throw_if($count > 20000, ValidationException::withMessages(['store_id' => __('Narrow the valuation filters before exporting more than 20,000 rows.') ]));
            $rows = $query->orderBy('p.item_code')->get();
            $paginator = null;
        } else {
            $rows = $query->orderBy('p.item_code')->paginate(50, ['*'], 'valuation_page')->withQueryString();
            $paginator = $rows;
        }
        $rows->each(function (object $row): void {
            $row->unit_cost_as_of = bccomp((string) $row->quantity_as_of, '0', 6) === 0 ? null : bcdiv((string) $row->value_as_of, (string) $row->quantity_as_of, 4);
        });

        $todayReconciliation = null;
        if ($scope['as_of_local']->isToday()) {
            $todayReconciliation = DB::table('stock_balances as sb')->where('sb.store_id', $scope['store']->id)
                ->selectRaw("COALESCE(SUM(ABS(sb.on_hand - COALESCE((SELECT SUM(sm2.quantity) FROM stock_movements sm2 WHERE sm2.product_id = sb.product_id AND sm2.store_id = sb.store_id AND sm2.posted_at <= ?), 0))), 0) as quantity_difference", [$scope['to_utc']])->value('quantity_difference');
        }

        return [
            'filters' => $scope['filters'], 'store' => $scope['store'], 'rows' => $paginator ?? $rows,
            'summary' => [
                'total_value' => $this->decimal($summary->total_value, 4),
                'positive_products' => (int) $summary->positive_products,
                'total_products' => (int) $summary->total_products,
                'zero_products' => (int) $summary->zero_products,
                'negative_products' => (int) $summary->negative_products,
            ],
            'today_reconciliation_difference' => $todayReconciliation === null ? null : $this->decimal($todayReconciliation, 6),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function export(User $user, string $dataset, array $filters): array
    {
        abort_unless(in_array($dataset, self::EXPORT_KEYS, true), 422);
        $report = $dataset === 'stock_movement' ? $this->movementCard($user, $filters, true) : $this->valuation($user, $filters, true);
        $rows = collect($report['rows']);
        $stockCard = $dataset === 'stock_movement';

        return [
            'title' => $stockCard ? __('Stock Movement Card') : __('Inventory Valuation As Of Date'),
            'filters' => $report['filters'], 'modules' => ['inventory'], 'fresh_at' => now()->toIso8601String(),
            'kpis' => $stockCard ? [
                'opening_balance' => $report['opening'], 'incoming' => $report['incoming'], 'outgoing' => $report['outgoing'], 'net_movement' => $report['net'], 'closing_balance' => $report['closing'],
            ] : $report['summary'],
            'sources' => $stockCard ? ['equation' => $report['opening'].' + '.$report['incoming'].' - '.$report['outgoing'].' = '.$report['closing']] : ['historical_value_source' => 'SUM(stock_movements.total_cost)'],
            'sales' => [], 'assets' => [], 'export' => ['dataset' => $dataset, 'row_count' => $rows->count()],
            'detail_sections' => [[
                'title' => $stockCard ? __('Movements') : __('Inventory valuation'),
                'columns' => $stockCard
                    ? ['posted_at' => __('Posted at'), 'movement' => __('Movement type'), 'document' => __('Document'), 'original_quantity' => __('Original quantity'), 'original_unit' => __('Original unit'), 'incoming' => __('Incoming'), 'outgoing' => __('Outgoing'), 'base_quantity' => __('Base quantity'), 'running_balance' => __('Balance')]
                    : ['product' => __('Product'), 'code' => __('Product Code'), 'category' => __('Category'), 'store' => __('Store'), 'base_unit' => __('Base Unit'), 'quantity' => __('Quantity As Of'), 'unit_cost' => __('Unit Cost As Of'), 'value' => __('Inventory Value As Of'), 'last_movement' => __('Last Movement Date')],
                'rows' => $rows->map(fn (object $row): array => $stockCard ? [
                    'posted_at' => (string) $row->posted_at, 'movement' => InventoryMovementLabel::label((string) $row->movement_type), 'document' => $row->document_number ?: '#'.$row->source_id,
                    'original_quantity' => $row->original_quantity, 'original_unit' => $row->original_unit, 'incoming' => bccomp((string) $row->base_quantity, '0', 6) > 0 ? $row->base_quantity : '0',
                    'outgoing' => bccomp((string) $row->base_quantity, '0', 6) < 0 ? ltrim((string) $row->base_quantity, '-') : '0', 'base_quantity' => $row->base_quantity, 'running_balance' => $row->running_balance,
                ] : [
                    'product' => $row->name_ar ?: $row->name_en, 'code' => $row->item_code, 'category' => $row->category_ar ?: $row->category_en,
                    'store' => $report['store']->code, 'base_unit' => $row->base_unit, 'quantity' => $row->quantity_as_of, 'unit_cost' => $row->unit_cost_as_of, 'value' => $row->value_as_of, 'last_movement' => $row->last_movement_at,
                ])->all(),
            ]],
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode([$snapshot['filters'], $snapshot['kpis'], $snapshot['sources'], $snapshot['detail_sections']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function scope(User $user, array $filters, bool $movementCard): array
    {
        Gate::forUser($user)->authorize('dashboard_reports.view');
        Gate::forUser($user)->authorize('inventory_stock_card.view');
        $company = app(AuthorizedCompanyContext::class)->resolve($user, $filters['company_id'] ?? null);
        $stores = Store::query()->visibleTo($user)->where('company_id', $company->id)->where('status', 'active')->orderBy('code');
        $storeId = $this->id($filters['store_id'] ?? null) ?? (int) (clone $stores)->value('id');
        $store = (clone $stores)->whereKey($storeId)->first();
        throw_unless($store, ValidationException::withMessages(['store_id' => __('The selected store is outside your access scope.') ]));
        $productId = $this->id($filters['product_id'] ?? null);
        throw_if($movementCard && $productId === null, ValidationException::withMessages(['product_id' => __('Select a product to open its stock movement card.') ]));
        $product = $productId ? Product::query()->with(['category', 'baseProductUnit.unit'])->find($productId) : null;
        throw_if($productId && ! $product, ValidationException::withMessages(['product_id' => __('The selected product is unavailable.') ]));

        $today = CarbonImmutable::now(self::TIMEZONE);
        $asOf = $this->date($filters[$movementCard ? 'date_to' : 'as_of_date'] ?? null, $today);
        $from = $movementCard ? $this->date($filters['date_from'] ?? null, $asOf->subDays(30)) : $asOf;
        throw_if($movementCard && $from->greaterThan($asOf), ValidationException::withMessages(['date_to' => __('The end date must be on or after the start date.') ]));
        throw_if($movementCard && $from->diffInDays($asOf) > 366, ValidationException::withMessages(['date_to' => __('The selected reporting period may not exceed 366 days.') ]));
        $fromUtc = $from->startOfDay()->utc();
        $toUtc = $asOf->endOfDay()->utc();
        $movementType = filled($filters['movement_type'] ?? null) ? trim((string) $filters['movement_type']) : null;
        $reference = filled($filters['reference'] ?? null) ? mb_substr(trim((string) $filters['reference']), 0, 100) : null;
        $categoryId = $this->id($filters['category_id'] ?? null);

        return [
            'company' => $company, 'store' => $store, 'product' => $product, 'product_id' => $productId, 'category_id' => $categoryId,
            'base_unit' => $product?->baseProductUnit?->unit?->code ?: $product?->unit_of_measure,
            'from_utc' => $fromUtc, 'to_utc' => $toUtc, 'as_of_local' => $asOf, 'movement_type' => $movementType, 'reference' => $reference,
            'filters' => array_filter([
                'company_id' => $company->id, 'branch_id' => $store->branch_id, 'store_id' => $store->id, 'product_id' => $productId,
                'date_from' => $movementCard ? $from->toDateString() : null, 'date_to' => $movementCard ? $asOf->toDateString() : null,
                'as_of_date' => $movementCard ? null : $asOf->toDateString(), 'movement_type' => $movementType,
                'reference' => $reference, 'category_id' => $categoryId,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function movementRows(int $productId, int $storeId, CarbonImmutable $from, CarbonImmutable $to, ?string $movementType, ?string $reference): \Illuminate\Database\Query\Builder
    {
        return DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')->join('stores as st', 'st.id', '=', 'sm.store_id')
            ->leftJoin('users as u', 'u.id', '=', 'sm.created_by')
            ->leftJoin('product_units as bpu', fn ($join) => $join->on('bpu.product_id', '=', 'p.id')->where('bpu.is_base_unit', true))->leftJoin('units as bu', 'bu.id', '=', 'bpu.unit_id')
            ->leftJoin('purchase_invoice_lines as pil', fn ($join) => $join->on('pil.id', '=', 'sm.source_line_id')->where('sm.source_type', PurchaseInvoice::class))
            ->leftJoin('product_units as ppu', 'ppu.id', '=', 'pil.product_unit_id')->leftJoin('units as pu', 'pu.id', '=', 'ppu.unit_id')
            ->leftJoin('sale_lines as sl', fn ($join) => $join->on('sl.id', '=', 'sm.source_line_id')->where('sm.source_type', Sale::class))
            ->leftJoin('product_units as spu', 'spu.id', '=', 'sl.product_unit_id')->leftJoin('units as su', 'su.id', '=', 'spu.unit_id')
            ->leftJoin('purchase_invoices as pi', fn ($join) => $join->on('pi.id', '=', 'sm.source_id')->where('sm.source_type', PurchaseInvoice::class))
            ->leftJoin('sales as s', fn ($join) => $join->on('s.id', '=', 'sm.source_id')->where('sm.source_type', Sale::class))
            ->leftJoin('retail_returns as rr', fn ($join) => $join->on('rr.id', '=', 'sm.source_id')->where('sm.source_type', RetailReturn::class))
            ->leftJoin('purchase_returns as pr', fn ($join) => $join->on('pr.id', '=', 'sm.source_id')->where('sm.source_type', PurchaseReturn::class))
            ->leftJoin('stock_transfers as tr', fn ($join) => $join->on('tr.id', '=', 'sm.source_id')->where('sm.source_type', StockTransfer::class))
            ->leftJoin('inventory_adjustments as ia', fn ($join) => $join->on('ia.id', '=', 'sm.source_id')->where('sm.source_type', InventoryAdjustment::class))
            ->leftJoin('opening_inventory_documents as oi', fn ($join) => $join->on('oi.id', '=', 'sm.source_id')->where('sm.source_type', OpeningInventoryDocument::class))
            ->where('sm.product_id', $productId)->where('sm.store_id', $storeId)->whereBetween('sm.posted_at', [$from, $to])
            ->when($movementType, fn ($q, string $type) => $q->where('sm.movement_type', $type))
            ->when($reference, fn ($q, string $value) => $q->where(fn ($ref) => $ref->where('pi.invoice_number', 'like', '%'.$value.'%')->orWhere('s.document_number', 'like', '%'.$value.'%')->orWhere('rr.return_number', 'like', '%'.$value.'%')->orWhere('pr.return_number', 'like', '%'.$value.'%')->orWhere('tr.transfer_number', 'like', '%'.$value.'%')->orWhere('ia.adjustment_number', 'like', '%'.$value.'%')->orWhere('oi.document_number', 'like', '%'.$value.'%')))
            ->selectRaw('sm.id, sm.store_id, sm.posted_at, sm.movement_type, sm.quantity as base_quantity, sm.unit_cost, sm.total_cost, sm.source_type, sm.source_id, sm.source_line_id, sm.reversal_of_id, u.name as actor_name, COALESCE(pil.entered_quantity, sl.entered_quantity, ABS(sm.quantity)) as original_quantity, COALESCE(pu.code, su.code, bu.code, p.unit_of_measure) as original_unit, COALESCE(bu.code, p.unit_of_measure) as base_unit, COALESCE(pi.invoice_number, s.document_number, rr.return_number, pr.return_number, tr.transfer_number, ia.adjustment_number, oi.document_number) as document_number, COALESCE(ia.reason_notes, tr.reason_notes, rr.reason, pr.notes, oi.notes) as reason');
    }

    private function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            PurchaseInvoice::class => __('Purchase invoice'), Sale::class => __('Sales invoice'), RetailReturn::class => __('Sales return'),
            PurchaseReturn::class => __('Supplier return'), StockTransfer::class => __('Stock transfer'), InventoryAdjustment::class => __('Inventory adjustment'),
            OpeningInventoryDocument::class => __('Opening inventory document'), default => __('Inventory source'),
        };
    }

    private function sourceUrl(string $sourceType, int $sourceId): ?string
    {
        if ($sourceId < 1) return null;
        return match ($sourceType) {
            Sale::class => route('sales.show', $sourceId), RetailReturn::class => route('returns.show', $sourceId), PurchaseReturn::class => route('purchasing.returns.show', $sourceId),
            PurchaseInvoice::class => route('purchasing.invoices', ['invoice_id' => $sourceId]), StockTransfer::class => route('inventory.transfers', ['transfer_id' => $sourceId]),
            InventoryAdjustment::class => route('inventory.adjustments', ['adjustment_id' => $sourceId]), OpeningInventoryDocument::class => route('inventory.opening.show', $sourceId), default => null,
        };
    }

    private function date(mixed $value, CarbonImmutable $default): CarbonImmutable
    {
        if (! filled($value)) return $default;
        try { return CarbonImmutable::createFromFormat('!Y-m-d', (string) $value, self::TIMEZONE); }
        catch (\Throwable) { throw ValidationException::withMessages(['date' => __('Report dates must be valid calendar dates.')]); }
    }

    private function id(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }

    private function decimal(mixed $value, int $scale): string
    {
        return bcadd((string) ($value ?? '0'), '0', $scale);
    }
}
