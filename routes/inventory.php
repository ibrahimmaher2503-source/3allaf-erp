<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\CompleteStockCountLineAction;
use App\Modules\Inventory\Actions\CancelStockCountSessionAction;
use App\Modules\Inventory\Actions\AssertInventoryStoreScope;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\OpenStockCountSessionAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\RecordStockCountContributionAction;
use App\Modules\Inventory\Actions\RequestStockCountRecountAction;
use App\Modules\Inventory\Actions\RecordStockCountLineAction;
use App\Modules\Inventory\Actions\RequestStockTransferApprovalAction;
use App\Modules\Inventory\Actions\ResolveTransferDifferenceAction;
use App\Modules\Inventory\Actions\ReverseInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveStockCountAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Actions\UpdateStockTransferDraftAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

$router = app('router');
$renderInventory = static function (?int $productId = null, ?string $focus = null, ?StockTransfer $transfer = null, ?InventoryAdjustment $adjustment = null, ?StockCount $count = null) {
    Gate::authorize('inventory_stock_card.view');
    $user = Auth::user();
    abort_unless($user instanceof User, 403);
    $visibleStores = Store::query()->with('branch')->visibleTo($user)->orderBy('branch_id')->orderBy('name_ar')->orderBy('code')->limit(200)->get();
    $visibleStoreIds = $visibleStores->modelKeys();
    $search = trim((string) request()->query('q', ''));
    $filterStoreId = request()->integer('store_id') ?: app(\App\Modules\Platform\Support\WorkContext::class)->id($user);
    if ($filterStoreId !== null && ! in_array($filterStoreId, $visibleStoreIds, true)) {
        abort(403);
    }
    $movementType = trim((string) request()->query('movement_type', ''));
    abort_unless($movementType === '' || array_key_exists($movementType, \App\Modules\Inventory\Support\InventoryMovementLabel::options()), 422);
    $stockState = trim((string) request()->query('stock_state', ''));
    $includeZero = request()->boolean('include_zero');
    abort_unless(in_array($stockState, ['', 'available', 'low', 'out', 'negative'], true), 422);
    $canViewCost = Gate::allows('inventory_stock_card.cost_view');
    $showBalances = in_array($focus, [null, 'balances', 'stock-card'], true);
    $showMovements = $focus !== 'balances';
    $showTransfers = $focus === null || str_starts_with((string) $focus, 'transfer');
    $showCountWorkspace = $focus === null || str_starts_with((string) $focus, 'count') || str_starts_with((string) $focus, 'adjustment');
    $balancesQuery = StockBalance::query()->with(['product.barcodes', 'store.branch'])->whereIn('store_id', $visibleStoreIds)->orderBy('store_id')->orderBy('product_id');
    $movementsQuery = StockMovement::query()->with(['product', 'store.branch', 'creator'])->whereIn('store_id', $visibleStoreIds)->latest('posted_at');
    if(!$includeZero)$balancesQuery->where(fn($q)=>$q->where('on_hand','!=',0)->orWhere('reserved','!=',0)->orWhere('in_transit','!=',0));
    if ($filterStoreId !== null) {
        $balancesQuery->where('store_id', $filterStoreId);
        $movementsQuery->where('store_id', $filterStoreId);
    }
    if ($search !== '') {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $balancesQuery->whereHas('product', static function ($query) use ($like): void {
            $query->where('item_code', 'like', $like)->orWhere('model_number', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)
                ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('barcode', 'like', $like));
        });
        $movementsQuery->whereHas('product', static function ($query) use ($like): void {
            $query->where('item_code', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)
                ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('barcode', 'like', $like));
        });
    }
    if ($productId !== null) {
        $balancesQuery->where('product_id', $productId);
        $movementsQuery->where('product_id', $productId);
    }
    if ($movementType !== '') {
        $movementsQuery->where('movement_type', $movementType);
    }
    match ($stockState) {
        'available' => $balancesQuery->whereRaw('(on_hand - reserved) > 0'),
        'low' => $balancesQuery->whereRaw('(on_hand - reserved) > 0')->whereRaw('(on_hand - reserved) <= COALESCE((SELECT reorder_threshold FROM products WHERE products.id = stock_balances.product_id), 0)'),
        'out' => $balancesQuery->whereRaw('(on_hand - reserved) = 0'),
        'negative' => $balancesQuery->whereRaw('(on_hand - reserved) < 0'),
        default => null,
    };
    $scopedBalances = StockBalance::query()->whereIn('store_id', $visibleStoreIds);
    $scopedMovements = StockMovement::query()->whereIn('store_id', $visibleStoreIds);
    $inventoryOverview = $focus === null ? [
        'active_products' => Product::query()->active()->whereNull('parent_product_id')->count(),
        'active_variants' => Product::query()->active()->whereNotNull('parent_product_id')->count(),
        'visible_products' => (clone $scopedBalances)->distinct()->count('product_id'),
        'stock_on_hand' => (string) (clone $scopedBalances)->sum('on_hand'),
        'inventory_value' => $canViewCost ? (string) (clone $scopedBalances)->sum('total_value') : null,
        'low_stock' => (clone $scopedBalances)->whereRaw('(on_hand - reserved) > 0')->whereRaw('(on_hand - reserved) <= COALESCE((SELECT reorder_threshold FROM products WHERE products.id = stock_balances.product_id), 0)')->count(),
        'out_of_stock' => (clone $scopedBalances)->whereRaw('(on_hand - reserved) = 0')->count(),
        'negative_stock' => (clone $scopedBalances)->whereRaw('(on_hand - reserved) < 0')->count(),
        'today_movements' => (clone $scopedMovements)->whereBetween('posted_at', [today()->startOfDay(), today()->endOfDay()])->count(),
        'missing_barcodes' => Product::query()->sellable()->whereDoesntHave('barcodes', fn ($barcode) => $barcode->where('status', 'active'))->count(),
        'missing_catalog_data' => Product::query()->active()->where(function ($product): void {
            $product->whereNull('category_id')->orWhereNull('unit_of_measure')->orWhere('unit_of_measure', '');
        })->count(),
        'pending_transfers' => StockTransfer::query()->where(function ($query) use ($visibleStoreIds): void {
            $query->whereIn('source_store_id', $visibleStoreIds)->orWhereIn('destination_store_id', $visibleStoreIds);
        })->whereIn('status', ['draft', 'submitted', 'approved', 'in_transit', 'difference'])->count(),
        'active_counts' => StockCount::query()->whereIn('store_id', $visibleStoreIds)->whereIn('status', ['draft', 'in_progress', 'submitted'])->count(),
        'pending_adjustments' => InventoryAdjustment::query()->whereIn('store_id', $visibleStoreIds)->whereIn('status', ['draft', 'submitted'])->count(),
        'stock_distribution' => StockBalance::query()->join('stores', 'stores.id', '=', 'stock_balances.store_id')->whereIn('stock_balances.store_id', $visibleStoreIds)->select(['stores.id', 'stores.code', 'stores.name_ar', 'stores.name_en'])->selectRaw('SUM(stock_balances.on_hand) as on_hand')->groupBy('stores.id', 'stores.code', 'stores.name_ar', 'stores.name_en')->orderByDesc('on_hand')->limit(6)->get(),
        'top_moving_products' => StockMovement::query()->join('products', 'products.id', '=', 'stock_movements.product_id')->whereIn('stock_movements.store_id', $visibleStoreIds)->where('posted_at', '>=', now()->subDays(7))->select(['products.id', 'products.item_code', 'products.name_ar', 'products.name_en'])->selectRaw('SUM(ABS(stock_movements.quantity)) as moved_quantity')->groupBy('products.id', 'products.item_code', 'products.name_ar', 'products.name_en')->orderByDesc('moved_quantity')->limit(6)->get(),
    ] : [];
    $transferQuery = StockTransfer::query()->where(function ($query) use ($visibleStoreIds): void {
        $query->whereIn('source_store_id', $visibleStoreIds)->orWhereIn('destination_store_id', $visibleStoreIds);
    });
    $adjustmentQuery = InventoryAdjustment::query()->whereIn('store_id', $visibleStoreIds);
    $countQuery = StockCount::query()->where(function ($query) use ($visibleStoreIds): void {
        $query->whereIn('store_id', $visibleStoreIds)->orWhereHas('locations', fn ($locations) => $locations->whereIn('store_id', $visibleStoreIds));
    });
    $inventorySummary = [
        'transfers' => (clone $transferQuery)->count(),
        'adjustments' => (clone $adjustmentQuery)->count(),
        'counts' => (clone $countQuery)->count(),
    ];
    $balanceTotal = $showBalances ? null : (clone $balancesQuery)->count();
    $movementTotal = $showMovements ? null : (clone $movementsQuery)->count();
    $balances = $showBalances ? $balancesQuery->paginate(25, ['*'], 'balance_page')->withQueryString() : new LengthAwarePaginator([], $balanceTotal, 25);
    $movements = $showMovements ? $movementsQuery->paginate(50, ['*'], 'movement_page')->withQueryString() : new LengthAwarePaginator([], $movementTotal, 50);
    $inventorySummary['balances'] = $balances->total();
    $inventorySummary['movements'] = $movements->total();
    $transfers = $showTransfers ? (clone $transferQuery)->with(['sourceStore', 'destinationStore', 'lines.product'])->latest('id')->limit(20)->get() : collect();
    $transferApprovals = $showTransfers ? ApprovalRecord::query()
        ->where('source_type', 'stock_transfers')
        ->whereIn('source_id', $transfers->pluck('id')->map(fn (int $id): string => (string) $id))
        ->latest('requested_at')
        ->limit(50)->get()
        ->unique('source_id')
        ->keyBy('source_id') : collect();
    $adjustments = $showCountWorkspace ? (clone $adjustmentQuery)->with(['store', 'lines.product'])->latest('id')->limit(20)->get() : collect();
    $counts = $showCountWorkspace ? (clone $countQuery)->with(['store', 'lines:id,stock_count_id,is_counted'])->latest('id')->limit(20)->get() : collect();
    $needsProductOptions = str_starts_with((string) $focus, 'transfer') || str_starts_with((string) $focus, 'adjustment');
    $products = $needsProductOptions ? Product::query()->sellable()->select(['id', 'item_code', 'name_en', 'name_ar', 'fractional_quantity', 'parent_product_id'])->when($search !== '', static function ($query) use ($search): void {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $query->where(static function ($nested) use ($like): void {
            $nested->where('item_code', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like);
        });
    })->orderBy('item_code')->limit(200)->get() : collect();
    $countAssignees = $focus === 'count-create' ? User::query()->where('status', 'active')->limit(200)->get(['id', 'name', 'email', 'is_super_admin'])->filter(static fn (User $candidate): bool => $candidate->is_super_admin || $candidate->hasPermission('stock_counts.edit') || $candidate->hasPermission('stock_counts.close'))->values() : collect();
    $countCategories = $focus === 'count-create' ? Category::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_ar', 'name_en']) : collect();
    $countSuppliers = $focus === 'count-create' ? Supplier::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_ar', 'name_en']) : collect();

    return view('inventory/index', compact('balances', 'movements', 'transfers', 'transferApprovals', 'adjustments', 'counts', 'inventorySummary', 'focus', 'transfer', 'adjustment', 'count', 'visibleStores', 'products', 'countAssignees', 'countCategories', 'countSuppliers', 'search', 'filterStoreId', 'movementType', 'stockState', 'includeZero', 'inventoryOverview', 'canViewCost'));
};

