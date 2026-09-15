<?php

use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\TaxSetting;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Product Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $categoryFilter = 'all';

    public string $brandFilter = 'all';

    public string $statusFilter = 'all';

    public string $productTypeFilter = 'all';

    public string $colourFilter = '';

    public string $ageFilter = '';

    public string $genderFilter = 'all';

    public string $characterFilter = '';

    public bool $showProductModal = false;

    public ?int $editingProductId = null;

    public array $productForm = [
        'item_code' => '',
        'name_ar' => '',
        'name_en' => '',
        'product_type' => 'standard',
        'category_id' => '',
        'brand_id' => '',
        'tax_setting_id' => '',
        'preferred_supplier_id' => '',
        'supplier_item_code' => '',
        'status' => 'active',
    ];

    public ?int $productVersion = null;

    public bool $showBarcodeModal = false;

    public ?int $barcodeProductId = null;

    public string $barcodeProductLabel = '';

    public array $barcodeForm = [
        'source' => 'supplier',
        'barcode' => '',
        'supplier_code' => '',
    ];

    public string $allocationKey = '';

    public array $barcodeRecords = [];

    public function mount(): void
    {
        Gate::authorize('products_categories_brands.view');
    }

    public function updatingSearch(string $value): void
    {
        $this->search = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBrandFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProductTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingColourFilter(string $value): void
    {
        $this->colourFilter = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingAgeFilter(string $value): void
    {
        $this->ageFilter = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingGenderFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCharacterFilter(string $value): void
    {
        $this->characterFilter = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function openCreateProductModal(): void
    {
        Gate::authorize('products_categories_brands.create');
        $this->editingProductId = null;
        $this->productVersion = null;
        $this->productForm = [
            'item_code' => '',
            'name_ar' => '',
            'name_en' => '',
            'product_type' => 'standard',
            'category_id' => '',
            'brand_id' => '',
            'tax_setting_id' => '',
            'preferred_supplier_id' => '',
            'supplier_item_code' => '',
            'status' => 'active',
        ];
        $this->resetValidation();
        $this->showProductModal = true;
    }

    public function openEditProductModal(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $product = Product::query()->findOrFail($id);
        $this->editingProductId = $product->id;
        $this->productVersion = $product->lock_version;
        $this->productForm = [
            'item_code' => $product->item_code,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'product_type' => $product->product_type,
            'category_id' => (string) $product->category_id,
            'brand_id' => (string) ($product->brand_id ?? ''),
            'tax_setting_id' => (string) ($product->tax_setting_id ?? ''),
            'preferred_supplier_id' => (string) ($product->preferredProductSupplier?->supplier_id ?? ''),
            'supplier_item_code' => (string) ($product->preferredProductSupplier?->supplier_item_code ?? ''),
            'status' => $product->status,
        ];
        $this->resetValidation();
        $this->showProductModal = true;
    }

    protected function validationAttributes(): array
    {
        return str_starts_with(app()->getLocale(), 'ar')
            ? [
                'productForm.item_code' => 'كود الصنف',
                'productForm.name_ar' => 'اسم المنتج بالعربية',
                'productForm.name_en' => 'اسم المنتج بالإنجليزية',
                'productForm.product_type' => 'نوع المنتج',
                'productForm.category_id' => 'الفئة',
                'productForm.brand_id' => 'العلامة التجارية',
                'productForm.tax_setting_id' => 'ضريبة المنتج',
                'productForm.status' => 'الحالة',
            ]
            : [];
    }

    public function saveProduct(SaveProductAction $action): void
    {
        Gate::authorize($this->editingProductId ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $validated = $this->validate([
            'productForm.item_code' => [
                $this->editingProductId ? 'required' : 'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
                Rule::unique('products', 'item_code')->ignore($this->editingProductId),
            ],
            'productForm.name_ar' => ['required', 'string', 'max:255'],
            'productForm.name_en' => ['required', 'string', 'max:255'],
            'productForm.product_type' => ['required', 'in:standard,composite,service,digital'],
            'productForm.category_id' => ['required', 'integer', 'exists:categories,id'],
            'productForm.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'productForm.tax_setting_id' => [
                'nullable',
                'integer',
                Rule::exists('tax_settings', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->where(fn ($dates) => $dates->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn ($dates) => $dates->whereNull('effective_to')->orWhere('effective_to', '>=', now()))),
            ],
            'productForm.preferred_supplier_id' => [$this->editingProductId || filled($this->productForm['item_code']) ? 'nullable' : 'required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')],
            'productForm.supplier_item_code' => ['nullable', 'string', 'max:100'],
            'productForm.status' => ['required', 'in:active,inactive'],
        ], str_starts_with(app()->getLocale(), 'ar') ? [
            'productForm.item_code.required' => 'كود الصنف مطلوب.',
            'productForm.item_code.regex' => 'صيغة كود الصنف غير صحيحة. استخدم حروفًا إنجليزية وأرقامًا وشرطة أو شرطة سفلية فقط.',
            'productForm.item_code.unique' => 'كود الصنف مستخدم بالفعل.',
        ] : [], $this->validationAttributes())['productForm'];

        try {
            $action->execute($validated, $this->editingProductId, $this->productVersion);
            Flux::toast(variant: 'success', text: $this->editingProductId ? __('Product identity updated successfully.') : __('Product created successfully.'));
            $this->showProductModal = false;
        } catch (Throwable $exception) {
            $this->addError('productForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function toggleProductStatus(int $id, SaveProductAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->toggleStatus($id);
            Flux::toast(variant: 'success', text: __('Product status updated successfully.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function bulkToggleProductStatus(SaveProductAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $action->toggleStatus($id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Product status updated for :count records.', ['count' => $count]));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function openBarcodeModal(int $productId): void
    {
        Gate::authorize('products_categories_brands.edit');
        $product = Product::query()->findOrFail($productId);
        $this->barcodeProductId = $product->id;
        $this->barcodeProductLabel = $product->item_code.' · '.(str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : $product->name_en);
        $this->refreshBarcodeRecords();
        $this->barcodeForm = ['source' => 'supplier', 'barcode' => '', 'supplier_code' => ''];
        $this->allocationKey = (string) Str::uuid();
        $this->resetValidation();
        $this->showBarcodeModal = true;
    }

    public function addBarcode(AddBarcodeAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        $rules = [
            'barcodeForm.source' => ['required', 'in:supplier,local'],
            'barcodeForm.barcode' => ['nullable', 'string', 'max:64'],
            'barcodeForm.supplier_code' => ['nullable', 'regex:/^[A-Za-z0-9]{1,12}$/'],
        ];

        if ($this->barcodeForm['source'] === 'supplier') {
            $rules['barcodeForm.barcode'] = ['required', 'string', 'max:64', 'regex:/^\S+$/'];
        } else {
            $rules['barcodeForm.supplier_code'] = ['required', 'regex:/^[A-Za-z0-9]{1,12}$/'];
        }

        $validated = $this->validate($rules)['barcodeForm'];

        try {
            if ($validated['source'] === 'supplier') {
                $barcode = $action->addSupplierBarcode($this->barcodeProductId, $validated['barcode']);
                $message = __('Supplier barcode :barcode added.', ['barcode' => $barcode->barcode]);
            } else {
                $barcode = $action->allocateLocalBarcode($this->barcodeProductId, $validated['supplier_code'], $this->allocationKey);
                $message = __('Local barcode :barcode allocated.', ['barcode' => $barcode->barcode]);
                $this->allocationKey = (string) Str::uuid();
            }

            Flux::toast(variant: 'success', text: $message);
            $this->barcodeForm['barcode'] = '';
            $this->refreshBarcodeRecords();
        } catch (Throwable $exception) {
            $this->addError('barcodeForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function deactivateBarcode(int $id, AddBarcodeAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->deactivate($id);
            $this->refreshBarcodeRecords();
            Flux::toast(variant: 'success', text: __('Barcode deactivated without changing historical identity.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    private function refreshBarcodeRecords(): void
    {
        if (! $this->barcodeProductId) {
            $this->barcodeRecords = [];

            return;
        }

        $this->barcodeRecords = Product::query()->findOrFail($this->barcodeProductId)->barcodes()
            ->orderByDesc('is_primary')
            ->orderBy('barcode')
            ->get()
            ->map(fn (Barcode $barcode): array => [
                'id' => $barcode->id,
                'barcode' => $barcode->barcode,
                'source' => $barcode->source,
                'status' => $barcode->status,
            ])->all();
    }

    #[Computed]
    public function catalogLookups(): array
    {
        return [
            'categories' => Category::query()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en', 'status']),
            'brands' => Brand::query()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en', 'status']),
            'activeCategories' => Category::query()->active()->with('parent:id,code,name_ar,name_en')->orderBy('sort_order')->orderBy('code')->get(['id', 'parent_id', 'code', 'name_ar', 'name_en']),
            'activeBrands' => Brand::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'activeSuppliers' => Supplier::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'activeTaxSettings' => TaxSetting::query()
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
                ->orderBy('code')
                ->get(['id', 'code', 'name_ar', 'name_en', 'rate']),
        ];
    }

    public function render()
    {
        $term = mb_strtolower(trim(Str::limit($this->search, 100, '')));
        $query = Product::query()->familiesAndSimple()->with([
            'category.parent:id,code,name_ar,name_en',
            'brand:id,code,name_ar,name_en',
            'barcodes' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('barcode'),
        ]);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($scope) use ($term, $like): void {
                $scope->where('item_code', 'like', $like)
                    ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('barcode', 'like', $like))
                    ->orWhere('model_number', 'like', $like)
                    ->orWhereHas('productSuppliers', fn ($link) => $link->where('supplier_item_code', 'like', $like))
                    ->orWhereRaw('LOWER(name_ar) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(keywords_ar, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(keywords_en, \'\')) LIKE ?', [$like]);
            });
        }

        if ($this->categoryFilter !== 'all') {
            $query->where('category_id', (int) $this->categoryFilter);
        }

        if ($this->brandFilter !== 'all') {
            $query->where('brand_id', (int) $this->brandFilter);
        }

        if ($this->statusFilter === 'draft') {
            $query->incompleteCard();
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->productTypeFilter !== 'all') {
            $query->where('product_type', $this->productTypeFilter);
        }

        foreach ([['colour', $this->colourFilter], ['target_age', $this->ageFilter], ['character', $this->characterFilter]] as [$field, $value]) {
            if (trim((string) $value) !== '') {
                $query->whereRaw('LOWER('.$field.') LIKE ?', ['%'.mb_strtolower(trim((string) $value)).'%']);
            }
        }

        if ($this->genderFilter !== 'all') {
            $query->where('suitable_gender', $this->genderFilter);
        }

        if ($term !== '') {
            $query->orderByRaw(
                'CASE WHEN item_code = ? THEN 0 WHEN EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.barcode = ?) THEN 1 ELSE 2 END',
                [$term, $term],
            );
        }

        $products = $query->orderBy('item_code')->paginate(12);

        return view('catalog.products', [
            'products' => $products,
            ...$this->catalogLookups,
            'productTypes' => ['standard', 'composite', 'service', 'digital'],
            'genderOptions' => ['unisex', 'female', 'male'],
        ]);
    }
}; ?>

<x-app.page
    :title="str_starts_with(app()->getLocale(), 'ar') ? 'المنتجات والباركود' : __('Products & barcodes')"
    :description="str_starts_with(app()->getLocale(), 'ar') ? 'ابحث بالاسم أو كود الصنف أو الباركود، وراجع الهوية والمتغيرات وحالة بيانات الكتالوج.' : __('Search by name, item code, or barcode and review identity, variants, and catalog readiness.')"
    :breadcrumbs="str_starts_with(app()->getLocale(), 'ar') ? 'المنتجات والمخزون' : 'Products & inventory'"
    max-width="7xl"
    class="catalog-screen"
    data-guide="products-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="products-filters">
            @can('products_categories_brands.create')
                <flux:button href="{{ route('catalog.products.import.template.xlsx') }}" icon="arrow-down-tray" variant="subtle">{{ __('Import template') }}</flux:button>
                <flux:button href="{{ route('catalog.products.import') }}" icon="arrow-up-tray" variant="subtle" wire:navigate>{{ __('Excel import') }}</flux:button>
                <flux:button href="{{ route('catalog.products.create') }}" icon="plus" variant="primary" wire:navigate>{{ str_starts_with(app()->getLocale(), 'ar') ? 'إضافة كارت صنف' : 'Add Product Card' }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    @can('products_categories_brands.create')
        <details class="rounded-xl border border-border bg-surface p-4"><summary class="cursor-pointer font-semibold">{{ __('Product Card Excel import') }}</summary><form method="POST" enctype="multipart/form-data" action="{{ route('catalog.products.import-card') }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf<flux:input type="file" name="workbook" accept=".xlsx" :label="__('Product Card XLSX workbook')" required/><x-actions.button type="submit" semantic="create" :label="__('Validate and import valid rows')">{{ __('Validate and import valid rows') }}</x-actions.button>@if(session('product_card_import_rejections'))<flux:button href="{{ route('catalog.products.import-card.rejections') }}" icon="arrow-down-tray">{{ __('Download rejected rows') }}</flux:button>@endif</form></details>
    @endcan
    @if ($activeCategories->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Product entry needs an active category') }}">
            <div class="space-y-2">
                <p>{{ __('Products must be assigned to an active category. Create the category hierarchy first; catalog users with category create permission can configure it.') }}</p>
                @can('products_categories_brands.create')
                    <flux:button href="{{ route('catalog.categories') }}" variant="subtle" icon="arrow-top-right-on-square" wire:navigate>{{ __('Configure categories') }}</flux:button>
                @endcan
            </div>
        </flux:callout>
    @endif

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Catalog action could not be completed') }}">
            <ul class="list-disc space-y-1 ps-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <div id="products-filters" class="catalog-filter-card scroll-mt-24 rounded-xl p-4 sm:p-5" data-guide="products-filters">
        <div class="catalog-filter-heading mb-4">
            <div>
                <flux:heading size="sm">{{ __('Search') }}</flux:heading>
                <flux:text class="mt-1 text-xs text-text-muted">{{ __('Exact code and barcode matches are prioritized before name matches.') }}</flux:text>
            </div>
            <span class="hidden rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary sm:inline-flex">{{ __('12 per page') }}</span>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Exact code/barcode or Arabic/English name...')" />
        <flux:select wire:model.live="categoryFilter" :label="__('Category')">
            <flux:select.option value="all">{{ __('All categories') }}</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option :value="$category->id">{{ $category->code }} &middot; {{ str_starts_with(app()->getLocale(), 'ar') ? $category->name_ar : $category->name_en }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="brandFilter" :label="__('Brand')">
            <flux:select.option value="all">{{ __('All brands') }}</flux:select.option>
            @foreach ($brands as $brand)
                <flux:select.option :value="$brand->id">{{ $brand->code }} &middot; {{ str_starts_with(app()->getLocale(), 'ar') ? $brand->name_ar : $brand->name_en }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFilter" :label="__('Status')">
            <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="productTypeFilter" :label="__('Product type')">
            <flux:select.option value="all">{{ __('All product types') }}</flux:select.option>
            @foreach ($productTypes as $type)
                <flux:select.option :value="$type">{{ __(ucfirst($type)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="genderFilter" :label="__('Gender')">
            <flux:select.option value="all">{{ __('All genders') }}</flux:select.option>
            @foreach ($genderOptions as $gender)
                <flux:select.option :value="$gender">{{ __(ucfirst($gender)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="colourFilter" :label="__('Colour')" :placeholder="__('Filter colour')" />
        <flux:input wire:model.live.debounce.300ms="ageFilter" :label="__('Target age')" :placeholder="__('Filter age')" />
        <flux:input wire:model.live.debounce.300ms="characterFilter" :label="__('Character')" :placeholder="__('Filter character')" />
        </div>
    </div>

    <div wire:loading.flex wire:target="search,categoryFilter,brandFilter,statusFilter,productTypeFilter,colourFilter,ageFilter,genderFilter,characterFilter,gotoPage,previousPage,nextPage" role="status" aria-live="polite" class="catalog-loading">
        <flux:icon name="arrow-path" class="size-4 animate-spin" />
        {{ __('Loading catalog...') }}
    </div>

    @php($hasProductFilters = trim($search) !== '' || $categoryFilter !== 'all' || $brandFilter !== 'all' || $statusFilter !== 'all' || $productTypeFilter !== 'all' || trim($colourFilter) !== '' || trim($ageFilter) !== '' || $genderFilter !== 'all' || trim($characterFilter) !== '')
    @if ($products->isEmpty())
        <flux:card class="space-y-3 p-10 text-center" data-guide="products-empty">
            <flux:icon name="cube" class="mx-auto size-12 text-zinc-400" />
            @if ($hasProductFilters)
                <flux:heading size="lg">{{ __('No products match these filters') }}</flux:heading>
                <flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('No product matches the current search or filters. Clear them to see the full catalog, or add a new product.') }}</flux:text>
                <div class="flex flex-wrap justify-center gap-2">
                    <flux:button href="{{ route('catalog.products') }}" icon="arrow-path" variant="subtle" wire:navigate>{{ __('Clear filters') }}</flux:button>
                    @can('products_categories_brands.create')
                        <flux:button icon="plus" variant="primary" wire:click="openCreateProductModal">{{ __('Manual entry') }}</flux:button>
                    @endcan
                </div>
            @else
                <flux:heading size="lg">{{ __('No products found') }}</flux:heading>
                <flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('No product master records exist in your authorized scope yet. Create one manually or use the reviewed Excel import flow.') }}</flux:text>
                @can('products_categories_brands.create')
                    <div class="flex flex-wrap justify-center gap-2">
                        <flux:button icon="plus" variant="primary" wire:click="openCreateProductModal">{{ __('Manual entry') }}</flux:button>
                <flux:button href="{{ route('catalog.products.import.template.xlsx') }}" icon="arrow-down-tray" variant="subtle">{{ __('Import template') }}</flux:button>
                        <flux:button href="{{ route('catalog.products.import') }}" icon="arrow-up-tray" variant="subtle" wire:navigate>{{ __('Excel import') }}</flux:button>
                    </div>
                @else
                    <flux:text class="text-sm text-text-muted">{{ __('You can view product masters, but you do not have permission to create or import records.') }}</flux:text>
                @endcan
            @endif
        </flux:card>
    @else
        <div class="catalog-table-frame" data-guide="products-table">
            <x-tables.bulk-actions
                :page-ids="$products->pluck('id')->all()"
                :selected-ids="$selectedIds"
                :selected-count="count($selectedIds)"
                :page-count="$products->count()"
            >
                <x-slot:actions>
                    @can('products_categories_brands.edit')
                        <flux:button type="button" size="sm" variant="subtle" wire:click="bulkToggleProductStatus" wire:confirm="{{ __('Toggle status for the selected products?') }}">
                            {{ __('Toggle status') }}
                        </flux:button>
                    @endcan
                </x-slot:actions>
            </x-tables.bulk-actions>
            <flux:table class="catalog-resource-table responsive-resource-table" aria-label="{{ __('Product Cards') }}">
                <flux:table.columns>
                    <flux:table.column>
                        <span class="sr-only">{{ __('Select') }}</span>
                    </flux:table.column>
                    <flux:table.column class="min-w-32">{{ __('Item code') }}</flux:table.column>
                    <flux:table.column class="min-w-52">{{ __('Product name') }}</flux:table.column>
                    <flux:table.column class="min-w-28">{{ __('Type') }}</flux:table.column>
                    <flux:table.column class="min-w-48">{{ __('Category / brand') }}</flux:table.column>
                    <flux:table.column class="min-w-36">{{ __('Barcodes') }}</flux:table.column>
                    <flux:table.column class="min-w-24">{{ __('Status') }}</flux:table.column>
                    <flux:table.column class="min-w-40">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($products as $product)
                        <flux:table.row :key="$product->id">
                            <flux:table.cell>
                                <input type="checkbox" value="{{ $product->id }}" wire:model.live="selectedIds" aria-label="{{ __('Select product :code', ['code' => $product->item_code]) }}" class="size-4 rounded border-border text-primary focus:ring-primary" />
                            </flux:table.cell>
                            <flux:table.cell data-primary class="whitespace-nowrap"><span class="catalog-code-chip">{{ $product->item_code }}</span></flux:table.cell>
                            <flux:table.cell data-label="{{ __('Product name') }}">
                                <div class="font-medium text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : $product->name_en }}</div>
                                <div class="catalog-secondary-line">{{ str_starts_with(app()->getLocale(), 'ar') ? $product->name_en : $product->name_ar }}</div>
                            </flux:table.cell>
                            <flux:table.cell data-label="{{ __('Type') }}"><flux:badge size="sm" color="zinc">{{ __(ucfirst($product->product_type)) }}</flux:badge><div class="mt-1 text-xs text-text-muted">{{ $product->colour ?: __('No colour') }}</div></flux:table.cell>
                            <flux:table.cell data-label="{{ __('Category / brand') }}" class="text-xs">
                                @if($product->category?->parent)<div class="font-semibold">{{ __('Main category') }}: {{ str_starts_with(app()->getLocale(), 'ar') ? $product->category->parent->name_ar : $product->category->parent->name_en }}</div><div>{{ __('Subcategory') }}: {{ str_starts_with(app()->getLocale(), 'ar') ? $product->category->name_ar : $product->category->name_en }}</div>@else<div class="font-semibold">{{ __('Main category') }}: {{ str_starts_with(app()->getLocale(), 'ar') ? $product->category?->name_ar : $product->category?->name_en }}</div>@endif
                                @if ($product->brand)
                                    <div class="text-zinc-500">{{ $product->brand->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $product->brand->name_ar : $product->brand->name_en }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell data-label="{{ __('Barcodes') }}">
                                <div class="flex max-w-72 flex-wrap gap-1">
                                    @forelse ($product->barcodes as $barcode)
                                        <span class="catalog-code-chip">{{ $barcode->barcode }}</span>
                                    @empty
                                        <span class="text-xs text-text-muted">{{ __('None yet') }}</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell data-label="{{ __('Status') }}">
                                @if($product->hasIncompleteCard())<flux:badge size="sm" color="amber">{{ __('Draft') }}</flux:badge>@else<flux:badge size="sm" :color="$product->status === 'active' ? 'emerald' : 'zinc'">{{ __($product->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge>@endif
                            </flux:table.cell>
                            <flux:table.cell data-label="{{ __('Actions') }}" class="whitespace-nowrap">
                                @can('products_categories_brands.edit')
                                    <div class="catalog-actions">
                                        <x-actions.button semantic="view" :label="__('View details')" :href="route('catalog.products.show', ['product' => $product])" size="xs" wire:navigate />
                                        <x-actions.button semantic="view" icon="arrow-top-right-on-square" :label="$product->hasIncompleteCard() ? __('Resume') : __('Full product card')" :href="route('catalog.products.edit', ['product' => $product])" size="xs" wire:navigate />
                                        <x-actions.button semantic="edit" :label="__('Edit identity')" size="xs" wire:click="openEditProductModal({{ $product->id }})" />
                                        <x-actions.button semantic="assign" icon="tag" :label="__('Manage barcodes')" size="xs" wire:click="openBarcodeModal({{ $product->id }})" />
                                        <x-actions.button :semantic="$product->status === 'active' ? 'disable' : 'restore'" :icon="$product->status === 'active' ? 'pause' : 'play'" :label="$product->status === 'active' ? __('Deactivate') : __('Activate')" size="xs" wire:click="toggleProductStatus({{ $product->id }})" />
                                    </div>
                                @else
                                    <span class="text-xs font-medium text-text-muted">{{ __('View only') }}</span>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
        <div data-guide="products-pagination"><x-tables.pagination :paginator="$products" :page-sizes="[12]" /></div>
    @endif

    <flux:modal name="product-identity-editor" wire:model="showProductModal" class="max-w-2xl">
        <div class="catalog-modal-section space-y-1">
            <flux:heading level="2" size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? ($editingProductId ? 'تعديل هوية المنتج' : 'إنشاء هوية المنتج') : ($editingProductId ? __('Edit product identity') : __('Create product identity')) }}</flux:heading>
            <flux:subheading>{{ str_starts_with(app()->getLocale(), 'ar') ? 'أدخل البيانات الأساسية للمنتج. وللوصف والخصائص والصور استخدم محرر بطاقة المنتج الكامل.' : __('This quick editor preserves the identity slice. Use the full product-card editor for descriptions, types, attributes, and protected media.') }}</flux:subheading>
        </div>
        <form wire:submit="saveProduct" novalidate class="space-y-4">
            <div>
                <flux:input autofocus wire:model="productForm.item_code" :label="__('Global / scanned code (optional)')" :disabled="$editingProductId !== null" />
                <flux:text class="mt-1 text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'لا يمكن تغيير هذا الكود بعد إنشاء المنتج.' : __('This identity value cannot be changed after the product is created.') }}</flux:text>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="productForm.name_ar" :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم المنتج بالعربية' : __('Arabic product name')" required />
                <flux:input wire:model="productForm.name_en" :label="str_starts_with(app()->getLocale(), 'ar') ? 'اسم المنتج بالإنجليزية' : __('English product name')" required />
            </div>
            <flux:select wire:model="productForm.product_type" :label="str_starts_with(app()->getLocale(), 'ar') ? 'نوع المنتج' : __('Product type')" required>
                <flux:select.option value="standard">{{ str_starts_with(app()->getLocale(), 'ar') ? 'منتج عادي' : __('Standard') }}</flux:select.option>
                <flux:select.option value="service">{{ str_starts_with(app()->getLocale(), 'ar') ? 'خدمة' : __('Service') }}</flux:select.option>
                <flux:select.option value="digital">{{ __('Digital product') }}</flux:select.option>
            </flux:select>
            <div class="grid gap-4 md:grid-cols-2">
            <flux:select wire:model="productForm.category_id" :label="str_starts_with(app()->getLocale(), 'ar') ? 'الفئة' : __('Category')" required>
                <flux:select.option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اختر فئة نشطة...' : __('Select active category...') }}</flux:select.option>
                @foreach ($activeCategories as $category)
                    <flux:select.option :value="$category->id">{{ $category->parent ? (str_starts_with(app()->getLocale(), 'ar') ? $category->parent->name_ar : $category->parent->name_en).' / ' : '' }}{{ str_starts_with(app()->getLocale(), 'ar') ? $category->name_ar : $category->name_en }} · {{ $category->code }}</flux:select.option>
                @endforeach
            </flux:select>
    @can('products_categories_brands.create')
        <details class="rounded-xl border border-border bg-surface p-4"><summary class="cursor-pointer font-semibold">{{ __('Product Card Excel import') }}</summary><form method="POST" enctype="multipart/form-data" action="{{ route('catalog.products.import-card') }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf<flux:input type="file" name="workbook" accept=".xlsx" :label="__('Product Card XLSX workbook')" required/><x-actions.button type="submit" semantic="create" :label="__('Validate and import valid rows')">{{ __('Validate and import valid rows') }}</x-actions.button>@if(session('product_card_import_rejections'))<flux:button href="{{ route('catalog.products.import-card.rejections') }}" icon="arrow-down-tray">{{ __('Download rejected rows') }}</flux:button>@endif</form></details>
    @endcan
            @if ($activeCategories->isEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle">{{ str_starts_with(app()->getLocale(), 'ar') ? 'لا توجد فئات نشطة. أضف فئة قبل حفظ المنتج.' : __('No active categories exist yet. Configure a category before saving this product.') }} <a class="font-medium underline" href="{{ route('catalog.categories') }}" wire:navigate>{{ str_starts_with(app()->getLocale(), 'ar') ? 'إعداد الفئات' : __('Configure categories') }}</a></flux:callout>
            @endif
            <flux:select wire:model="productForm.brand_id" :label="str_starts_with(app()->getLocale(), 'ar') ? 'العلامة التجارية' : __('Brand')">
                <flux:select.option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'بدون علامة تجارية' : __('No brand') }}</flux:select.option>
                @foreach ($activeBrands as $brand)
                    <flux:select.option :value="$brand->id">{{ $brand->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $brand->name_ar : $brand->name_en }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="productForm.tax_setting_id" :label="str_starts_with(app()->getLocale(), 'ar') ? 'ضريبة المنتج' : __('Product-specific tax')">
                <flux:select.option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'استخدام ضريبة الشركة الافتراضية' : __('Use company default tax') }}</flux:select.option>
                @foreach ($activeTaxSettings as $taxSetting)
                    <flux:select.option :value="$taxSetting->id">{{ $taxSetting->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $taxSetting->name_ar : $taxSetting->name_en }} @if($taxSetting->rate !== null)({{ number_format((float) $taxSetting->rate, 2) }}%)@endif</flux:select.option>
                @endforeach
            </flux:select>
            @if ($activeBrands->isEmpty())
                <flux:text class="text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'لا توجد علامات تجارية نشطة. العلامة اختيارية؛ أضفها فقط إذا كان المنتج يحتاجها.' : __('No active brands exist. Brand is optional; configure one only if this product needs a brand.') }} <a class="font-medium underline" href="{{ route('catalog.brands') }}" wire:navigate>{{ str_starts_with(app()->getLocale(), 'ar') ? 'إعداد العلامات التجارية' : __('Configure brands') }}</a></flux:text>
            @endif
            <flux:select wire:model="productForm.preferred_supplier_id" :label="__('Preferred supplier')">
                <flux:select.option value="">{{ __('Select supplier when no global code is supplied') }}</flux:select.option>
                @foreach ($activeSuppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->code }} · {{ $supplier->name_en }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="productForm.supplier_item_code" :label="__('Supplier product code')" :description="__('Kept separately from the internal item code and barcode.')" dir="ltr" />
            </div>
            <flux:select wire:model="productForm.status" :label="str_starts_with(app()->getLocale(), 'ar') ? 'الحالة' : __('Status')" required>
                <flux:select.option value="active">{{ str_starts_with(app()->getLocale(), 'ar') ? 'نشط' : __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ str_starts_with(app()->getLocale(), 'ar') ? 'غير نشط' : __('Inactive') }}</flux:select.option>
            </flux:select>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="subtle" wire:click="$set('showProductModal', false)">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إلغاء' : __('Cancel') }}</flux:button>
                <flux:text wire:dirty class="me-auto self-center text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تغييرات غير محفوظة' : __('Unsaved changes') }}</flux:text>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveProduct">{{ str_starts_with(app()->getLocale(), 'ar') ? 'حفظ المنتج' : __('Save product') }} <span wire:loading wire:target="saveProduct">{{ str_starts_with(app()->getLocale(), 'ar') ? 'جارٍ الحفظ...' : __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showBarcodeModal" class="max-w-2xl space-y-5">
        <div class="rounded-xl border border-border bg-surface-muted/20 p-4">
            <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود المنتج' : __('Barcode identity') }}</flux:heading>
            <flux:subheading>{{ $barcodeProductLabel }}</flux:subheading>
        </div>
        <div class="rounded-xl border border-info/15 bg-info/5 p-4 text-sm leading-7 text-text-primary">
            {{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود المورد يُسجَّل كما ورد على العبوة. الباركود المحلي يُنشأ تلقائيًا من بادئة المورد ورقم تسلسلي.' : __('Supplier barcodes are preserved as supplied. Local barcodes concatenate a supplier prefix of up to 12 letters or digits and a six-digit sequential serial, with no invented check digit.') }}
        </div>
        @if ($barcodeProductId)
            <div class="rounded-xl border border-border bg-surface-muted/10 p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:text class="font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? 'الباركودات المرتبطة' : __('Current barcodes') }}</flux:text>
                    <span class="text-xs text-text-muted">{{ count($barcodeRecords) }} {{ str_starts_with(app()->getLocale(), 'ar') ? 'مرتبط' : __('linked') }}</span>
                </div>
                @forelse ($barcodeRecords as $barcode)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface px-3 py-2.5 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="catalog-code-chip">{{ $barcode['barcode'] }}</span>
                            <flux:badge size="sm" color="zinc">{{ __($barcode['source'] === 'local' ? 'Local' : 'Supplier') }}</flux:badge>
                            <flux:badge size="sm" color="{{ $barcode['status'] === 'active' ? 'emerald' : 'zinc' }}">{{ __($barcode['status'] === 'active' ? 'Active' : 'Inactive') }}</flux:badge>
                        </div>
                        @if ($barcode['status'] === 'active')
                            <x-actions.button semantic="disable" :label="__('Deactivate')" size="xs" variant="subtle" color="red" wire:click="deactivateBarcode({{ $barcode['id'] }})" wire:confirm="{{ __('Deactivate this barcode while preserving its historical identity?') }}">{{ __('Deactivate') }}</x-actions.button>
                        @endif
                    </div>
                @empty
                    <flux:text class="text-zinc-500">{{ __('No barcode is linked yet.') }}</flux:text>
                @endforelse
            </div>
        @endif
        <form wire:submit="addBarcode" novalidate class="rounded-xl border border-primary/20 bg-primary/5 p-4 space-y-4">
            <div>
                <flux:heading size="sm">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إضافة باركود للمنتج' : __('Add barcode') }}</flux:heading>
                <flux:text class="mt-1 text-xs leading-6 text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اختار نوع الباركود ثم أدخل القيمة أو أنشئ باركودًا محليًا.' : __('Choose the source before entering or allocating the identity.') }}</flux:text>
            </div>
            <flux:select wire:model.live="barcodeForm.source" :label="str_starts_with(app()->getLocale(), 'ar') ? 'نوع الباركود' : __('Barcode source')" required>
                <flux:select.option value="supplier">{{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود المورد (يُحفظ كما هو)' : __('Supplier / international') }}</flux:select.option>
                <flux:select.option value="local">{{ str_starts_with(app()->getLocale(), 'ar') ? 'باركود محلي (يُنشأ تلقائيًا)' : __('Locally generated') }}</flux:select.option>
            </flux:select>
            <div class="rounded-lg border border-border bg-surface/60 px-3 py-2 text-xs leading-6 text-text-muted">
                {{ $barcodeForm['source'] === 'supplier'
                    ? (str_starts_with(app()->getLocale(), 'ar') ? 'استخدمه عندما يكون للمنتج باركود مطبوع من المورد.' : __('Use this when the supplier already provides the barcode.'))
                    : (str_starts_with(app()->getLocale(), 'ar') ? 'سيُنشأ الرقم التسلسلي تلقائيًا؛ لا تحتاج لإدخاله.' : __('The serial is allocated automatically; you do not need to enter it.')) }}
            </div>
            @if ($barcodeForm['source'] === 'supplier')
                <flux:input wire:model="barcodeForm.barcode" :label="str_starts_with(app()->getLocale(), 'ar') ? 'باركود المورد' : __('Supplier barcode')" :placeholder="str_starts_with(app()->getLocale(), 'ar') ? 'أدخل الباركود كما هو' : __('Enter the supplied value')" required />
            @else
                <flux:input wire:model="barcodeForm.supplier_code" :label="__('Supplier barcode prefix')" maxlength="12" placeholder="AHMED01" required />
            @endif
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="subtle" wire:click="$set('showBarcodeModal', false)">{{ __('Close') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addBarcode">{{ $barcodeForm['source'] === 'local' ? __('Allocate barcode') : __('Add barcode') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
