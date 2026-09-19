<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\DefaultOperatingContext;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\CancelPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\RejectPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ReversePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Queries\SupplierProductPrice;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Invoices')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $supplierFilter = 'all';

    public string $storeFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showFormModal = false;

    public bool $showTransitionModal = false;

    public ?int $transitionInvoiceId = null;

    public string $transitionType = '';

    public string $transitionReason = '';

    public ?int $editingInvoiceId = null;

    public array $invoiceForm = [
        'supplier_id' => '',
        'store_id' => '',
        'purchase_order_id' => '',
        'supplier_reference' => '',
        'invoice_date' => '',
        'currency_code' => '',
        'notes' => '',
        'lock_version' => 0,
    ];

    public array $lineItems = [];
    public string $itemEntry = '';
    public bool $supplierProductsOnly = true;
    public string $confirmedSupplierId = '';
    public string $pendingSupplierId = '';
    public bool $showSupplierChangeConfirmation = false;
    public string $productSearch = '';
    public int $productSearchRequest = 0;
    public bool $showQuickProduct = false;
    public ?int $quickProductLineIndex = null;
    public array $quickProduct = [
        'barcode_registration_type' => 'international',
        'barcode' => '',
        'model_number' => '',
        'name_ar' => '',
        'name_en' => '',
        'category_id' => '',
        'brand_id' => '',
        'preferred_supplier_id' => '',
        'product_type' => 'standard',
        'status' => 'active',
        'average_cost' => '',
        'sale_price' => '',
        'open_price' => false,
    ];
    public bool $showDistribution = false;
    public array $selectedDestinationIds = [];
    public array $distributionMatrix = [];

    public function mount(): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.view');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $defaultWarehouse = app(DefaultOperatingContext::class)->warehouse($actor);
        $this->storeFilter = $defaultWarehouse === null ? 'all' : (string) $defaultWarehouse->id;
        $this->invoiceForm['invoice_date'] = now()->toDateString();

        if (($purchaseOrderId = request()->integer('purchase_order')) > 0) {
            $this->openReceivingModal($purchaseOrderId);
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

    public function updatedSupplierFilter(): void { $this->resetPage(); }

    public function updatedStoreFilter(): void { $this->resetPage(); }

    public function updatedDateFrom(): void { $this->resetPage(); }

    public function updatedDateTo(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $this->resetValidation();
        $this->editingInvoiceId = null;
        $this->invoiceForm = [
            'supplier_id' => '',
            'store_id' => (string) (app(DefaultOperatingContext::class)->warehouse(Auth::user())?->id ?? ''),
            'purchase_order_id' => '',
            'supplier_reference' => '',
            'invoice_date' => now()->toDateString(),
            'currency_code' => '',
            'notes' => '',
            'lock_version' => 0,
        ];
        $this->lineItems = [$this->emptyLine()];
        $this->confirmedSupplierId = '';
        $this->pendingSupplierId = '';
        $this->supplierProductsOnly = true;
        $this->showFormModal = true;
    }

    public function openReceivingModal(int $purchaseOrderId): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $order = PurchaseOrder::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with(['lines.product', 'lines.productUnit.unit'])
            ->findOrFail($purchaseOrderId);
        abort_unless(in_array($order->status, ['approved', 'partially_received'], true), 422, __('Only approved purchase orders can be received.'));

        $lineItems = $order->lines
            ->map(function ($line): ?array {
                $remainingQuantity = bcsub((string) $line->quantity_ordered, (string) $line->quantity_received, 6);

                if (bccomp($remainingQuantity, '0', 6) <= 0) {
                    return null;
                }

                $factor = (string) ($line->conversion_factor_snapshot ?? '1');
                $enteredQuantity = bcdiv($remainingQuantity, $factor, 6);
                $precision = $line->product?->fractional_quantity ? min(6, (int) ($line->productUnit?->unit?->decimal_places ?? 6)) : 0;
                $useOrderUnit = $line->product_unit_id !== null
                    && bccomp($enteredQuantity, bcadd($enteredQuantity, '0', $precision), 6) === 0
                    && bccomp(bcmul($enteredQuantity, $factor, 6), $remainingQuantity, 6) === 0;

                return [
                    'product_id' => (string) $line->product_id,
                    'product_unit_id' => $useOrderUnit ? (string) $line->product_unit_id : '',
                    'purchase_order_line_id' => (string) $line->id,
                    'quantity' => $useOrderUnit ? $enteredQuantity : $remainingQuantity,
                    'unit_cost' => (string) ($useOrderUnit ? $line->entered_unit_price : $line->unit_cost),
                    'price_source' => 'approved_purchase_order_cost',
                    'price_date' => '',
                    'price_currency' => '',
                    'discount_type' => '',
                    'discount_value' => '0',
                    'tax_rate' => '0',
                    'tax_code' => '',
                ];
            })
            ->filter()
            ->values()
            ->all();

        abort_unless($lineItems !== [], 422, __('This purchase order has no remaining quantity to receive.'));

        $this->resetValidation();
        $this->editingInvoiceId = null;
        $this->invoiceForm = [
            'supplier_id' => (string) $order->supplier_id,
            'store_id' => (string) $order->store_id,
            'purchase_order_id' => (string) $order->id,
            'supplier_reference' => '',
            'invoice_date' => now()->toDateString(),
            'currency_code' => '',
            'notes' => __('Receiving for :number', ['number' => $order->po_number]),
            'lock_version' => 0,
        ];
        $this->lineItems = $lineItems;
        $this->confirmedSupplierId = (string) $order->supplier_id;
        $this->supplierProductsOnly = true;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
        $invoice = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with('lines')
            ->findOrFail($id);
        abort_if($invoice->status !== 'draft', 422, __('Only draft purchase invoices can be edited.'));

        $this->resetValidation();
        $this->editingInvoiceId = $invoice->id;
        $this->invoiceForm = [
            'supplier_id' => (string) $invoice->supplier_id,
            'store_id' => (string) $invoice->store_id,
            'purchase_order_id' => $invoice->purchase_order_id ? (string) $invoice->purchase_order_id : '',
            'supplier_reference' => $invoice->supplier_reference ?: '',
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d') ?: now()->toDateString(),
            'currency_code' => $invoice->currency_code ?: '',
            'notes' => $invoice->notes ?: '',
            'lock_version' => $invoice->lock_version,
        ];
        $this->lineItems = $invoice->lines->map(fn ($line): array => [
            'product_id' => (string) $line->product_id,
            'product_unit_id' => $line->product_unit_id ? (string) $line->product_unit_id : '',
            'purchase_order_line_id' => $line->purchase_order_line_id ? (string) $line->purchase_order_line_id : '',
            'quantity' => (string) ($line->entered_quantity ?? $line->quantity),
            'unit_cost' => (string) ($line->entered_unit_price ?? $line->unit_cost),
            'base_consumer_price' => (string) ($line->base_consumer_price ?? $line->product?->sale_price ?? 0),
            'discount_type' => $line->discount_type ?: '',
            'discount_value' => (string) $line->discount_value,
            'tax_rate' => (string) $line->tax_rate,
            'tax_code' => $line->tax_code ?: '',
            'price_source' => 'saved_draft_cost',
            'price_date' => '',
            'price_currency' => $invoice->currency_code ?: '',
        ])->all();
        $this->lineItems = $this->lineItems ?: [$this->emptyLine()];
        $this->confirmedSupplierId = (string) $invoice->supplier_id;
        $this->supplierProductsOnly = true;
        $this->showFormModal = true;
    }

    public function updatedInvoiceFormSupplierId($supplierId): void
    {
        $supplierId = filled($supplierId) ? (string) $supplierId : '';
        if ($this->confirmedSupplierId !== '' && $supplierId !== $this->confirmedSupplierId && collect($this->lineItems)->contains(fn (array $line): bool => filled($line['product_id'] ?? null))) {
            $this->pendingSupplierId = $supplierId;
            $this->invoiceForm['supplier_id'] = $this->confirmedSupplierId;
            $this->showSupplierChangeConfirmation = true;

            return;
        }
        $this->confirmedSupplierId = $supplierId;
        $this->supplierProductsOnly = $supplierId !== '';
        $this->dispatch('purchasing-product-focus');
    }

    public function confirmSupplierChange(): void
    {
        $this->invoiceForm['supplier_id'] = $this->pendingSupplierId;
        $this->confirmedSupplierId = $this->pendingSupplierId;
        $this->pendingSupplierId = '';
        $this->lineItems = [$this->emptyLine()];
        $this->itemEntry = '';
        $this->invoiceForm['purchase_order_id'] = '';
        $this->supplierProductsOnly = $this->confirmedSupplierId !== '';
        $this->showSupplierChangeConfirmation = false;
        $this->dispatch('purchasing-product-focus');
    }

    public function cancelSupplierChange(): void
    {
        $this->pendingSupplierId = '';
        $this->showSupplierChangeConfirmation = false;
    }

    public function addLine(): void
    {
        if (! filled($this->invoiceForm['supplier_id'])) {
            Flux::toast(__('Select a supplier before adding products.'), variant: 'warning');

            return;
        }
        foreach ($this->lineItems as $index => $line) {
            if (blank($line['product_id'] ?? null)) {
                $this->dispatch('invoice-line-search-focus', index: $index);

                return;
            }
        }

        $this->lineItems[] = $this->emptyLine();
    }

    public function selectInvoiceProduct(int $index, int $productId, SupplierProductPrice $prices): void
    {
        abort_unless(filled($this->invoiceForm['supplier_id']), 422, __('Select a supplier before adding products.'));
        abort_unless(array_key_exists($index, $this->lineItems), 422);
        $product = Product::query()->sellable()->with('productUnits.unit')->findOrFail($productId);
        $price = $prices->resolve($product, (int) $this->invoiceForm['supplier_id'], $this->invoiceForm['currency_code'] ?: null);
        $this->insertProduct($product, $price, $index);
    }

    public function addItemEntry(SupplierProductPrice $prices): void
    {
        if (! filled($this->invoiceForm['supplier_id'])) { Flux::toast(__('Select a supplier before adding products.'), variant: 'warning'); return; }
        $lookup = trim($this->itemEntry);
        if ($lookup === '') { $this->dispatch('invoice-scanner-focus'); return; }
        $product = Product::query()->sellable()
            ->when($this->supplierProductsOnly, fn ($query) => $query->whereHas('productSuppliers', fn ($relations) => $relations->where('supplier_id', (int) $this->invoiceForm['supplier_id'])))
            ->where(function ($query) use ($lookup): void {
            $query->where('item_code', $lookup)->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', $lookup)->where('status', 'active'));
        })->first();
        if (! $product) { $this->openQuickProduct($lookup); return; }
        $this->insertProduct($product, $prices->resolve($product, (int) $this->invoiceForm['supplier_id'], $this->invoiceForm['currency_code'] ?: null));
    }

    public function selectSearchProduct(int $productId, SupplierProductPrice $prices): void
    {
        abort_unless(filled($this->invoiceForm['supplier_id']), 422, __('Select a supplier before adding products.'));
        $product = Product::query()->sellable()->with('productUnits.unit')->findOrFail($productId);
        $price = $prices->resolve($product, (int) $this->invoiceForm['supplier_id'], $this->invoiceForm['currency_code'] ?: null);
        $this->insertProduct($product, $price);
        $this->productSearch = '';
    }

    public function updatedProductSearch(): void
    {
        $this->productSearchRequest++;
    }

    public function searchProductsNow(string $term): void
    {
        $this->productSearch = trim($term);
        $this->productSearchRequest++;
    }

    public function openQuickProduct(string $barcode = '', ?int $lineIndex = null): void
    {
        Gate::authorize('products_categories_brands.create');
        if (! filled($this->invoiceForm['supplier_id'])) {
            Flux::toast(__('Select a supplier before adding products.'), variant: 'warning');

            return;
        }
        $this->resetValidation();
        $this->quickProductLineIndex = $lineIndex;
        $this->quickProduct = [
            'barcode_registration_type' => 'international',
            'barcode' => trim($barcode !== '' ? $barcode : $this->itemEntry),
            'model_number' => '',
            'name_ar' => '',
            'name_en' => '',
            'category_id' => '',
            'brand_id' => '',
            'preferred_supplier_id' => (string) ($this->invoiceForm['supplier_id'] ?: ''),
            'product_type' => 'standard',
            'status' => 'active',
            'average_cost' => '',
            'sale_price' => '',
            'open_price' => false,
        ];
        $this->showQuickProduct = true;
    }

    public function closeQuickProduct(): void
    {
        $this->showQuickProduct = false;
        $this->dispatch('invoice-line-search-focus', index: $this->quickProductLineIndex);
        $this->quickProductLineIndex = null;
    }

    /** @param array{unit_cost: string|null, price_source: string, price_date: string|null, price_currency: string|null}|null $price */
    private function insertProduct(Product $product, ?array $price = null, ?int $targetIndex = null): void
    {
        foreach ($this->lineItems as $index => $line) {
            if ($index !== $targetIndex && (int) ($line['product_id'] ?? 0) === $product->id) {
                $this->lineItems[$index]['quantity'] = bcadd((string) $line['quantity'], '1', 6);
                if ($targetIndex !== null && blank($this->lineItems[$targetIndex]['product_id'] ?? null) && count($this->lineItems) > 1) {
                    array_splice($this->lineItems, $targetIndex, 1);
                    $index -= $targetIndex < $index ? 1 : 0;
                }
                $this->itemEntry='';
                $this->dispatch('invoice-line-focus', index: $index);
                return;
            }
        }
        $price ??= app(SupplierProductPrice::class)->resolve($product, (int) $this->invoiceForm['supplier_id'], $this->invoiceForm['currency_code'] ?: null);
        $product->loadMissing('productUnits.unit');
        $purchaseUnit = $product->productUnits->firstWhere('is_purchase_unit', true) ?? $product->productUnits->firstWhere('is_base_unit', true);
        $productLine = [...$this->emptyLine(), 'product_id'=>(string)$product->id, 'product_unit_id'=>$purchaseUnit ? (string)$purchaseUnit->id : '', 'unit_cost'=>$price['unit_cost'] ?? '', 'base_consumer_price'=>(string)($product->sale_price ?? 0), 'price_source'=>$price['price_source'], 'price_date'=>$price['price_date'] ?? '', 'price_currency'=>$price['price_currency'] ?? ''];
        if ($targetIndex !== null && array_key_exists($targetIndex, $this->lineItems)) {
            $this->lineItems[$targetIndex] = [...$this->lineItems[$targetIndex], ...$productLine];
        } else {
            if (count($this->lineItems) === 1 && empty($this->lineItems[0]['product_id'])) $this->lineItems=[];
            $this->lineItems[] = $productLine;
            $targetIndex = array_key_last($this->lineItems);
        }
        $this->itemEntry='';
        $this->dispatch('invoice-line-focus', index: $targetIndex);
    }

    public function createQuickProduct(SaveProductAction $save, AddBarcodeAction $barcodes): void
    {
        Gate::authorize('products_categories_brands.create');

        try {
            $validated = $this->validate([
                'quickProduct.barcode_registration_type' => ['required', Rule::in(['international', 'local'])],
                'quickProduct.barcode' => ['required', 'string', 'max:64', 'regex:/^\S+$/', Rule::unique('products', 'item_code'), Rule::unique('barcodes', 'barcode')],
                'quickProduct.model_number' => ['required', 'string', 'max:100'],
                'quickProduct.name_ar' => ['required', 'string', 'max:255'],
                'quickProduct.name_en' => ['nullable', 'string', 'max:255'],
                'quickProduct.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', 'active')],
                'quickProduct.brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('status', 'active')],
                'quickProduct.preferred_supplier_id' => [Rule::requiredIf(fn (): bool => $this->quickProduct['barcode_registration_type'] === 'local'), 'nullable', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')],
                'quickProduct.product_type' => ['required', Rule::in(['standard', 'composite', 'service'])],
                'quickProduct.status' => ['required', Rule::in(['active', 'inactive'])],
                'quickProduct.average_cost' => ['required', 'numeric', 'min:0'],
                'quickProduct.sale_price' => ['required', 'numeric', 'min:0.01'],
                'quickProduct.open_price' => ['required', 'boolean'],
            ], [], [
                'quickProduct.barcode_registration_type' => __('Barcode registration type'),
                'quickProduct.barcode' => __('Barcode / SKU'),
                'quickProduct.model_number' => __('Model number'),
                'quickProduct.name_ar' => __('Arabic product name'),
                'quickProduct.name_en' => __('English product name'),
                'quickProduct.category_id' => __('Category / subcategory'),
                'quickProduct.brand_id' => __('Brand'),
                'quickProduct.preferred_supplier_id' => __('Supplier'),
                'quickProduct.product_type' => __('Product type'),
                'quickProduct.status' => __('Status'),
                'quickProduct.average_cost' => __('Cost price'),
                'quickProduct.sale_price' => __('Base consumer selling price'),
                'quickProduct.open_price' => __('Open-price product'),
            ])['quickProduct'];

            $lookup = trim((string) $validated['barcode']);
            $isInternationalBarcode = $validated['barcode_registration_type'] === 'international'
                && preg_match('/^\d{8}$|^\d{12,14}$/', $lookup) === 1;
            if ($isInternationalBarcode) {
                app(\App\Modules\Catalog\Support\ProductBarcodePolicy::class)->international($lookup);
            }

            $product = DB::transaction(function () use ($save, $barcodes, $validated, $lookup, $isInternationalBarcode): Product {
                $product = $save->execute([
                    ...$validated,
                    'item_code' => $isInternationalBarcode ? '' : strtoupper($lookup),
                    'preferred_supplier_id' => $validated['preferred_supplier_id'] ?: null,
                ]);

                if ($validated['barcode_registration_type'] === 'local') {
                    $supplier = Supplier::query()->findOrFail((int) $validated['preferred_supplier_id']);
                    $barcodes->allocateLocalBarcode($product->id, $supplier->code, 'purchase-invoice-quick-product:'.$product->id);
                } elseif ($isInternationalBarcode) {
                    $barcodes->addSupplierBarcode($product->id, $lookup);
                }

                return $product->fresh();
            });

            $this->showQuickProduct = false;
            $targetIndex = $this->quickProductLineIndex;
            $this->quickProductLineIndex = null;
            $this->insertProduct($product, null, $targetIndex);
            Flux::toast(__('Product card created and added to the draft invoice.'), variant: 'success');
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
        } catch (Throwable $exception) {
            $this->addError('quickProduct.barcode', \App\Support\UserSafeError::message($exception));
        }
    }

    public function removeLine(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function saveInvoice(SavePurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices.draft');
        $this->validate([
            'invoiceForm.supplier_id' => 'required|exists:suppliers,id',
            'invoiceForm.store_id' => 'required|exists:stores,id',
            'invoiceForm.purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'invoiceForm.supplier_reference' => 'nullable|string|max:100',
            'invoiceForm.invoice_date' => 'required|date',
            'invoiceForm.currency_code' => 'nullable|string|size:3',
            'invoiceForm.notes' => 'nullable|string|max:5000',
            'lineItems' => 'required|array|min:1',
            'lineItems.*.product_id' => 'required|exists:products,id',
            'lineItems.*.product_unit_id' => 'nullable|exists:product_units,id',
            'lineItems.*.quantity' => 'required|decimal:0,6|gt:0',
            'lineItems.*.unit_cost' => 'required|numeric|gte:0',
            'lineItems.*.base_consumer_price' => 'required|numeric|gte:0',
            'lineItems.*.discount_type' => 'nullable|in:percentage,amount',
            'lineItems.*.discount_value' => 'nullable|numeric|gte:0',
            'lineItems.*.tax_rate' => 'nullable|numeric|between:0,100',
        ]);

        try {
            $invoice = $action->execute(
                data: $this->invoiceForm,
                lines: $this->lineItems,
                id: $this->editingInvoiceId,
                expectedVersion: $this->editingInvoiceId ? (int) $this->invoiceForm['lock_version'] : null,
            );
            $this->showFormModal = false;
            Flux::toast($this->editingInvoiceId ? __('Purchase invoice updated.') : __('Purchase invoice saved as draft.'), variant: 'success');
            $this->editingInvoiceId = $invoice->id;
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function submitInvoice(int $id, SubmitPurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');
        try {
            $invoice = PurchaseInvoice::query()->findOrFail($id);
            $action->execute($invoice->id, $invoice->lock_version);
            $this->openDistribution($invoice->id);
            Flux::toast(__('Purchase invoice submitted for approval.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function openDistribution(int $id): void
    {
        Gate::authorize('purchase_invoices.distribute');
        $invoice=PurchaseInvoice::query()->with(['lines.product','distributions'])->findOrFail($id);
        $this->editingInvoiceId=$invoice->id; $this->invoiceForm['lock_version']=$invoice->lock_version;
        $this->selectedDestinationIds=$invoice->distributions->pluck('destination_store_id')->unique()->map(fn($id)=>(string)$id)->values()->all();
        $this->distributionMatrix=[];
        foreach($invoice->distributions as $allocation) $this->distributionMatrix[$allocation->purchase_invoice_line_id][$allocation->destination_store_id]=(string)$allocation->quantity;
        $this->showDistribution=true;
    }

    public function saveDistribution(SavePurchaseDistributionAction $action): void
    {
        try { $invoice=$action->execute((int)$this->editingInvoiceId,$this->distributionMatrix,(int)$this->invoiceForm['lock_version']); $this->invoiceForm['lock_version']=$invoice->lock_version; Flux::toast(__('Distribution saved. Every line must balance before approval.'),variant:'success'); }
        catch(Throwable $e){ Flux::toast(\App\Support\UserSafeError::message($e),variant:'danger'); }
    }

    public function approveInvoice(int $id, ApprovePurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices.approve');
        try {
            $invoice = PurchaseInvoice::query()->findOrFail($id);
            $action->execute($invoice->id, $invoice->lock_version);
            Flux::toast(__('Purchase invoice approved and stock/WAC posted.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function openTransitionModal(string $type, int $id): void
    {
        Gate::authorize(match ($type) {
            'reject' => 'purchase_invoices.approve',
            'cancel' => 'purchase_invoices_supplier_returns.cancel',
            'reverse' => 'purchase_invoices.reverse',
            default => throw new InvalidArgumentException(__('Unsupported invoice transition.')),
        });
        $this->transitionType = $type;
        $this->transitionInvoiceId = $id;
        $this->transitionReason = '';
        $this->resetValidation();
        $this->showTransitionModal = true;
    }

    public function executeTransition(RejectPurchaseInvoiceAction $reject, CancelPurchaseInvoiceAction $cancel, ReversePurchaseInvoiceAction $reverse): void
    {
        $this->validate(['transitionReason' => 'required|string|min:3|max:500']);
        $invoice = PurchaseInvoice::query()->findOrFail($this->transitionInvoiceId);
        try {
            match ($this->transitionType) {
                'reject' => $reject->execute($invoice->id, $this->transitionReason, $invoice->lock_version),
                'cancel' => $cancel->execute($invoice->id, $this->transitionReason, $invoice->lock_version),
                'reverse' => $reverse->execute($invoice->id, $this->transitionReason, $invoice->lock_version),
                default => throw new InvalidArgumentException(__('Unsupported invoice transition.')),
            };
            $this->showTransitionModal = false;
            Flux::toast(__('Invoice transition completed.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function render(): View
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');

        $invoices = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with(['supplier', 'store', 'lines.distributions', 'distributions.destination'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('supplier_reference', 'like', '%'.$this->search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->supplierFilter !== 'all', fn ($query) => $query->where('supplier_id', (int) $this->supplierFilter))
            ->when($this->storeFilter !== 'all', fn ($query) => $query->where('store_id', (int) $this->storeFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('invoice_date', '<=', $this->dateTo))
            ->latest()
            ->paginate(20);

        $stores = Store::query()->visibleTo($actor)->where('status', 'active')->orderBy('name_en')->get();

        return view('purchasing.invoices', [
            'invoices' => $invoices,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name_en')->get(),
            'stores' => $stores,
            'receivingStores' => $stores->where('type', 'warehouse')->values(),
            'productResults' => $this->searchProducts($actor),
            'lineProducts' => Product::query()->with('productUnits.unit')->whereIn('id', collect($this->lineItems)->pluck('product_id')->filter()->map(fn($id)=>(int)$id))->get()->keyBy('id'),
            'categories' => Category::query()->where('status','active')->orderBy('name_en')->get(),
            'brands' => Brand::query()->where('status','active')->orderBy('name_en')->get(),
            'distributionInvoice' => $this->showDistribution && $this->editingInvoiceId ? PurchaseInvoice::query()->with(['lines.product','distributions.destination'])->find($this->editingInvoiceId) : null,
        ]);
    }

    public function formatQuantity(string|int|float|null $quantity): string
    {
        return \App\Support\ProductQuantity::format($quantity);
    }

    private function searchProducts(User $actor): \Illuminate\Support\Collection
    {
        $term = trim($this->productSearch);
        if (mb_strlen($term) < 3) return collect();

        $companyId = Store::query()->visibleTo($actor)
            ->when(filled($this->invoiceForm['store_id']), fn ($query) => $query->whereKey((int) $this->invoiceForm['store_id']))
            ->where('status', 'active')->value('company_id');
        if (! $companyId) return collect();

        $escaped = addcslashes($term, '\\%_');
        $prefix = $escaped.'%';
        $contained = '%'.$escaped.'%';
        $tokens = collect(preg_split('/\s+/u', $term) ?: [])->filter()->take(4);

        return Product::query()->sellable()
            ->with(['barcodes' => fn ($query) => $query->where('status', 'active')->orderByDesc('is_primary')])
            ->where(function ($query) use ($term, $prefix, $contained, $tokens): void {
                $query->where('item_code', $term)->orWhere('model_number', $term)
                    ->orWhere('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)
                    ->orWhere('item_code', 'like', $contained)->orWhere('model_number', 'like', $contained)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('status', 'active')->where(function ($query) use ($term, $prefix, $contained): void {
                        $query->where('barcode', $term)->orWhere('barcode', 'like', $prefix)->orWhere('barcode', 'like', $contained);
                    }))
                    ->orWhere(function ($names) use ($tokens): void {
                        foreach ($tokens as $token) {
                            $escapedToken = addcslashes((string) $token, '\\%_');
                            $names->orWhere('name_ar', 'like', $escapedToken.'%')->orWhere('name_ar', 'like', '% '.$escapedToken.'%')
                                ->orWhere('name_en', 'like', $escapedToken.'%')->orWhere('name_en', 'like', '% '.$escapedToken.'%');
                        }
                    });
            })
            ->orderByRaw('CASE WHEN item_code = ? OR model_number = ? OR EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.status = ? AND barcodes.barcode = ?) THEN 0 WHEN item_code LIKE ? OR model_number LIKE ? OR EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.status = ? AND barcodes.barcode LIKE ?) THEN 1 ELSE 2 END', [$term,$term,'active',$term,$prefix,$prefix,'active',$prefix])
            ->orderBy('item_code')->limit(20)->get();
    }

    /** @return array<string, string> */
    private function emptyLine(): array
    {
        return [
            'product_id' => '',
            'product_unit_id' => '',
            'purchase_order_line_id' => '',
            'quantity' => '1',
            'unit_cost' => '0',
            'base_consumer_price' => '0',
            'discount_type' => '',
            'discount_value' => '0',
            'tax_rate' => '0',
            'tax_code' => '',
            'price_source' => 'none',
            'price_date' => '',
            'price_currency' => '',
        ];
    }
};
?>

<x-app.page
    :title="str_starts_with(app()->getLocale(), 'ar') ? 'فواتير المشتريات' : __('Purchase invoices')"
    :description="str_starts_with(app()->getLocale(), 'ar') ? 'راجع فواتير الموردين والاستلام والاعتماد والترحيل ضمن نطاقك.' : __('Review supplier invoices, receiving, approvals, and posting within your scope.')"
    :breadcrumbs="str_starts_with(app()->getLocale(), 'ar') ? 'المشتريات والموردون' : 'Purchasing & suppliers'"
    max-width="7xl"
    class="space-y-6"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="purchase-invoices-filters">
        <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="shield-check">{{ __('Invoice availability') }}</flux:button>
        @can('purchase_invoices_supplier_returns.create')
            <flux:button href="{{ route('purchasing.invoices.import') }}" variant="subtle" icon="arrow-up-tray">{{ __('Import') }}</flux:button>
        @endcan
        @can('purchase_returns.view')
            <flux:button href="{{ route('purchasing.returns') }}" variant="subtle" icon="arrow-uturn-left">{{ __('Supplier returns') }}</flux:button>
        @endcan
        @can('purchase_invoices_supplier_returns.export')
        @endcan
        @can('purchase_invoices_supplier_returns.create')
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">{{ __('New draft invoice') }}</flux:button>
        @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    @unless($showFormModal || $showDistribution)
    <flux:card id="purchase-invoices-filters" class="scroll-mt-24">
        <div class="grid gap-3 sm:grid-cols-2 {{ $receivingStores->count() > 1 ? 'xl:grid-cols-6' : 'xl:grid-cols-5' }}">
            <div class="min-w-64 flex-1">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Invoice number or supplier reference') }}" />
            </div>
            <div>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
                    <flux:select.option value="awaiting_distribution">{{ __('Awaiting Distribution') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                    <flux:select.option value="reversed">{{ __('Reversed') }}</flux:select.option>
                    <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                </flux:select>
            </div>
            <flux:select wire:model.live="supplierFilter" :label="__('Supplier')"><flux:select.option value="all">{{ __('All') }}</flux:select.option>@foreach($suppliers as $supplier)<flux:select.option value="{{ $supplier->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->name_ar ?: '—') : ($supplier->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
            @if($receivingStores->count() > 1)<flux:select wire:model.live="storeFilter" :label="__('Store')"><flux:select.option value="all">{{ __('All') }}</flux:select.option>@foreach($receivingStores as $store)<flux:select.option value="{{ $store->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? ($store->name_ar ?: '—') : ($store->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>@endif
            <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
            <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />
        </div>
    </flux:card>

    <div class="space-y-4" aria-label="{{ __('Purchase invoices') }}">
        @forelse ($invoices as $invoice)
            @php($remaining=$invoice->lines->reduce(fn($total,$line)=>bcadd($total,bcsub((string)$line->quantity,$line->distributions->reduce(fn($allocated,$distribution)=>bcadd($allocated,(string)$distribution->quantity,6),'0'),6),6),'0'))
            @php($fullyDistributed=$invoice->lines->isNotEmpty()&&bccomp($remaining,'0',6)===0)
            @php($stageOneComplete=$invoice->lines->isNotEmpty())
            @php($stage=$invoice->status==='draft' ? __('Stage 1') : __('Stage 2'))
            @php($nextAction=match($invoice->status){'draft'=>$stageOneComplete?__('Review and distribute quantities'):__('Complete invoice'),'submitted','awaiting_distribution'=>$fullyDistributed?__('Approve and post invoice'):__('Review and distribute quantities'),default=>__('No further action')})
            <flux:card class="overflow-hidden" wire:key="invoice-card-{{ $invoice->id }}">
                <div class="grid min-w-0 gap-5 lg:grid-cols-[minmax(11rem,1.25fr)_minmax(10rem,1fr)_minmax(9rem,.8fr)_minmax(13rem,1.35fr)] lg:items-start">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="truncate" dir="ltr">{{ $invoice->invoice_number ?: $invoice->supplier_reference ?: '#'.$invoice->id }}</strong>
                            <x-status.badge :status="$invoice->status" />
                        </div>
                        @if(filled($invoice->supplier_reference))<div class="truncate text-xs text-text-muted" dir="ltr">{{ $invoice->supplier_reference }}</div>@endif
                        <div class="text-sm">{{ str_starts_with(app()->getLocale(), 'ar') ? ($invoice->supplier?->name_ar ?: '—') : ($invoice->supplier?->name_en ?: '—') }}</div>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm lg:grid-cols-1">
                        <div><dt class="text-xs text-text-muted">{{ __('Store') }}</dt><dd>{{ str_starts_with(app()->getLocale(), 'ar') ? ($invoice->store?->name_ar ?: '—') : ($invoice->store?->name_en ?: '—') }}</dd></div>
                        <div><dt class="text-xs text-text-muted">{{ __('Date') }}</dt><dd dir="ltr">{{ $invoice->invoice_date?->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-xs text-text-muted">{{ __('Total') }}</dt><dd><x-money :amount="$invoice->total_amount" :currency="$invoice->currency_code" /></dd></div>
                    </dl>
                    <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-1">
                        <div><dt class="text-xs text-text-muted">{{ __('Current stage') }}</dt><dd class="font-medium">{{ $stage }}</dd></div>
                        <div><dt class="text-xs text-text-muted">{{ __('Remaining quantity') }}</dt><dd class="tabular-nums" dir="ltr">{{ __(':quantity units', ['quantity'=>$this->formatQuantity($remaining)]) }}</dd></div>
                    </dl>
                    <div class="min-w-0 space-y-3 lg:border-s lg:ps-5">
                        <div><div class="text-xs text-text-muted">{{ __('Next required action') }}</div><div class="font-medium">{{ $nextAction }}</div></div>
                        <div class="flex flex-wrap gap-2">
                            @can('purchase_invoices_supplier_returns.print')
                                <x-actions.button semantic="print" :label="__('Print')" :href="route('purchasing.invoices.print', $invoice)" target="_blank">{{ __('Print') }}</x-actions.button>
                            @endcan
                            @if ($invoice->status === 'draft')
                                <div class="flex flex-wrap gap-2">
                                    @if($stageOneComplete)
                                        @can('purchase_invoices_supplier_returns.edit')
                                            <flux:button size="sm" variant="primary" wire:click="submitInvoice({{ $invoice->id }})">{{ __('Review and distribute quantities') }}</flux:button>
                                        @else
                                            <flux:button size="sm" disabled title="{{__('You do not have permission to complete Stage 1.')}}">{{ __('Review and distribute quantities') }}</flux:button>
                                        @endcan
                                        <flux:button size="sm" variant="subtle" wire:click="openEditModal({{ $invoice->id }})">{{ __('Edit') }}</flux:button>
                                    @else
                                        <flux:button variant="primary" wire:click="openEditModal({{ $invoice->id }})">{{ __('Complete invoice') }}</flux:button>
                                    @endif
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif (in_array($invoice->status, ['submitted','awaiting_distribution'], true))
                                <div class="flex flex-wrap gap-2">
                                    @if($fullyDistributed)
                                        @can('purchase_invoices.approve')<flux:button size="sm" variant="primary" wire:click="approveInvoice({{ $invoice->id }})">{{ __('Approve and post invoice') }}</flux:button>@else<flux:button size="sm" disabled title="{{__('You do not have permission to approve and post this invoice.')}}">{{ __('Approve and post invoice') }}</flux:button>@endcan
                                    @else
                                        @can('purchase_invoices.distribute')<flux:button size="sm" variant="primary" wire:click="openDistribution({{ $invoice->id }})">{{ __('Review and distribute quantities') }}</flux:button>@else<flux:button size="sm" disabled title="{{__('You do not have permission to distribute this invoice.')}}">{{ __('Review and distribute quantities') }}</flux:button>@endcan
                                    @endif
                                    @can('purchase_invoices.approve')
                                        <x-actions.button semantic="reject" :label="__('Reject')" wire:click="openTransitionModal('reject', {{ $invoice->id }})">{{ __('Reject') }}</x-actions.button>
                                    @endcan
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif ($invoice->status === 'approved')
                                @can('purchase_invoices.print_labels')
                                    @foreach($invoice->distributions->whereNotNull('effective_selling_price')->unique('destination_store_id') as $allocation)
                                        <x-actions.button semantic="print" :label="__('Print labels')" :href="route('purchasing.invoices.destination-labels',[$invoice,$allocation->destination_store_id])">{{ __('Labels') }} · {{ str_starts_with(app()->getLocale(), 'ar')?$allocation->destination?->name_ar:($allocation->destination?->name_en?:$allocation->destination?->name_ar) }}</x-actions.button>
                                    @endforeach
                                @endcan
                                @can('purchase_invoices.reverse')
                                    <flux:button size="sm" variant="danger" wire:click="openTransitionModal('reverse', {{ $invoice->id }})">{{ __('Reverse') }}</flux:button>
                                @endcan
                            @else
                                <flux:text title="{{ __('This invoice is locked because its state is final.') }}">{{ __('Locked') }}</flux:text>
                            @endif
                        </div>
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:card><div class="flex flex-col items-center gap-2 py-10 text-center"><flux:icon name="document-text" class="size-8 text-text-muted" /><flux:heading size="sm">{{ __('No purchase invoices yet.') }}</flux:heading><flux:text>{{ __('Create a draft invoice to begin reviewing purchases.') }}</flux:text></div></flux:card>
        @endforelse
        <x-tables.pagination :paginator="$invoices" />
    </div>
    @endunless

    @if ($showFormModal)
        <flux:card class="space-y-3 p-3 sm:p-4" aria-label="{{ __('Stage 1 — Purchase invoice') }}" data-purchase-invoice-editor>
            <form wire:submit.prevent="saveInvoice" class="space-y-4" data-product-line-editor data-autofocus="true">
                <div class="flex flex-wrap items-baseline justify-between gap-2"><flux:heading size="lg">{{ __('Stage 1 — Purchase invoice') }}</flux:heading><flux:text class="text-xs">{{ __('Scan or search products, then save the invoice safely as a draft.') }}</flux:text></div>
                @if (filled($invoiceForm['purchase_order_id']))
                    <input type="hidden" wire:model="invoiceForm.purchase_order_id" />
                    <flux:callout icon="inbox-arrow-down" variant="info">
                        {{ __('Receiving purchase order :id', ['id' => $invoiceForm['purchase_order_id']]) }}
                    </flux:callout>
                @endif
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(13rem,1.4fr)_minmax(13rem,1.35fr)_10.5rem_minmax(12rem,1fr)_7rem]" data-invoice-metadata>
                    <flux:select wire:model.live="invoiceForm.supplier_id" :label="__('Supplier')" :disabled="filled($invoiceForm['purchase_order_id'])">
                        <flux:select.option value="">{{ __('Select supplier') }}</flux:select.option>
                        @foreach ($suppliers as $supplier)
                            <flux:select.option value="{{ $supplier->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->name_ar ?: '—') : ($supplier->name_en ?: '—') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if($receivingStores->count() === 1)
                        <input type="hidden" wire:model="invoiceForm.store_id" />
                        <div><span class="text-xs font-semibold text-text-muted">{{ __('Receiving store') }}</span><strong class="mt-1 flex h-10 items-center rounded-lg bg-surface-muted px-3 text-sm">{{ str_starts_with(app()->getLocale(), 'ar') ? $receivingStores->first()->name_ar : $receivingStores->first()->name_en }}</strong></div>
                    @else
                        <flux:select wire:model="invoiceForm.store_id" :label="__('Receiving store')"><flux:select.option value="">{{ __('Select store') }}</flux:select.option>@foreach ($receivingStores as $store)<flux:select.option value="{{ $store->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? ($store->name_ar ?: '—') : ($store->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
                    @endif
                    <flux:input wire:model="invoiceForm.invoice_date" type="date" :label="__('Invoice date')" />
                    <flux:input wire:model="invoiceForm.supplier_reference" :label="__('Supplier invoice reference')" />
                    <flux:input wire:model="invoiceForm.currency_code" maxlength="3" :label="__('Currency code')" placeholder="{{ __('Optional') }}" />
                </div>

                <div class="space-y-2 border-t border-border pt-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <flux:heading size="base">{{ __('Invoice lines') }}</flux:heading>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:checkbox wire:model.live="supplierProductsOnly" :label="__('Use supplier products only')" :disabled="blank($invoiceForm['supplier_id'])" />
                            <flux:button type="button" size="xs" variant="subtle" icon="plus" wire:click="addLine" data-add-line :disabled="blank($invoiceForm['supplier_id'])">{{ __('Add item') }}</flux:button>
                            @can('products_categories_brands.create')
                                <flux:button type="button" wire:click="openQuickProduct" size="xs" variant="primary" icon="plus" :disabled="blank($invoiceForm['supplier_id'])">{{ __('Create product') }}</flux:button>
                            @endcan
                        </div>
                    </div>

                    <div class="max-h-[32rem] overflow-y-auto rounded-lg border border-border" data-invoice-lines-scroll>
                        <div class="sticky top-0 z-20 hidden grid-cols-[minmax(14rem,1fr)_4.5rem_6.5rem_6.5rem_6.5rem_5rem_4.5rem_6rem_2.5rem] gap-1.5 border-b border-border bg-zinc-100 px-2 py-1.5 text-[10px] font-semibold text-zinc-600 lg:grid dark:bg-zinc-800 dark:text-zinc-300" aria-hidden="true">
                            <span>{{ __('Product') }}</span><span>{{ __('Quantity') }}</span><span>{{ __('Unit cost') }}</span><span>{{ __('Base Consumer Price List 0') }}</span><span>{{ __('Discount type') }}</span><span>{{ __('Discount') }}</span><span>{{ __('Tax %') }}</span><span>{{ __('Line total') }}</span><span></span>
                        </div>
                        <div class="space-y-1.5 p-1.5">
                            @foreach ($lineItems as $index => $line)
                            <div class="grid grid-cols-12 items-start gap-1.5 rounded-md bg-zinc-50 p-2 lg:grid-cols-[minmax(14rem,1fr)_4.5rem_6.5rem_6.5rem_6.5rem_5rem_4.5rem_6rem_2.5rem] dark:bg-zinc-800/40" wire:key="invoice-line-{{ $index }}" data-product-line data-product-value="{{ $line['product_id'] ?? '' }}">
                                <div class="col-span-12 min-w-0 lg:col-auto">
                                    @php($lineProduct=$lineProducts->get((int)($line['product_id'] ?? 0)))
                                    <x-product-line-lookup wire:model.live="lineItems.{{ $index }}.product_id" :value="$line['product_id'] ?? ''" :display="$lineProduct ? ((str_starts_with(app()->getLocale(), 'ar') ? ($lineProduct->name_ar ?: $lineProduct->name_en) : ($lineProduct->name_en ?: $lineProduct->name_ar)).' · '.$lineProduct->item_code) : ''" :required="true" :purchasing="true" :supplier-id="$invoiceForm['supplier_id']" :supplier-only="$supplierProductsOnly" :currency-code="$invoiceForm['currency_code']" :disabled="blank($invoiceForm['supplier_id'])" x-on:product-selected="$wire.selectInvoiceProduct({{ $index }}, $event.detail.id)" x-on:product-lookup-empty="$wire.openQuickProduct($event.detail.term, {{ $index }})" x-on:invoice-line-search-focus.window="if($event.detail.index==={{ $index }})$nextTick(()=>$el.querySelector('[data-product-search]')?.focus())" />
                                </div>
                                @if($lineProduct?->productUnits->isNotEmpty())
                                    <div class="col-span-4 sm:col-span-2 lg:col-auto">
                                        <label class="text-xs font-semibold lg:sr-only">{{ __('Unit') }}</label>
                                        <select wire:model="lineItems.{{ $index }}.product_unit_id" class="mt-1 h-10 w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                                            @foreach($lineProduct->productUnits as $productUnit)
                                                <option value="{{ $productUnit->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $productUnit->unit->name_ar : $productUnit->unit->name_en }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-span-4 sm:col-span-2 lg:col-auto" x-on:invoice-line-focus.window="if($event.detail.index==={{$index}})$nextTick(()=>$el.querySelector('input')?.focus())"><label class="text-xs font-semibold lg:sr-only">{{ __('Quantity') }}</label><input wire:model="lineItems.{{ $index }}.quantity" data-line-quantity type="number" min="0.000001" step="any" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required /></div>
                                <div class="col-span-4 min-w-0 sm:col-span-2 lg:col-auto"><label class="text-xs font-semibold lg:sr-only">{{ __('Unit cost') }}</label><input wire:model="lineItems.{{ $index }}.unit_cost" wire:change="$set('lineItems.{{ $index }}.price_source','manual_authorized_cost')" data-line-price data-price-kind="cost" type="number" min="0" step="0.0001" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" @disabled(!Gate::allows('purchase_invoices.change_cost')) required /><p class="mt-0.5 truncate text-[9px] leading-none text-zinc-500" data-price-source>{{ match($line['price_source'] ?? 'none') { 'last_supplier_price' => __('Last supplier price'), 'fallback_cost' => __('Fallback product cost'), 'saved_draft_cost' => __('Saved draft cost'), 'approved_purchase_order_cost' => __('Approved purchase-order cost'), 'manual_authorized_cost' => __('Manually authorized cost'), default => __('No saved supplier price or fallback cost') } }}@if(filled($line['price_date'] ?? '')) · {{ $line['price_date'] }}@endif @if(filled($line['price_currency'] ?? '')) · {{ $line['price_currency'] }}@endif</p></div>
                                <div class="col-span-4 sm:col-span-2 lg:col-auto"><label class="text-xs font-semibold lg:sr-only">{{ __('Base Consumer Price List 0') }}</label><input wire:model="lineItems.{{ $index }}.base_consumer_price" data-line-step type="number" min="0" step="0.001" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" @disabled(!Gate::allows('purchase_invoices.change_list0_price')) required /></div>
                                <div class="col-span-6 sm:col-span-3 lg:col-auto"><label class="text-xs font-semibold lg:sr-only">{{ __('Discount type') }}</label><select wire:model.live="lineItems.{{ $index }}.discount_type" data-line-discount-type data-line-step class="mt-1 h-10 w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="">{{ __('None') }}</option><option value="percentage">{{ __('Percentage') }}</option><option value="amount">{{ __('Amount') }}</option></select></div>
                                <div class="col-span-3 sm:col-span-2 lg:col-auto"><label class="text-xs font-semibold lg:sr-only">{{ __('Discount') }}</label><input wire:model="lineItems.{{ $index }}.discount_value" data-line-discount data-line-step type="number" min="0" step="0.0001" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" /></div>
                                <div class="col-span-3 sm:col-span-2 lg:col-auto"><label class="text-xs font-semibold lg:sr-only">{{ __('Tax %') }}</label><input wire:model="lineItems.{{ $index }}.tax_rate" data-line-tax data-line-final type="number" min="0" max="100" step="0.0001" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" /></div>
                                <div class="col-span-10 sm:col-span-2 lg:col-auto"><span class="block text-xs font-semibold lg:sr-only">{{ __('Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-white px-2 font-mono text-sm dark:bg-zinc-900" data-line-total>0.00</output></div>
                                <div class="col-span-2 pt-1 text-end lg:col-auto">@if(count($lineItems)>1)<flux:button type="button" size="xs" variant="ghost" icon="trash" class="text-rose-600" wire:click="removeLine({{ $index }})" />@endif</div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end pt-1"><div class="rounded-lg bg-zinc-100 px-4 py-2 text-end dark:bg-zinc-800"><span class="text-xs text-zinc-500">{{ __('Invoice total') }}: </span><output class="font-mono font-bold text-zinc-900 dark:text-white" data-editor-total>0.00</output><span class="ms-1 text-xs text-zinc-400" dir="ltr">{{ $invoiceForm['currency_code'] ?: '—' }}</span></div></div>
                </div>

                <flux:input wire:model="invoiceForm.notes" :label="__('Notes')" />

                <div class="flex flex-wrap justify-end gap-2 border-t border-border pt-3">
                    <flux:button type="button" variant="subtle" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save draft') }}</flux:button>
                </div>
            </form>
        </flux:card>
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

    @if($showDistribution && $distributionInvoice)
        <flux:card class="space-y-5" aria-label="{{ __('Stage 2 — Mandatory distribution') }}">
            <div><flux:heading size="xl">{{ __('Stage 2 — Mandatory distribution') }}</flux:heading><flux:text>{{ __('Select destinations and allocate every purchased unit. The receiving warehouse is temporary and must finish at zero.') }}</flux:text></div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($stores->where('id','!=',$distributionInvoice->store_id) as $store)
                    <flux:checkbox wire:model.live="selectedDestinationIds" value="{{ $store->id }}" :label="str_starts_with(app()->getLocale(), 'ar') ? ($store->name_ar ?: '—') : ($store->name_en ?: '—')" />
                @endforeach
            </div>
            <form wire:submit="saveDistribution" class="space-y-4 overflow-x-auto">
                <table class="min-w-full text-sm"><thead><tr><th class="p-2 text-start">{{__('Product')}}</th><th class="p-2">{{__('Purchased')}}</th>@foreach($stores->whereIn('id',array_map('intval',$selectedDestinationIds)) as $store)<th class="p-2">{{ str_starts_with(app()->getLocale(), 'ar')?$store->name_ar:($store->name_en?:$store->name_ar) }}@if($store->type==='selling')<div class="text-xs text-text-muted">{{__('Effective destination price')}}</div>@endif</th>@endforeach<th class="p-2">{{__('Distributed')}}</th><th class="p-2">{{__('Remaining')}}</th></tr></thead>
                <tbody>@foreach($distributionInvoice->lines as $line) @php($distributed=collect($distributionMatrix[$line->id]??[])->reduce(fn($t,$q)=>bcadd($t,is_numeric($q)?(string)$q:'0',6),'0'))
                    <tr class="border-t"><td class="p-2"><strong>{{ str_starts_with(app()->getLocale(), 'ar')?($line->product->name_ar?:'—'):($line->product->name_en?:'—') }}</strong><div dir="ltr">{{ $line->product->model_number }} · {{ $line->product->item_code }}</div></td><td class="p-2 text-center" dir="ltr">{{ $this->formatQuantity($line->quantity) }}</td>
                    @foreach($stores->whereIn('id',array_map('intval',$selectedDestinationIds)) as $store)<td class="p-2"><flux:input wire:model.live="distributionMatrix.{{ $line->id }}.{{ $store->id }}" type="number" min="0" step="1" aria-label="{{__('Distribution quantity')}}" />@php($saved=$distributionInvoice->distributions->first(fn($d)=>$d->purchase_invoice_line_id===$line->id&&$d->destination_store_id===$store->id))@if($saved?->effective_selling_price)<div class="mt-1 text-center"><x-money :amount="$saved->effective_selling_price" :currency="$distributionInvoice->currency_code" /></div>@endif</td>@endforeach
                    <td class="p-2 text-center" dir="ltr">{{ $this->formatQuantity($distributed) }}</td><td class="p-2 text-center {{ bccomp(bcsub((string)$line->quantity,$distributed,6),'0',6)!==0?'text-red-600':'text-green-700' }}" dir="ltr">{{ $this->formatQuantity(bcsub((string)$line->quantity,$distributed,6)) }}</td></tr>
                @endforeach</tbody></table>
                <div class="flex flex-wrap justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="$set('showDistribution', false)">{{__('Back to invoices')}}</flux:button><flux:button type="submit" variant="primary">{{__('Save distribution')}}</flux:button>@can('purchase_invoices.approve')<flux:button type="button" wire:click="approveInvoice({{$distributionInvoice->id}})" variant="primary">{{__('Approve & post')}}</flux:button>@endcan</div>
            </form>
        </flux:card>
    @endif

    @if($showQuickProduct)
        <flux:modal wire:model.self="showQuickProduct" class="md:max-w-5xl xl:max-w-6xl">
            <form wire:submit="createQuickProduct" class="space-y-3" novalidate>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <flux:heading size="lg">{{ __('Unknown barcode — create Product Card') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('The invoice remains open. Create the complete Phase 1 product card and it will be inserted immediately.') }}</flux:text>
                    </div>
                    <flux:badge color="sky" size="sm">{{ __('Draft invoice preserved') }}</flux:badge>
                </div>

                @if ($errors->has('quickProduct.*'))
                    <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Product card could not be created') }}" class="py-2">
                        <ul class="list-disc ps-5 text-sm">@foreach ($errors->get('quickProduct.*') as $messages) @foreach ($messages as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul>
                    </flux:callout>
                @endif

                <fieldset class="rounded-xl border border-border p-3">
                    <legend class="px-1 text-sm font-semibold text-text-primary">{{ __('Identity') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <flux:select wire:model="quickProduct.barcode_registration_type" :label="__('Barcode registration type')" required>
                            <flux:select.option value="international">{{ __('International barcode') }}</flux:select.option>
                            <flux:select.option value="local">{{ __('Local generated barcode') }}</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="quickProduct.barcode" :label="__('Barcode / SKU')" dir="ltr" required />
                        <flux:input wire:model="quickProduct.model_number" :label="__('Model number')" required />
                        <flux:input wire:model="quickProduct.name_ar" :label="__('Arabic product name')" dir="rtl" required />
                        <flux:input wire:model="quickProduct.name_en" :label="__('English product name')" dir="ltr" />
                    </div>
                </fieldset>

                <fieldset class="rounded-xl border border-border p-3">
                    <legend class="px-1 text-sm font-semibold text-text-primary">{{ __('Classification') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <flux:select wire:model="quickProduct.category_id" :label="__('Category / subcategory')" required><flux:select.option value="">{{ __('Select category') }}</flux:select.option>@foreach($categories as $category)<flux:select.option value="{{ $category->id }}">{{ $category->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($category->name_ar ?: '—') : ($category->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
                        <flux:select wire:model="quickProduct.brand_id" :label="__('Brand')"><flux:select.option value="">{{ __('No brand') }}</flux:select.option>@foreach($brands as $brand)<flux:select.option value="{{ $brand->id }}">{{ $brand->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($brand->name_ar ?: '—') : ($brand->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
                        <flux:select wire:model="quickProduct.preferred_supplier_id" :label="__('Supplier')"><flux:select.option value="">{{ __('No supplier') }}</flux:select.option>@foreach($suppliers as $supplier)<flux:select.option value="{{ $supplier->id }}">{{ $supplier->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->name_ar ?: '—') : ($supplier->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
                        <flux:select wire:model="quickProduct.product_type" :label="__('Product type')" required><flux:select.option value="standard">{{ __('Standard') }}</flux:select.option><flux:select.option value="service">{{ __('Service') }}</flux:select.option><flux:select.option value="composite">{{ __('Composite') }}</flux:select.option></flux:select>
                        <flux:select wire:model="quickProduct.status" :label="__('Status')" required><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select>
                    </div>
                </fieldset>

                <fieldset class="rounded-xl border border-border p-3">
                    <legend class="px-1 text-sm font-semibold text-text-primary">{{ __('Commercial fields') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                        <flux:input wire:model="quickProduct.average_cost" type="number" min="0" step="0.01" :label="__('Cost price')" required />
                        <flux:input wire:model="quickProduct.sale_price" type="number" min="0.01" step="0.01" :label="__('Base consumer selling price')" required />
                        <flux:checkbox wire:model="quickProduct.open_price" :label="__('Open-price product')" />
                    </div>
                </fieldset>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="closeQuickProduct">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="createQuickProduct">{{ __('Create and add') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($showTransitionModal)
        <flux:modal wire:model.self="showTransitionModal" class="md:max-w-lg">
            <form wire:submit="executeTransition" class="space-y-5">
                <flux:heading size="lg">{{ $transitionType === 'reverse' ? __('Cancel purchase invoice and reverse its effects') : __(ucfirst($transitionType)).' '.__('purchase invoice') }}</flux:heading>
                <flux:text>{{ $transitionType === 'reverse' ? __('This cancels the approved invoice, reverses its stock receipt, and restores the previous product costs. It cannot be undone here.') : __('A reason is required and will be written to the audit trail.') }}</flux:text>
                <flux:textarea wire:model="transitionReason" :label="__('Reason')" rows="4" required />
                @error('transitionReason') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showTransitionModal', false)">{{ __('Close') }}</flux:button>
                    <flux:button type="submit" variant="danger">{{ $transitionType === 'reverse' ? __('Confirm invoice reversal') : __('Confirm') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

</x-app.page>
