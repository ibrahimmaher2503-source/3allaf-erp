<?php

use App\Modules\Catalog\Actions\RemoveProductAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductDependencyService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Product Details')] class extends Component {
    public Product $product;

    public function mount(Product $product): void
    {
        Gate::authorize('products_categories_brands.view');
        $this->product = $product->load([
            'category',
            'brand',
            'barcodes' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('barcode'),
            'images.attachment',
            'productSuppliers.supplier',
        ]);
    }

    public function removeProduct(bool $permanent, RemoveProductAction $action): mixed
    {
        $result = $action->execute($this->product->id, $permanent);
        if ($result['outcome'] === 'deleted') {
            return $this->redirectRoute('catalog.products', navigate: true);
        }
        $this->product->refresh();
        session()->flash('success', __('Product archived. Historical and operational records remain unchanged.'));

        return null;
    }

    public function render()
    {
        $this->product->loadMissing(['category', 'brand', 'barcodes', 'images.attachment', 'productSuppliers.supplier']);
        $canRemove = Gate::allows('products_categories_brands.logical_delete');
        return view('catalog.product-detail', [
            'canEdit' => Gate::allows('products_categories_brands.edit'),
            'canViewCost' => auth()->user()?->hasPermission('products_categories_brands.cost_view') ?? false,
            'canRemove' => $canRemove,
            'removalReport' => $canRemove ? app(ProductDependencyService::class)->inspect($this->product) : null,
        ]);
    }
}; ?>

<x-app.page
    :title="str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : $product->name_en"
    :description="__('Product detail summary with role-safe identity, attributes, barcodes, and protected media.')"
    max-width="7xl"
    class="catalog-screen"
    data-guide="product-detail-header"
