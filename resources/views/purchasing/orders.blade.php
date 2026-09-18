<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Modules\Purchasing\Actions\ClosePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Queries\PurchasingDashboard;
use App\Modules\Purchasing\Queries\SupplierProductPrice;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Orders')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $supplierFilter = 'all';

    public string $storeFilter = 'all';

    public string $branchFilter = 'all';

    public bool $showFormModal = false;

    public bool $createPage = false;

    public ?int $editingOrderId = null;

    public array $orderForm = [
        'supplier_id' => '',
        'store_id' => '',
        'order_date' => '',
        'expected_delivery_date' => '',
        'payment_terms' => '',
        'notes' => '',
        'lock_version' => 0,
    ];

    public array $lineItems = [];

    public bool $paymentTermsManuallyEdited = false;

    public bool $syncingPaymentTerms = false;

    public bool $supplierProductsOnly = true;

    public string $confirmedSupplierId = '';

    public string $pendingSupplierId = '';

    public bool $showSupplierChangeConfirmation = false;

    public bool $showDetailModal = false;

    public ?int $viewingOrderId = null;

    public string $detailTab = 'items';

    public bool $showCancelModal = false;

    public ?int $cancellingOrderId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->createPage = request()->routeIs('purchasing.orders.create', 'purchasing.orders.edit');
        Gate::authorize(request()->routeIs('purchasing.orders.edit') ? 'purchase_orders.edit' : ($this->createPage ? 'purchase_orders.create' : 'purchase_orders.view'));
        $contextStoreId = app(\App\Modules\Platform\Support\WorkContext::class)->id(auth()->user());
        $this->storeFilter = $contextStoreId === null ? 'all' : (string) $contextStoreId;
        if (request()->routeIs('purchasing.orders.edit')) {
            $order = request()->route('order');
            $this->loadDraft($order instanceof PurchaseOrder ? $order->id : (int) $order);
        } elseif ($this->createPage) {
            $this->initializeCreateForm();
        } else {
            $this->orderForm['order_date'] = now()->toDateString();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void { $this->resetPage(); }

    private function initializeCreateForm(): void
    {
        $this->resetValidation();
        $this->editingOrderId = null;
        $this->paymentTermsManuallyEdited = false;
        $this->orderForm = [
            'supplier_id' => '',
            'store_id' => '',
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => '',
            'payment_terms' => '',
            'notes' => '',
            'lock_version' => 0,
        ];
        $this->lineItems = [$this->emptyOrderLine()];
        $this->confirmedSupplierId = '';
        $this->pendingSupplierId = '';
        $this->supplierProductsOnly = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('purchase_orders.edit');

        $this->redirectRoute('purchasing.orders.edit', ['order' => $id], navigate: true);
    }

    private function loadDraft(int $id): void
    {

        $order = PurchaseOrder::query()->with('lines')->findOrFail($id);
        if ($order->status !== 'draft') {
            Flux::toast(__('Only draft purchase orders can be edited.'), variant: 'danger');

            return;
        }

        $this->resetValidation();
        $this->editingOrderId = $order->id;
        $this->paymentTermsManuallyEdited = true;
        $this->orderForm = [
            'supplier_id' => (string) $order->supplier_id,
            'store_id' => $order->store_id ? (string) $order->store_id : '',
            'order_date' => $order->order_date?->format('Y-m-d') ?: now()->toDateString(),
            'expected_delivery_date' => $order->expected_delivery_date?->format('Y-m-d') ?: '',
            'payment_terms' => $order->payment_terms ?: '',
            'notes' => $order->notes ?: '',
            'lock_version' => $order->lock_version,
        ];

        $this->lineItems = [];
        foreach ($order->lines as $line) {
            $this->lineItems[] = [
                'product_id' => (string) $line->product_id,
                'product_unit_id' => $line->product_unit_id ? (string) $line->product_unit_id : '',
                'conversion_factor' => $line->conversion_factor_snapshot ?? '1.000000',
                'quantity_ordered' => (string) ($line->entered_quantity ?? $line->quantity_ordered),
                'unit_cost' => (string) ($line->entered_unit_price ?? $line->unit_cost),
                'notes' => $line->notes ?: '',
                'price_source' => 'saved_draft_cost',
                'price_date' => '',
                'price_currency' => '',
            ];
        }

        if (empty($this->lineItems)) {
            $this->lineItems[] = $this->emptyOrderLine();
        }

        $this->confirmedSupplierId = (string) $order->supplier_id;
        $this->pendingSupplierId = '';
        $this->supplierProductsOnly = true;
    }

    public function updatedOrderFormSupplierId($supplierId): void
    {
        $supplierId = filled($supplierId) ? (string) $supplierId : '';
        if ($this->confirmedSupplierId !== '' && $supplierId !== $this->confirmedSupplierId && collect($this->lineItems)->contains(fn (array $line): bool => filled($line['product_id'] ?? null))) {
            $this->pendingSupplierId = $supplierId;
            $this->orderForm['supplier_id'] = $this->confirmedSupplierId;
            $this->showSupplierChangeConfirmation = true;

            return;
        }

        $this->confirmedSupplierId = $supplierId;
        $this->supplierProductsOnly = $supplierId !== '';
        $this->applySupplierPaymentTerms($supplierId);
        $this->dispatch('purchasing-product-focus');
    }

    public function confirmSupplierChange(): void
    {
        $this->orderForm['supplier_id'] = $this->pendingSupplierId;
        $this->confirmedSupplierId = $this->pendingSupplierId;
        $this->pendingSupplierId = '';
        $this->lineItems = [$this->emptyOrderLine()];
        $this->supplierProductsOnly = $this->confirmedSupplierId !== '';
        $this->showSupplierChangeConfirmation = false;
        $this->paymentTermsManuallyEdited = false;
        $this->applySupplierPaymentTerms($this->confirmedSupplierId);
        $this->dispatch('purchasing-product-focus');
    }

    public function cancelSupplierChange(): void
    {
        $this->pendingSupplierId = '';
        $this->showSupplierChangeConfirmation = false;
    }

    private function applySupplierPaymentTerms(string $supplierId): void
    {
        if ($this->editingOrderId !== null || $this->paymentTermsManuallyEdited || $supplierId === '') {
            return;
        }

        $terms = Supplier::query()->whereKey((int) $supplierId)->value('payment_terms');
        if (filled($terms)) {
            $this->syncingPaymentTerms = true;
            $this->orderForm['payment_terms'] = (string) $terms;
            $this->syncingPaymentTerms = false;
        }
    }

    public function updatedOrderFormPaymentTerms(): void
    {
        if ($this->editingOrderId === null && ! $this->syncingPaymentTerms) {
            $this->paymentTermsManuallyEdited = true;
        }
    }

    public function addLine(): void
    {
        if (! filled($this->orderForm['supplier_id'])) {
            Flux::toast(__('Select a supplier before adding products.'), variant: 'warning');

            return;
        }
        $this->lineItems[] = $this->emptyOrderLine();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function selectOrderProduct(int $index, int $productId, SupplierProductPrice $prices): void
    {
        abort_unless(filled($this->orderForm['supplier_id']), 422, __('Select a supplier before adding products.'));
        $product = Product::query()->sellable()->with('productUnits.unit')->findOrFail($productId);
        $price = $prices->resolve($product, (int) $this->orderForm['supplier_id']);
        $purchaseUnits = $product->productUnits->filter(fn ($unit) => $unit->is_purchase_unit && $unit->unit?->status === 'active');
        $unit = $purchaseUnits->firstWhere('is_base_unit', true) ?? $purchaseUnits->first();
        $this->lineItems[$index]['product_id'] = (string) $product->id;
        $this->lineItems[$index]['product_unit_id'] = $unit ? (string) $unit->id : '';
        $this->lineItems[$index]['conversion_factor'] = $unit?->conversion_factor ?? '1.000000';
        $this->lineItems[$index]['unit_cost'] = $price['unit_cost'] === null ? '' : bcmul((string) $price['unit_cost'], (string) ($unit?->conversion_factor ?? '1'), 4);
        $this->lineItems[$index]['price_source'] = $price['price_source'];
        $this->lineItems[$index]['price_date'] = $price['price_date'] ?? '';
        $this->lineItems[$index]['price_currency'] = $price['price_currency'] ?? '';
    }

    public function changeOrderUnit(int $index): void
    {
        $line = $this->lineItems[$index] ?? null;
        abort_unless(is_array($line), 422);
        $unit = \App\Modules\Catalog\Models\ProductUnit::query()->with('unit')
            ->where('product_id', (int) $line['product_id'])->where('is_purchase_unit', true)
            ->when(filled($line['product_unit_id'] ?? null), fn ($query) => $query->whereKey((int) $line['product_unit_id']), fn ($query) => $query->where('is_base_unit', true))
            ->firstOrFail();
        abort_unless($unit->unit?->status === 'active', 422);
        $this->lineItems[$index]['product_unit_id'] = (string) $unit->id;
        $oldFactor = (string) ($line['conversion_factor'] ?? '1');
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $oldFactor) !== 1 || bccomp($oldFactor, '0', 6) <= 0) {
            $oldFactor = '1';
        }
        if (preg_match('/^\d+(?:\.\d{1,4})?$/D', (string) ($line['unit_cost'] ?? '')) === 1) {
            $this->lineItems[$index]['unit_cost'] = bcdiv(bcmul((string) $line['unit_cost'], (string) $unit->conversion_factor, 10), $oldFactor, 4);
        }
        $this->lineItems[$index]['conversion_factor'] = (string) $unit->conversion_factor;
    }

    public function saveOrder(): void
    {
        Gate::authorize($this->editingOrderId ? 'purchase_orders.edit' : 'purchase_orders.create');

        $this->validate([
            'orderForm.supplier_id' => 'required|exists:suppliers,id',
            'orderForm.store_id' => 'required|exists:stores,id',
            'orderForm.order_date' => 'required|date',
            'orderForm.expected_delivery_date' => 'nullable|date|after_or_equal:orderForm.order_date',
            'lineItems' => 'required|array|min:1',
            'lineItems.*.product_id' => 'required|exists:products,id',
            'lineItems.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
            'lineItems.*.quantity_ordered' => 'required|numeric|gt:0|decimal:0,6',
            'lineItems.*.unit_cost' => 'required|numeric|gte:0',
        ]);

        try {
            $action = app(SavePurchaseOrderAction::class);
            $order = $action->execute(
                data: $this->orderForm,
                lines: $this->lineItems,
                id: $this->editingOrderId,
                expectedVersion: $this->editingOrderId ? (int) $this->orderForm['lock_version'] : null,
            );

            app(\App\Modules\Purchasing\Actions\GeneratePurchaseOrderPdfAction::class)->execute($order);

            $this->showFormModal = false;
            Flux::toast($this->editingOrderId ? __('Purchase Order updated successfully.') : __('Purchase Order :number created as draft.', ['number' => $order->po_number]), variant: 'success');
            if ($this->createPage) $this->redirectRoute('purchasing.orders', navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Flux::toast(\App\Support\UserSafeError::message($e), variant: 'danger');
        }
    }

    /** @return array<string, int|string> */
    private function emptyOrderLine(): array
    {
        return ['product_id' => '', 'product_unit_id' => '', 'conversion_factor' => '1.000000', 'quantity_ordered' => '1', 'unit_cost' => '', 'notes' => '', 'price_source' => 'none', 'price_date' => '', 'price_currency' => ''];
    }

    public function submitOrder(int $id): void
    {
        Gate::authorize('purchase_orders.edit');

        try {
            $order = PurchaseOrder::findOrFail($id);
            app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Flux::toast(__('Purchase Order :number submitted successfully.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast(\App\Support\UserSafeError::message($e), variant: 'danger');
        }
    }

    public function approveOrder(int $id): void
    {
        Gate::authorize('purchase_orders.approve');

        try {
            $order = PurchaseOrder::findOrFail($id);
            app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            app(\App\Modules\Purchasing\Actions\GeneratePurchaseOrderPdfAction::class)->execute($order->fresh());
            Flux::toast(__('Purchase Order :number approved successfully. No stock or invoice posting occurred.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast(\App\Support\UserSafeError::message($e), variant: 'danger');
        }
    }

    public function openCancelModal(int $id): void
    {
        Gate::authorize('purchase_orders.cancel');

        $this->cancellingOrderId = $id;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function cancelOrder(): void
    {
        Gate::authorize('purchase_orders.cancel');

        $this->validate([
            'cancelReason' => 'required|string|min:3|max:500',
        ]);

        try {
            $order = PurchaseOrder::findOrFail($this->cancellingOrderId);
            app(CancelPurchaseOrderAction::class)->execute($order->id, $this->cancelReason, $order->lock_version);

            $this->showCancelModal = false;
            Flux::toast(__('Purchase Order :number cancelled.', ['number' => $order->po_number]), variant: 'warning');
        } catch (Throwable $e) {
            Flux::toast(\App\Support\UserSafeError::message($e), variant: 'danger');
        }
    }

    public function closeOrder(int $id): void
    {
        Gate::authorize('purchase_orders.edit');

        try {
            $order = PurchaseOrder::findOrFail($id);
            app(ClosePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Flux::toast(__('Purchase Order :number closed.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast(\App\Support\UserSafeError::message($e), variant: 'danger');
        }
    }

    public function openDetailModal(int $id): void
    {
        Gate::authorize('purchase_orders.view');

        $this->viewingOrderId = $id;
        $this->detailTab = 'items';
        $this->showDetailModal = true;
    }

    public function render(PurchasingDashboard $dashboard): View
    {
        $user = auth()->user();
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name_ar')->get();
        $stores = Store::visibleTo($user)->where('status', 'active')->orderBy('name_ar')->get();
        $products = Product::query()->sellable()->with(['productUnits.unit', 'baseProductUnit.unit', 'productSuppliers' => fn($query) => $query->when(filled($this->orderForm['supplier_id']), fn($scope) => $scope->where('supplier_id',(int)$this->orderForm['supplier_id']))])->whereIn('id', collect($this->lineItems)->pluck('product_id')->filter()->map(fn ($id): int => (int) $id))->get();
        $formSubtotal = collect($this->lineItems)->sum(fn (array $item): float => (float) ($item['quantity_ordered'] ?? 0) * (float) ($item['unit_cost'] ?? 0));

        if ($this->createPage) {
            return view('purchasing.orders', [
                'createPage' => $this->createPage,
                'suppliers' => $suppliers,
                'stores' => $stores,
                'products' => $products,
                'formSubtotal' => $formSubtotal,
                'viewingOrder' => null,
                'auditLogs' => collect(),
                'canPrint' => false,
            ]);
        }

        $query = PurchaseOrder::query()->with(['supplier', 'store', 'branch', 'creator', 'lines']);

        if ($user && ! $user->is_super_admin) {
            $query->where(function ($scope) use ($user) {
                $scope->whereIn('store_id', Store::visibleTo($user)->select('id'))
                    ->orWhereIn('branch_id', Branch::visibleTo($user)->select('id'));
            });
        }

        if (! empty($this->search)) {
            $search = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', $search)
                    ->orWhere('notes', 'like', $search)
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name_ar', 'like', $search)
                            ->orWhere('name_en', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    });
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->supplierFilter !== 'all') {
            $query->where('supplier_id', (int) $this->supplierFilter);
        }
        if ($this->storeFilter !== 'all') {
            $query->where('store_id', (int) $this->storeFilter);
        }
        if ($this->branchFilter !== 'all') $query->where('branch_id', (int) $this->branchFilter);

        $orders = $query->latest('id')->paginate(15);
        $viewingOrderQuery = PurchaseOrder::query()->with(['supplier', 'store', 'branch', 'creator', 'submitter', 'approver', 'canceller', 'closer', 'lines.product', 'invoices.store', 'documents.attachment']);
        if ($user && ! $user->is_super_admin) {
            $viewingOrderQuery->where(function ($scope) use ($user) {
                $scope->whereIn('store_id', Store::visibleTo($user)->select('id'))
                    ->orWhereIn('branch_id', Branch::visibleTo($user)->select('id'));
            });
        }
        $viewingOrder = $this->viewingOrderId ? $viewingOrderQuery->find($this->viewingOrderId) : null;

        $auditLogs = $viewingOrder ? AuditLog::query()
            ->where('source_type', PurchaseOrder::class)
            ->where('source_id', (string) $viewingOrder->id)
            ->latest('id')
            ->get() : collect();

        return view('purchasing.orders', [
            'createPage' => $this->createPage,
            'orders' => $orders,
            'suppliers' => $suppliers,
            'stores' => $stores,
            'branches' => Branch::query()->visibleTo($user)->where('status', 'active')->withCount('purchaseOrders')->orderBy('code')->get(),
            'products' => $products,
            'viewingOrder' => $viewingOrder,
            'auditLogs' => $auditLogs,
            'formSubtotal' => $formSubtotal,
            'canCreate' => Gate::allows('purchase_orders.create'),
            'canEdit' => Gate::allows('purchase_orders.edit'),
            'canCancel' => Gate::allows('purchase_orders.cancel'),
            'canPrint' => Gate::allows('purchase_orders.print'),
            'canApprove' => Gate::allows('purchase_orders.approve'),
            'dashboard' => $dashboard->for($user, $this->storeFilter === 'all' ? null : (int) $this->storeFilter),
        ]);
    }
}; ?>

<x-app.page
    :title="$createPage ? ($editingOrderId ? __('Edit Draft Purchase Order') : __('New Purchase Order')) : (str_starts_with(app()->getLocale(), 'ar') ? 'نظرة عامة على المشتريات' : __('Purchasing overview'))"
    :description="$createPage ? __('Enter supplier, destination store, order dates, and item lines.') : (str_starts_with(app()->getLocale(), 'ar') ? 'المشتريات والاستلام والاستثناءات التشغيلية ضمن المواقع المصرح بها.' : __('Purchasing, receiving, and operational exceptions within your authorized locations.'))"
    :breadcrumbs="str_starts_with(app()->getLocale(), 'ar') ? 'المشتريات والموردون' : 'Purchasing & suppliers'"
    :max-width="$createPage ? 'full' : '7xl'"
    class="purchasing-screen"
    data-guide="po-header"
>
    <x-slot:actions>
        @if ($createPage)
            <flux:button href="{{ route('purchasing.orders') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Purchase Order Overview') }}</flux:button>
        @else
        <x-tables.resource-toolbar filter-target="po-filters">
        <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="clipboard-document-list" data-guide="tsk-015-readiness-link">
            {{ str_starts_with(app()->getLocale(), 'ar') ? 'فواتير الشراء' : 'Purchase invoices' }}
        </flux:button>
        @if ($canCreate)
            <div>
                <flux:button variant="primary" icon="plus" href="{{ route('purchasing.orders.create') }}" wire:navigate data-guide="po-create-action">
                    {{ __('New Purchase Order') }}
                </flux:button>
            </div>
        @endif
        </x-tables.resource-toolbar>
        @endif
    </x-slot:actions>

    @if ($createPage)
        <flux:card class="p-3 sm:p-4" data-guide="po-create-page">
            @include('purchasing.partials.order-form', ['fullPage' => true])
        </flux:card>
    @else
    <x-purchasing.dashboard :dashboard="$dashboard" />

    <!-- Filters Bar -->
    <flux:card id="po-filters" class="scroll-mt-24 space-y-4 p-5 sm:p-6" data-guide="po-filters">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by PO #, supplier or notes...')" />

            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <option value="all">{{ __('All Statuses') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
                <option value="submitted">{{ __('Submitted') }}</option>
                <option value="approved">{{ __('Approved') }}</option>
                <option value="partially_received">{{ __('Partially Received') }}</option>
                <option value="received">{{ __('Received') }}</option>
                <option value="cancelled">{{ __('Cancelled') }}</option>
                <option value="closed">{{ __('Closed') }}</option>
            </flux:select>

            <flux:select wire:model.live="supplierFilter" :label="__('Supplier')">
                <option value="all">{{ __('All Suppliers') }}</option>
                @foreach ($suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $sup->name_ar : ($sup->name_en ?: $sup->name_ar) }} ({{ $sup->code }})</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="branchFilter" :label="__('Branch')"><option value="all">{{ __('All branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ str_starts_with(app()->getLocale(),'ar') ? $branch->name_ar : $branch->name_en }} · {{ $branch->purchase_orders_count ?? '' }}</option>@endforeach</flux:select>
        </div>
    </flux:card>

    <!-- Orders Table -->
    <flux:card id="purchase-orders-queue" class="scroll-mt-24 overflow-hidden p-0" data-guide="po-table">
        <div class="app-table-frame">
            <table class="data-table responsive-resource-table w-full text-start text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('PO Number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Branch') }} / {{ __('Receiving Store / Warehouse') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Order Date') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Total') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition">
                            <td data-primary class="px-4 py-3 font-mono font-bold text-primary">
                                {{ $order->po_number }}
                            </td>
                            <td data-label="{{ __('Supplier') }}" class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ str_starts_with(app()->getLocale(), 'ar') ? $order->supplier->name_ar : ($order->supplier->name_en ?: $order->supplier->name_ar) }}
                                </div>
                                <div class="text-xs font-mono text-zinc-500">{{ $order->supplier->code }}</div>
                            </td>
                            <td data-label="{{ __('Branch') }} / {{ __('Receiving Store / Warehouse') }}" class="px-4 py-3 text-zinc-600 dark:text-zinc-400 text-xs">
                                <div>{{ $order->branch ? (str_starts_with(app()->getLocale(), 'ar') ? $order->branch->name_ar : $order->branch->name_en) : '—' }}</div><div class="text-zinc-500">{{ $order->store ? (str_starts_with(app()->getLocale(), 'ar') ? $order->store->name_ar : $order->store->name_en) : '—' }}</div>
                            </td>
                            <td data-label="{{ __('Status') }}" class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium uppercase tracking-wide
                                    @if($order->status === 'draft') bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300
                                    @elseif($order->status === 'submitted') bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300
                                    @elseif($order->status === 'approved') bg-violet-100 text-violet-800 dark:bg-violet-950/80 dark:text-violet-300
                                    @elseif($order->status === 'partially_received') bg-sky-100 text-sky-800 dark:bg-sky-950/80 dark:text-sky-300
                                    @elseif($order->status === 'received') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300
                                    @elseif($order->status === 'cancelled') bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300
                                    @else bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300 @endif">
                                    {{ __($order->workflowStatusLabel()) }}
                                </span>
                                <div class="mt-1 text-[10px] text-zinc-500">{{ __($order->nextAction()) }} · {{ __($order->receivingState()) }}</div>
                            </td>
                            <td data-label="{{ __('Order Date') }}" class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400 font-mono">
                                {{ $order->order_date?->format('Y-m-d') }}
                            </td>
                            <td data-label="{{ __('Total') }}" class="px-4 py-3 text-end font-mono font-semibold text-zinc-900 dark:text-white">
                                {{ number_format((float) $order->total_amount, 2) }}
                            </td>
                            <td data-label="{{ __('Actions') }}" class="px-4 py-3 text-end whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-actions.button semantic="view" :label="__('View Details')" size="xs" wire:click="openDetailModal({{ $order->id }})" />

                                    @if ($order->isDraft() && $canEdit)
                                        <x-actions.button semantic="edit" :label="__('Edit Draft')" size="xs" wire:click="openEditModal({{ $order->id }})" />
                                        <flux:button size="xs" variant="subtle" icon="paper-airplane" wire:click="submitOrder({{ $order->id }})" title="{{ __('Submit Order') }}" />
                                    @endif

                                    @if ($order->status === 'submitted' && $canApprove && $order->submitted_by !== auth()->id())
                                        <x-actions.button semantic="approve" :label="__('Approve Order')" size="xs" wire:click="approveOrder({{ $order->id }})" />
                                    @endif

                                    @if (in_array($order->status, ['approved', 'partially_received'], true) && Gate::allows('purchase_invoices_supplier_returns.create') && $order->lines->contains(fn ($line): bool => bccomp(bcsub((string) $line->quantity_ordered, (string) $line->quantity_received, 6), '0', 6) > 0))
                                        <flux:button size="xs" variant="primary" icon="inbox-arrow-down" href="{{ route('purchasing.invoices', ['purchase_order' => $order->id]) }}" wire:navigate>
                                            {{ __('Receive') }}
                                        </flux:button>
                                    @endif

                                    @if ($order->isCancellable() && $canCancel)
                                        <x-actions.button semantic="cancel" :label="__('Cancel Order')" size="xs" wire:click="openCancelModal({{ $order->id }})" />
                                    @endif

                                    @if ($order->isClosable() && $canEdit)
                                        <x-actions.button semantic="complete" :label="__('Close Order')" size="xs" wire:click="closeOrder({{ $order->id }})" />
                                    @endif

                                    @if ($canPrint)
                                        <a href="{{ route('purchasing.orders.print', $order->id) }}" target="_blank" class="inline-flex items-center justify-center p-1 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition" title="{{ __('Print A4') }}">
                                            <flux:icon name="printer" size="sm" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center" data-guide="po-empty">
                                <div class="max-w-xs mx-auto space-y-3">
                                    <flux:icon name="document-text" class="size-10 mx-auto text-zinc-400" />
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('No purchase orders found') }}</h3>
                                    <p class="text-xs text-zinc-500">{{ __('Try adjusting search query or filters, or create a new draft purchase order.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-tables.pagination :paginator="$orders" />
    </flux:card>

    <!-- Create / Edit Form Modal -->
    @if(false) {{-- Retained only as inert rollback-compatible markup; create/edit routes are full-page. --}}
    <flux:modal wire:model="showFormModal" class="md:max-w-4xl space-y-6">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                {{ $editingOrderId ? __('Edit Draft Purchase Order') : __('New Purchase Order') }}
            </h2>
            <p class="text-xs text-zinc-500 mt-1">{{ __('Enter supplier, destination store, order dates, and item lines.') }}</p>
        </div>

        <form wire:submit.prevent="saveOrder" class="space-y-6" data-product-line-editor data-autofocus="true">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:select wire:model.live="orderForm.supplier_id" :label="__('Supplier') . ' *'">
                    <option value="">{{ __('Select Supplier') }}</option>
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $sup->name_ar : ($sup->name_en ?: $sup->name_ar) }} ({{ $sup->code }})</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="orderForm.store_id" :label="__('Receiving Store / Warehouse')">
                    <option value="">{{ __('Select Store (Optional)') }}</option>
                    @foreach ($stores as $st)
                        <option value="{{ $st->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $st->name_ar : $st->name_en }} ({{ $st->code }})</option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model="orderForm.order_date" :label="__('Order Date') . ' *'" />
                <flux:input type="date" wire:model="orderForm.expected_delivery_date" :label="__('Expected Delivery Date')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="orderForm.notes" :label="__('Order Notes')" :placeholder="__('Internal procurement reference notes...')" />
            </div>

            <!-- Line Items Editor -->
            <div class="space-y-3 border-t border-border pt-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Order Line Items') }}</h3>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <flux:checkbox wire:model.live="supplierProductsOnly" :label="__('Use supplier products only')" :disabled="blank($orderForm['supplier_id'])" />
                        <flux:button type="button" size="xs" variant="subtle" icon="plus" wire:click="addLine" data-add-line :disabled="blank($orderForm['supplier_id'])">{{ __('Add item') }}</flux:button>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ($lineItems as $index => $item)
                        <div class="grid grid-cols-12 gap-2 items-end bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-border" data-product-line wire:key="order-line-{{ $index }}">
                            <div class="col-span-12 sm:col-span-4">
                                @php($selectedProduct = $products->firstWhere('id', (int) ($item['product_id'] ?? 0)))
                                <x-product-line-lookup wire:model.live="lineItems.{{ $index }}.product_id" :value="$item['product_id'] ?? ''" :display="$selectedProduct ? ((str_starts_with(app()->getLocale(), 'ar') ? $selectedProduct->name_ar : ($selectedProduct->name_en ?: $selectedProduct->name_ar)).' · '.$selectedProduct->item_code) : ''" :required="true" :purchasing="true" :supplier-id="$orderForm['supplier_id']" :supplier-only="$supplierProductsOnly" :disabled="blank($orderForm['supplier_id'])" x-on:product-selected="$wire.selectOrderProduct({{ $index }}, $event.detail.id)" />
                            </div>

                            <div class="col-span-4 sm:col-span-2">
                                <label class="text-xs font-semibold">{{ __('Quantity') }}</label>
                                <input data-line-quantity type="number" step="1" min="1" wire:model.live="lineItems.{{ $index }}.quantity_ordered" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required />
                            </div>

                            <div class="col-span-4 sm:col-span-2">
                                <label class="text-xs font-semibold">{{ __('Unit cost') }}</label>
                                <input data-line-price data-price-kind="cost" type="number" step="0.0001" min="0" wire:model.live="lineItems.{{ $index }}.unit_cost" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required />
                                <p class="mt-1 text-[11px] text-zinc-500" data-price-source>{{ match($item['price_source'] ?? 'none') { 'last_supplier_price' => __('Last supplier price'), 'fallback_cost' => __('Fallback product cost'), 'saved_draft_cost' => __('Saved draft cost'), default => __('No saved supplier price or fallback cost') } }}@if(filled($item['price_date'] ?? '')) · {{ $item['price_date'] }}@endif @if(filled($item['price_currency'] ?? '')) · {{ $item['price_currency'] }}@endif</p>
                            </div>

                            <div class="col-span-3 sm:col-span-2">
                                <span class="block text-xs font-semibold">{{ __('Line total') }}</span>
                                <output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-white px-3 font-mono text-sm dark:bg-zinc-900" data-line-total>0.00</output>
                            </div>

                            <div class="col-span-1 sm:col-span-2 text-end">
                                @if (count($lineItems) > 1)
                                    <flux:button size="xs" variant="ghost" icon="trash" class="text-rose-600" wire:click="removeLine({{ $index }})" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2">
                    <div class="bg-zinc-100 dark:bg-zinc-800 px-4 py-2 rounded-lg text-end">
                        <span class="text-xs text-zinc-500">{{ __('Estimated Subtotal') }}: </span>
                        <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ number_format($formSubtotal, 2) }}</span>
                        <span class="text-xs text-zinc-400 block mt-0.5">{{ __('Tax is not configured') }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <flux:button variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Draft') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
    @endif

    <flux:modal wire:model.self="showSupplierChangeConfirmation" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Change supplier and clear product lines?') }}</flux:heading>
            <flux:callout variant="warning">{{ __('Changing the supplier clears existing product lines and supplier prices so no price is retained for the wrong supplier.') }}</flux:callout>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="subtle" wire:click="cancelSupplierChange">{{ __('Keep current supplier') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="confirmSupplierChange">{{ __('Change supplier and clear lines') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Detail Drawer / Modal -->
    @if ($viewingOrder)
        <flux:modal wire:model="showDetailModal" class="md:max-w-3xl space-y-6">
            <div class="flex items-start justify-between border-b border-border pb-4">
                <div>
                    <h2 class="text-xl font-bold font-mono text-zinc-900 dark:text-white">{{ $viewingOrder->po_number }}</h2>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('Supplier') }}: <strong>{{ str_starts_with(app()->getLocale(), 'ar') ? $viewingOrder->supplier->name_ar : ($viewingOrder->supplier->name_en ?: $viewingOrder->supplier->name_ar) }}</strong>
                    </p>
                </div>
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                        @if($viewingOrder->status === 'draft') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                        @elseif($viewingOrder->status === 'submitted') bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300
                        @elseif($viewingOrder->status === 'partially_received') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300
                        @elseif($viewingOrder->status === 'received') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                        @elseif($viewingOrder->status === 'cancelled') bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300
                        @else bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300 @endif">
                        {{ __($viewingOrder->workflowStatusLabel()) }}
                    </span>
                </div>
            </div>

            <!-- Detail Tabs -->
            <div class="flex border-b border-border gap-4 text-sm font-medium">
                <button wire:click="$set('detailTab', 'items')" class="pb-2 border-b-2 transition @if($detailTab === 'items') border-primary text-primary font-bold @else border-transparent text-zinc-500 hover:text-zinc-700 @endif">
                    {{ __('Items & Totals') }}
                </button>
                <button wire:click="$set('detailTab', 'receipts')" class="pb-2 border-b-2 transition @if($detailTab === 'receipts') border-primary text-primary font-bold @else border-transparent text-zinc-500 hover:text-zinc-700 @endif">
                    {{ __('Goods Receipts & Invoices') }}
                </button>
                <button wire:click="$set('detailTab', 'audit')" class="pb-2 border-b-2 transition @if($detailTab === 'audit') border-primary text-primary font-bold @else border-transparent text-zinc-500 hover:text-zinc-700 @endif">
                    {{ __('Audit History') }}
                </button>
            </div>

            @if ($detailTab === 'items')
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-lg">
                        <div>
                            <span class="text-zinc-500">{{ __('Order Date') }}:</span>
                            <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->order_date?->format('Y-m-d') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500">{{ __('Expected Delivery') }}:</span>
                            <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->expected_delivery_date?->format('Y-m-d') ?: __('Not specified') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500">{{ __('Store') }}:</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->store ? (str_starts_with(app()->getLocale(), 'ar') ? $viewingOrder->store->name_ar : $viewingOrder->store->name_en) : __('Unassigned') }}</span>
                        </div>
                        <div><span class="text-zinc-500">{{ __('Branch') }}:</span><span class="font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->branch ? (str_starts_with(app()->getLocale(),'ar') ? $viewingOrder->branch->name_ar : $viewingOrder->branch->name_en) : '—' }}</span></div>
                    </div>

                    <div class="border border-border rounded-lg overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 font-semibold uppercase text-zinc-600 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 text-start">#</th>
                                    <th class="px-3 py-2 text-start">{{ __('Product') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('Qty') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('Price per selected unit') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('Subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($viewingOrder->lines as $line)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-zinc-400">{{ $line->line_number }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ str_starts_with(app()->getLocale(), 'ar') ? $line->product->name_ar : ($line->product->name_en ?: $line->product->name_ar) }}</div>
                                            <div class="text-[11px] font-mono text-zinc-500">{{ $line->product->sku ?: $line->product->code }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono"><x-product-quantity :value="$line->entered_quantity ?? $line->quantity_ordered" /> {{ $line->unit_code_snapshot }}<div class="text-xs text-text-muted">{{ __('Base quantity') }}: <x-product-quantity :value="$line->quantity_ordered" /></div></td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format((float) ($line->entered_unit_price ?? $line->unit_cost), 4) }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-semibold">{{ number_format((float) $line->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <div class="w-60 space-y-1 text-xs bg-zinc-50 dark:bg-zinc-800/60 p-3 rounded-lg border border-border">
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>{{ __('Subtotal') }}:</span>
                                <span class="font-mono font-semibold">{{ number_format((float)$viewingOrder->subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>{{ __('Tax') }}:</span>
                                <span class="font-mono">{{ number_format((float)$viewingOrder->tax_amount, 2) }}</span>
                            </div>
                            <div class="border-t border-border pt-1 flex justify-between font-bold text-sm text-zinc-900 dark:text-white">
                                <span>{{ __('Total') }}:</span>
                                <span class="font-mono">{{ number_format((float)$viewingOrder->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($viewingOrder->cancel_reason)
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300">
                            <strong>{{ __('Cancellation Reason') }}:</strong> {{ $viewingOrder->cancel_reason }}
                        </div>
                    @endif
                </div>
            @elseif ($detailTab === 'receipts')
                <div class="space-y-3">@forelse($viewingOrder->invoices as $invoice)<div class="flex items-center justify-between rounded-lg border border-border p-3"><div><strong>{{ $invoice->invoice_number ?: '#'.$invoice->id }}</strong><div class="text-xs text-zinc-500">{{ $invoice->invoice_date?->format('Y-m-d') }} · {{ __($invoice->status) }} · {{ $invoice->store?->code }}</div></div><span class="font-mono">{{ number_format((float)$invoice->total_amount,2) }}</span></div>@empty<x-state.empty :title="__('No Goods Receipts or Purchase Invoices')" :description="__('Goods receipts and purchase invoices will appear here after the order is received.')" />@endforelse</div>
            @elseif ($detailTab === 'audit')
                <div class="space-y-3">
                    @forelse ($auditLogs as $log)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/40 border border-border text-xs space-y-1">
                            <div class="flex items-center justify-between font-semibold">
                                <span class="font-mono text-primary">{{ $log->event }}</span>
                                <span class="text-zinc-400 font-mono">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                            </div>
                            <p class="text-zinc-600 dark:text-zinc-300">
                                {{ __('Actor') }}: {{ $log->actor_name ?: __('System') }}
                            </p>
                            @if ($log->reason_text)
                                <p class="text-rose-600 dark:text-rose-400 italic">{{ __('Reason') }}: {{ $log->reason_text }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-zinc-500 text-center py-6">{{ __('No audit log entries recorded for this document.') }}</p>
                    @endforelse
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <flux:button variant="ghost" wire:click="$set('showDetailModal', false)">{{ __('Close') }}</flux:button>
                @if ($canPrint)
                    <form method="POST" action="{{ route('purchasing.orders.pdf.generate', $viewingOrder) }}">@csrf<x-actions.button type="submit" semantic="print" :label="__('Generate stored PDF')" size="sm">{{ __('Generate stored PDF') }}</x-actions.button></form>
                    @foreach($viewingOrder->documents->take(3) as $document)<a href="{{ route('purchasing.orders.pdf.view', [$viewingOrder,$document]) }}" target="_blank" class="text-xs underline">{{ __('View') }} PDF v{{ $document->version }}</a><a href="{{ route('purchasing.orders.pdf.download', [$viewingOrder,$document]) }}" class="text-xs underline">{{ __('Download') }}</a>@endforeach
                    <a href="{{ route('purchasing.orders.print', $viewingOrder->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-800 dark:text-zinc-100 rounded-lg text-xs font-medium transition">
                        🖨️ {{ __('Print A4 Document') }}
                    </a>
                @endif
            </div>
        </flux:modal>
    @endif

    <!-- Cancel Confirmation Modal -->
    <flux:modal wire:model="showCancelModal" class="md:max-w-md space-y-4">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Cancel Purchase Order') }}</h2>
            <p class="text-xs text-zinc-500 mt-1">{{ __('Please provide a reason for cancelling this purchase order.') }}</p>
        </div>

        <form wire:submit.prevent="cancelOrder" class="space-y-4">
            <flux:input wire:model="cancelReason" :label="__('Cancellation Reason') . ' *'" :placeholder="__('e.g. Supplier item out of stock...')" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">{{ __('Back') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Confirm Cancel') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