$router->middleware(['auth', 'verified'])->group(function () use ($router, $renderInventory): void {
    $router->get('inventory', fn () => $renderInventory())->middleware('can:inventory_stock_card.view')->name('inventory.index');
    $router->get('inventory/balances', fn () => $renderInventory(null, 'balances'))->middleware('can:inventory_stock_card.view')->name('inventory.balances');
    $router->get('inventory/products/{product}', function (Product $product) use ($renderInventory): mixed {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless(StockBalance::query()->where('product_id', $product->id)->whereIn('store_id', Store::query()->visibleTo($user)->select('id'))->exists(), 403);

        return $renderInventory($product->id, 'stock-card');
    })->middleware('can:inventory_stock_card.view')->name('inventory.stock-card');
    $router->get('inventory/movements', fn () => $renderInventory(null, 'movements'))->middleware('can:inventory_stock_card.view')->name('inventory.movements');
    $router->get('inventory/export', function () {
        Gate::authorize('inventory_stock_card.export');
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($user)->pluck('id')->all();
        $storeId = request()->integer('store_id') ?: null;
        if ($storeId !== null && ! in_array($storeId, $visibleStoreIds, true)) {
            abort(403);
        }
        $query = StockBalance::query()->with(['product', 'store.branch'])->whereIn('store_id', $visibleStoreIds)->when($storeId !== null, fn ($builder) => $builder->where('store_id', $storeId));
        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('product', fn ($builder) => $builder->where('item_code', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('name_ar', 'like', $like));
        }
        $canViewCost = Gate::allows('inventory_stock_card.cost_view');
        $rows = $query->orderBy('store_id')->orderBy('product_id')->limit(5000)->get();
        app(RecordAuditEvent::class)->execute('inventory', 'export_stock_balances', 'inventory', after: ['row_count' => $rows->count(), 'cost_included' => $canViewCost], storeId: $storeId, metadata: ['filters' => request()->query()]);

        return response()->streamDownload(static function () use ($rows, $canViewCost): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $canViewCost ? ['product_name', 'product_code', 'location_name', 'location_code', 'location_type', 'branch_name', 'branch_code', 'on_hand', 'reserved', 'available', 'in_transit', 'average_cost'] : ['product_name', 'product_code', 'location_name', 'location_code', 'location_type', 'branch_name', 'branch_code', 'on_hand', 'reserved', 'available', 'in_transit']);
            foreach ($rows as $row) {
                $available = bcsub((string) $row->on_hand, (string) $row->reserved, 6);
                $values = [\App\Support\HumanName::name($row->product), $row->product?->item_code, \App\Support\HumanName::name($row->store), $row->store?->code, $row->store?->type, \App\Support\HumanName::name($row->store?->branch), $row->store?->branch?->code, \App\Support\ProductQuantity::format($row->on_hand), \App\Support\ProductQuantity::format($row->reserved), \App\Support\ProductQuantity::format($available), \App\Support\ProductQuantity::format($row->in_transit)];
                if ($canViewCost) {
                    $values[] = $row->average_cost;
                }
                fputcsv($output, $values);
            }
            fclose($output);
        }, 'inventory-balances-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('can:inventory_stock_card.export')->name('inventory.export');
    $router->get('inventory/transfers', fn () => $renderInventory(null, 'transfers'))->middleware('can:transfers.view')->name('inventory.transfers');
    $router->get('inventory/transfers/create', fn () => $renderInventory(null, 'transfer-create'))->middleware('can:transfers.create')->name('inventory.transfers.create');
    $router->get('inventory/transfers/{transfer}/edit', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-edit', $transfer->load(['sourceStore', 'destinationStore', 'lines.product']));
    })->middleware('can:transfers.edit')->name('inventory.transfers.edit');
    $router->get('inventory/transfers/{transfer}/dispatch', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-dispatch', $transfer->load(['sourceStore', 'destinationStore', 'lines.product']));
    })->middleware('can:transfers.view')->name('inventory.transfers.dispatch-page');
    $router->get('inventory/transfers/{transfer}/receive', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-receive', $transfer->load(['sourceStore', 'destinationStore', 'lines.product']));
    })->middleware('can:transfers.view')->name('inventory.transfers.receive-page');
    $router->get('inventory/transfers/{transfer}/differences', function (StockTransfer $transfer) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->transfer($transfer);

        return $renderInventory(null, 'transfer-differences', $transfer->load(['sourceStore', 'destinationStore', 'lines.product']));
    })->middleware('can:transfers.difference')->name('inventory.transfers.differences');
    $router->get('inventory/adjustments', fn () => $renderInventory(null, 'adjustments'))->middleware('can:inventory_stock_card.view')->name('inventory.adjustments');
    $router->get('inventory/adjustments/create', fn () => $renderInventory(null, 'adjustment-create'))->middleware('can:inventory_stock_card.create')->name('inventory.adjustments.create');
    $router->get('inventory/adjustments/{adjustment}/edit', function (InventoryAdjustment $adjustment) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->execute((int) $adjustment->store_id);

        return $renderInventory(null, 'adjustment-edit', null, $adjustment->load(['store', 'lines.product']));
    })->middleware('can:inventory_stock_card.edit')->name('inventory.adjustments.edit');
    $router->get('inventory/counts', fn () => $renderInventory(null, 'counts'))->middleware('can:inventory_stock_card.view')->name('inventory.counts');
    $router->get('inventory/counts/create', fn () => $renderInventory(null, 'count-create'))->middleware('can:stock_counts.create')->name('inventory.counts.create');
    $router->get('inventory/counts/{count}/entry', function (StockCount $count) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->execute((int) $count->store_id);

        return $renderInventory(null, 'count-entry', null, null, $count->load(['store','locations.store','members.user','lines.product']));
    })->middleware('can:stock_counts.view')->name('inventory.counts.entry');
    $router->get('inventory/counts/{count}/reconcile', function (StockCount $count) use ($renderInventory) {
        app(AssertInventoryStoreScope::class)->execute((int) $count->store_id);

        return $renderInventory(null, 'count-reconcile', null, null, $count->load(['store','locations.store','members.user','lines.product']));
    })->middleware('can:stock_counts.reconcile')->name('inventory.counts.reconcile-page');

    $router->post('inventory/transfers', function (CreateStockTransferDraftAction $action) {
        $validated = request()->validate(['source_store_id' => ['required', 'integer', 'exists:stores,id'], 'destination_store_id' => ['required', 'integer', 'different:source_store_id', 'exists:stores,id'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity_requested' => ['required', 'integer', 'min:1'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'lock_version' => ['nullable', 'integer']]);
        try {
            $transfer = $action->execute((int) $validated['source_store_id'], (int) $validated['destination_store_id'], $validated['lines'], $validated['reason_code'], $validated['reason_notes'] ?? null);

            return redirect()->route('inventory.transfers.edit', $transfer)->with('success', __('Transfer saved as draft.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->middleware('can:transfers.create')->name('inventory.transfers.store');

    $router->post('inventory/transfers/{transfer}', function (StockTransfer $transfer, UpdateStockTransferDraftAction $action) {
        $validated = request()->validate(['source_store_id' => ['required', 'integer', 'exists:stores,id'], 'destination_store_id' => ['required', 'integer', 'different:source_store_id', 'exists:stores,id'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity_requested' => ['required', 'integer', 'min:1'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'lock_version' => ['nullable', 'integer']]);
        try {
            $saved = $action->execute($transfer->id, (int) $validated['source_store_id'], (int) $validated['destination_store_id'], $validated['lines'], $validated['reason_code'], $validated['reason_notes'] ?? null, $validated['lock_version'] ?? $transfer->lock_version);

            return redirect()->route('inventory.transfers.edit', $saved)->with('success', __('Transfer draft updated.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.edit')->name('inventory.transfers.update');

    $router->post('inventory/transfers/{transfer}/submit', function (StockTransfer $transfer, SubmitStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer submitted for approval.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.submit')->name('inventory.transfers.submit');

    $router->post('inventory/transfers/{transfer}/approve', function (StockTransfer $transfer, ApproveStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer approved in Local Demo.'));
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['transfer' => \App\Support\UserSafeError::message($exception)]);
        } catch (Throwable $exception) {

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.approve')->name('inventory.transfers.approve');

    $router->post('inventory/transfers/{transfer}/request-approval', function (StockTransfer $transfer, RequestStockTransferApprovalAction $action) {
        try {
            $action->execute($transfer->id);

            return redirect()->route('admin.approvals')->with('approval-success', __('Transfer approval requested.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.submit')->name('inventory.transfers.request-approval');

    $router->post('inventory/transfers/{transfer}/dispatch', function (StockTransfer $transfer, DispatchStockTransferAction $action) {
        try {
            $action->execute($transfer->id);

            return back()->with('success', __('Transfer dispatched and source stock posted.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.dispatch')->name('inventory.transfers.dispatch');

    $router->post('inventory/transfers/{transfer}/receive', function (StockTransfer $transfer, ReceiveStockTransferAction $action) {
        try {
            $validated = request()->validate(['received_quantities' => ['required', 'array', 'min:1'], 'received_quantities.*' => ['required', 'numeric', 'min:0'], 'difference_type' => ['nullable', 'in:shortage,damage,refusal'], 'difference_reason' => ['nullable', 'string', 'max:1000']]);
            $action->execute($transfer->id, $validated['received_quantities'], $validated['difference_type'] ?? null, $validated['difference_reason'] ?? null);

            return back()->with('success', __('Transfer receipt recorded in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.receive')->name('inventory.transfers.receive');

    $router->post('inventory/transfers/{transfer}/differences/resolve', function (StockTransfer $transfer, ResolveTransferDifferenceAction $action) {
        try {
            $validated = request()->validate(['difference_type' => ['required', 'in:shortage,damage,refusal'], 'difference_reason' => ['required', 'string', 'max:1000']]);
            $action->execute($transfer->id, $validated['difference_type'], $validated['difference_reason']);

            return back()->with('success', __('Transfer difference resolved in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('transfer')->middleware('can:transfers.difference')->name('inventory.transfers.differences.resolve');

    $router->post('inventory/adjustments/{adjustment}/submit', function (InventoryAdjustment $adjustment, SubmitInventoryAdjustmentAction $action) {
        try {
            $action->execute($adjustment->id);

            return back()->with('success', __('Adjustment submitted in Local Demo.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.submit')->name('inventory.adjustments.submit');

    $router->post('inventory/adjustments/{adjustment}/approve', function (InventoryAdjustment $adjustment, ApproveInventoryAdjustmentAction $action) {
        try {
            $action->execute($adjustment->id);

            return back()->with('success', __('Adjustment approved and movement posted.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.approve')->name('inventory.adjustments.approve');

    $router->post('inventory/adjustments', function (SaveInventoryAdjustmentAction $action) {
        $validated = request()->validate(['store_id' => ['required', 'integer'], 'adjustment_type' => ['required', 'in:entry,exit,exchange,adjustment'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'allow_negative' => ['nullable', 'boolean'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity_delta' => ['required', 'integer', 'not_in:0'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0']]);
        try {
            $document = $action->execute($validated, $validated['lines']);

            return redirect()->route('inventory.adjustments.edit', $document)->with('success', __('Inventory movement saved as draft.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->middleware('can:inventory_stock_card.create')->name('inventory.adjustments.store');

    $router->post('inventory/adjustments/{adjustment}', function (InventoryAdjustment $adjustment, SaveInventoryAdjustmentAction $action) {
        $validated = request()->validate(['store_id' => ['required', 'integer'], 'adjustment_type' => ['required', 'in:entry,exit,exchange,adjustment'], 'reason_code' => ['required', 'string', 'max:100'], 'reason_notes' => ['nullable', 'string', 'max:1000'], 'allow_negative' => ['nullable', 'boolean'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.quantity_delta' => ['required', 'integer', 'not_in:0'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0']]);
        try {
            $document = $action->execute($validated, $validated['lines'], $adjustment->id, $adjustment->lock_version);

            return redirect()->route('inventory.adjustments.edit', $document)->with('success', __('Inventory movement draft updated.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.edit')->name('inventory.adjustments.update');

    $router->post('inventory/adjustments/{adjustment}/reverse', function (InventoryAdjustment $adjustment, ReverseInventoryAdjustmentAction $action) {
        $validated = request()->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $action->execute($adjustment->id, $validated['reason']);

            return back()->with('success', __('Approved movement reversed with a linked corrective document.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('adjustment')->middleware('can:inventory_stock_card.reverse')->name('inventory.adjustments.reverse');

    $router->post('inventory/counts/{count}/submit', function (StockCount $count, SubmitStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count submitted with movement-window calculation.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.submit')->name('inventory.counts.submit');

    $router->post('inventory/counts', function (SaveStockCountAction $action) {
        $validated = request()->validate(['branch_id'=>['required','integer','exists:branches,id'],'location_ids'=>['required','array','min:1'],'location_ids.*'=>['integer','distinct','exists:stores,id'],'member_ids'=>['required','array','min:1'],'member_ids.*'=>['integer','distinct','exists:users,id'],'manager_id'=>['required','integer','exists:users,id'],'count_type'=>['required','in:full,partial'],'scope_type'=>['required','in:full,category,supplier,partial'],'category_id'=>['nullable','integer'],'supplier_id'=>['nullable','integer'],'notes'=>['nullable','string','max:1000'],'assignments'=>['nullable','array'],'assignments.*.store_id'=>['integer'],'assignments.*.user_id'=>['integer'],'assignments.*.zone'=>['nullable','string','max:120']]);
        try {
            $count = $action->execute($validated);

            return redirect()->route('inventory.counts.entry', $count)->with('success', __('Count session created. Review its scope and open it when the team is ready.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->middleware('can:stock_counts.create')->name('inventory.counts.store');

    $router->post('inventory/counts/{count}/open', function(StockCount $count, OpenStockCountSessionAction $action){$action->execute($count->id);return back()->with('success',__('Count session opened with immutable location snapshots.'));})->whereNumber('count')->middleware('can:stock_counts.open')->name('inventory.counts.open');
    $router->post('inventory/counts/{count}/contributions', function(StockCount $count, RecordStockCountContributionAction $action){$v=request()->validate(['store_id'=>['required','integer'],'lookup'=>['required','string','max:255'],'quantity'=>['required','numeric'],'request_id'=>['required','uuid'],'device_id'=>['required','string','max:120'],'input_method'=>['required','in:scan,batch,manual']]);$entry=$action->execute($count->id,(int)$v['store_id'],$v['lookup'],(string)$v['quantity'],$v['request_id'],$v['device_id'],$v['input_method']);return back()->with('success',__('Quantity added. Updated counted total: :total',['total'=>$entry->count->contributions()->where('store_id',$entry->store_id)->where('product_id',$entry->product_id)->sum('quantity')]));})->whereNumber('count')->middleware('can:stock_counts.participate')->name('inventory.counts.contribute');
    $router->post('inventory/count-lines/{line}/complete', function(\App\Modules\Inventory\Models\StockCountLine $line,CompleteStockCountLineAction $action){$action->execute($line->id,request()->boolean('explicit_zero'));return back()->with('success',__('Product/location count marked complete.'));})->whereNumber('line')->middleware('can:stock_counts.participate')->name('inventory.counts.complete-line');
    $router->post('inventory/counts/{count}/recount',function(StockCount $count,RequestStockCountRecountAction $action){$v=request()->validate(['line_ids'=>['required','array','min:1'],'line_ids.*'=>['integer'],'reason'=>['required','string','max:1000']]);$action->execute($count->id,$v['line_ids'],$v['reason']);return redirect()->route('inventory.counts.entry',$count)->with('success',__('Recount requested with history preserved.'));})->whereNumber('count')->middleware('can:stock_counts.request_recount')->name('inventory.counts.request-recount');
    $router->post('inventory/counts/{count}/cancel',function(StockCount $count,CancelStockCountSessionAction $action){$v=request()->validate(['reason'=>['required','string','max:1000']]);$action->execute($count->id,$v['reason']);return redirect()->route('inventory.counts')->with('success',__('Count session cancelled and transfer locks released.'));})->whereNumber('count')->middleware('can:stock_counts.cancel')->name('inventory.counts.cancel');
    $router->get('inventory/counts/{count}/products',function(StockCount $count){$user=Auth::user();abort_unless($user instanceof User&&$count->members()->where('user_id',$user->id)->exists(),404);$term=trim((string)request('q'));abort_if(mb_strlen($term)<2,422);$like='%'.addcslashes($term,'%_\\').'%';return Product::query()->sellable()->where(fn($q)=>$q->where('item_code','like',$like)->orWhere('model_number','like',$like)->orWhere('name_ar','like',$like)->orWhere('name_en','like',$like))->orderBy('item_code')->limit(20)->get(['id','item_code','name_ar','name_en','model_number']);})->whereNumber('count')->middleware('can:stock_counts.participate')->name('inventory.counts.products');
    $router->get('inventory/counts/{count}/report',function(StockCount $count){$user=Auth::user();abort_unless($user instanceof User,403);$visible=Store::query()->visibleTo($user)->select('id');abort_if($count->locations()->whereNotIn('store_id',$visible)->exists()||!$count->locations()->exists(),404);$count->load(['locations.store.branch','members.user','contributions.product','contributions.store','contributions.counter','lines.product','lines.store','creator']);return view('inventory.count-report',compact('count'));})->whereNumber('count')->middleware('can:stock_counts.view')->name('inventory.counts.report');

    $router->post('inventory/counts/{count}/entry', function (StockCount $count, RecordStockCountLineAction $action) {
        $validated = request()->validate(['counted_quantities' => ['required', 'array'], 'counted_quantities.*' => ['required', 'numeric', 'min:0'], 'input_methods' => ['nullable', 'array'], 'recount' => ['nullable', 'boolean']]);
        try {
            $action->execute($count->id, $validated['counted_quantities'], $validated['input_methods'] ?? [], (bool) ($validated['recount'] ?? false));

            return back()->with('success', __('Stock count quantities saved.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.entry.save');

    $router->post('inventory/counts/{count}/entry/recount', function (StockCount $count, RecordStockCountLineAction $action) {
        $validated = request()->validate(['counted_quantities' => ['required', 'array'], 'counted_quantities.*' => ['required', 'numeric', 'min:0'], 'input_methods' => ['nullable', 'array']]);
        try {
            $action->execute($count->id, $validated['counted_quantities'], $validated['input_methods'] ?? [], true);

            return back()->with('success', __('Stock recount saved.'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.edit')->name('inventory.counts.entry.recount');

    $router->post('inventory/counts/{count}/reconcile', function (StockCount $count, ReconcileStockCountAction $action) {
        try {
            $action->execute($count->id);

            return back()->with('success', __('Stock count reconciled; uncounted products were preserved.'));
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()->with('error', __('Inventory operation failed. Please review the record and try again.'));
        }
    })->whereNumber('count')->middleware('can:stock_counts.reconcile')->name('inventory.counts.reconcile');
});