>
    <x-slot:actions>
        <flux:button href="{{ route('catalog.products') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to products') }}</flux:button>
        @if ($canEdit)
            <flux:button href="{{ route('catalog.products.edit', ['product' => $product]) }}" variant="primary" icon="pencil" wire:navigate>{{ __('Edit product card') }}</flux:button>
        @endif
    </x-slot:actions>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(19rem,.7fr)]">
        <div class="space-y-5">
            <flux:card class="catalog-detail-hero overflow-hidden p-0" data-guide="product-detail-hero">
                <div class="grid gap-0 md:grid-cols-[minmax(14rem,.8fr)_minmax(0,1.2fr)]">
                    <div class="catalog-detail-primary-media">
                        @if (($mainImage = $product->images->firstWhere('role', 'main')) !== null)
                            <img src="{{ route('catalog.products.media', ['product' => $product, 'attachment' => $mainImage->attachment]) }}" alt="{{ $mainImage->attachment->original_filename }}" />
                        @else
                            <div class="flex min-h-64 items-center justify-center"><flux:icon name="photo" class="size-16 text-text-muted" /></div>
                        @endif
                    </div>
                    <div class="space-y-5 p-5 sm:p-7">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="catalog-code-chip">{{ $product->item_code }}</span>
                            <flux:badge size="sm" color="{{ $product->status === 'active' ? 'emerald' : 'zinc' }}">{{ __($product->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge>
                            <flux:badge size="sm" color="sky">{{ __(ucfirst($product->product_type)) }}</flux:badge>
                        </div>
                        <div>
                            <flux:heading size="xl">{{ str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : $product->name_en }}</flux:heading>
                            <flux:text class="mt-2 text-sm text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? $product->name_en : $product->name_ar }}</flux:text>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><div class="catalog-detail-label">{{ __('Category') }}</div><div class="font-medium">{{ $product->category?->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $product->category?->name_ar : $product->category?->name_en }}</div></div>
                            <div><div class="catalog-detail-label">{{ __('Brand') }}</div><div class="font-medium">{{ $product->brand ? $product->brand->code.' · '.(str_starts_with(app()->getLocale(), 'ar') ? $product->brand->name_ar : $product->brand->name_en) : __('No brand') }}</div></div>
                            <div><div class="catalog-detail-label">{{ __('Model / item number') }}</div><div class="font-medium">{{ $product->model_number ?: __('Not provided') }}</div></div>
                            <div><div class="catalog-detail-label">{{ __('Unit of measure') }}</div><div class="font-medium">{{ $product->unit_of_measure ?: __('Owner-configurable') }}</div></div>
                        </div>
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-5 p-5 sm:p-6" data-guide="product-detail-descriptions">
                <div><flux:heading size="lg">{{ __('Descriptions and key points') }}</flux:heading></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="catalog-bilingual-panel" dir="rtl"><div class="catalog-detail-label">{{ __('Arabic') }}</div><p class="whitespace-pre-line text-sm leading-7">{{ $product->description_ar ?: __('No description provided.') }}</p><p class="mt-4 whitespace-pre-line text-sm leading-7 text-text-muted">{{ $product->key_points_ar }}</p></div>
                    <div class="catalog-bilingual-panel" dir="ltr"><div class="catalog-detail-label">{{ __('English') }}</div><p class="whitespace-pre-line text-sm leading-7">{{ $product->description_en ?: __('No description provided.') }}</p><p class="mt-4 whitespace-pre-line text-sm leading-7 text-text-muted">{{ $product->key_points_en }}</p></div>
                </div>
            </flux:card>

            <flux:card class="space-y-5 p-5 sm:p-6" data-guide="product-detail-attributes">
                <flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'بيانات الصنف الإضافية' : 'Additional product data' }}</flux:heading>
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([['Weight', $product->weight === null ? null : $product->weight.' '.$product->weight_unit], ['Keywords Arabic', $product->keywords_ar], ['Keywords English', $product->keywords_en]] as [$label, $value])
                        <div class="catalog-detail-field"><dt class="catalog-detail-label">{{ __($label) }}</dt><dd class="mt-1 text-sm font-medium">{{ $value ?: __('Not provided') }}</dd></div>
                    @endforeach
                </dl>
            </flux:card>

            <flux:card class="space-y-5 p-5 sm:p-6" data-guide="product-detail-suppliers">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Suppliers & Preference') }}</flux:heading>
                    <flux:badge size="sm" color="sky">{{ $product->productSuppliers->count() }} {{ __('Linked') }}</flux:badge>
                </div>
                <div class="space-y-3">
                    @forelse ($product->productSuppliers as $ps)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="catalog-code-chip">{{ $ps->supplier?->code }}</span>
                                    <span class="font-medium text-sm">{{ str_starts_with(app()->getLocale(), 'ar') ? $ps->supplier?->name_ar : $ps->supplier?->name_en }}</span>
                                    @if ($ps->is_preferred)
                                        <flux:badge size="sm" color="amber">{{ __('Preferred Supplier') }}</flux:badge>
                                    @endif
                                </div>
                                @if ($ps->supplier_item_code)
                                    <div class="mt-1 text-xs text-text-muted">{{ __('Supplier Item Code') }}: <span class="font-mono">{{ $ps->supplier_item_code }}</span></div>
                                @endif
                            </div>
                            <div class="text-xs text-text-muted text-end">
                                <div>{{ __('Last Price') }}: {{ $ps->last_purchase_price ? number_format($ps->last_purchase_price, 2) : '—' }}</div>
                            </div>
                        </div>
                    @empty
                        <x-state.empty :title="__('No supplier linked')" :description="__('Supplier preferences and product history can be managed from the Suppliers menu.')" icon="truck"><x-slot:action><flux:button href="{{ route('catalog.suppliers') }}" variant="subtle" icon="arrow-top-right-on-square">{{ __('Open supplier masters') }}</flux:button></x-slot:action></x-state.empty>
                    @endforelse
                </div>
            </flux:card>
        </div>

        <aside class="space-y-5" data-guide="product-detail-media">
            <flux:card class="space-y-4 p-5">
                <flux:heading size="lg">{{ __('Barcode summary') }}</flux:heading>
                <div class="space-y-2">
                    @forelse ($product->barcodes as $barcode)
                        @php($barcodeLabel = $barcode->source === 'local'
                            ? (str_starts_with(app()->getLocale(), 'ar') ? 'باركود داخلي ينشئه النظام' : 'System-Generated Internal Barcode')
                            : (str_starts_with(app()->getLocale(), 'ar') ? 'باركود المصنع الموجود' : 'Existing Manufacturer Barcode'))
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"><bdi dir="ltr" class="catalog-code-chip">{{ $barcode->barcode }}</bdi><flux:badge size="sm" color="{{ $barcode->source === 'local' ? 'sky' : 'zinc' }}">{{ $barcodeLabel }}</flux:badge></div>
                    @empty
                        <x-state.empty :title="__('No barcode linked')" :description="__('Barcode identity can be managed from the catalog list.')" icon="tag">
                            @if ($canEdit)
                                <x-slot:action>
                                    <flux:button href="{{ route('catalog.products.edit', ['product' => $product]) }}" variant="subtle" icon="pencil">{{ __('Edit product card') }}</flux:button>
                                </x-slot:action>
                            @endif
                        </x-state.empty>
                    @endforelse
                </div>
            </flux:card>

            @if ($canViewCost)
                <flux:card class="space-y-3 p-5"><flux:heading size="lg">{{ __('Cost price') }}</flux:heading><flux:text>{{ $product->average_cost ?? __('Not provided') }}</flux:text></flux:card>
            @else
                <flux:callout variant="info" icon="lock-closed" title="{{ __('Cost field protected') }}">{{ __('Cost information is restricted to authorized purchasing and finance users.') }}</flux:callout>
            @endif

            <flux:card class="space-y-4 p-5">
                <div class="flex items-center justify-between gap-3"><flux:heading size="lg">{{ __('Protected media') }}</flux:heading><span class="text-xs text-text-muted">{{ $product->images->count() }}/5</span></div>
                <div class="grid grid-cols-2 gap-2">
                    @forelse ($product->images as $image)
                        <a class="catalog-media-thumb" href="{{ route('catalog.products.media', ['product' => $product, 'attachment' => $image->attachment]) }}" target="_blank" rel="noreferrer"><img src="{{ route('catalog.products.media', ['product' => $product, 'attachment' => $image->attachment]) }}" alt="{{ $image->attachment->original_filename }}" loading="lazy" /><span>{{ __($image->role === 'main' ? 'Main image' : 'Additional image') }}</span></a>
                    @empty
                        <div class="col-span-2"><x-state.empty :title="__('No images yet')" :message="__('Protected product media will appear here when linked.')" icon="photo" /></div>
                    @endforelse
                </div>
            </flux:card>

            @if ($canRemove)
                <flux:card class="space-y-4 p-5" data-product-removal>
                    <flux:heading size="lg">{{ __('Safe product removal') }}</flux:heading>
                    <p class="text-sm text-text-muted">{{ __('Product: :code — :name', ['code' => $product->item_code, 'name' => str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : $product->name_en]) }}</p>
                    @error('product')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                    @if (($removalReport['operational_total'] ?? 0) > 0)
                        <flux:callout variant="warning" icon="archive-box">{{ __('This product has :count operational or historical dependencies. It can only be archived; every dependency remains intact.', ['count' => $removalReport['operational_total']]) }}</flux:callout>
                        <flux:button variant="danger" wire:click="removeProduct(false)" wire:confirm="{{ __('Archive :code? It will be unavailable for new transactions, while all history remains unchanged.', ['code' => $product->item_code]) }}">{{ __('Archive product') }}</flux:button>
                    @else
                        <flux:callout variant="info" icon="trash">{{ __('No operational or historical dependency was found. Permanent deletion removes only this product and its non-historical catalog configuration.') }}</flux:callout>
                        <div class="flex flex-wrap gap-2"><flux:button variant="subtle" wire:click="removeProduct(false)" wire:confirm="{{ __('Archive :code? It will be unavailable for new transactions.', ['code' => $product->item_code]) }}">{{ __('Archive product') }}</flux:button><flux:button variant="danger" wire:click="removeProduct(true)" wire:confirm="{{ __('Permanently delete dependency-free product :code? This cannot be undone.', ['code' => $product->item_code]) }}">{{ __('Delete permanently') }}</flux:button></div>
                    @endif
                </flux:card>
            @endif

            <flux:callout variant="success" icon="shield-check" title="{{ __('Audit and scope protected') }}">{{ __('Product card, type, status, and media changes are recorded through the shared audit foundation. No stock or price side effect is created here.') }}</flux:callout>
        </aside>
    </div>
</x-app.page>
