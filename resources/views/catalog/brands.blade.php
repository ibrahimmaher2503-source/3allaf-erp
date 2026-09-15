<?php

use App\Modules\Catalog\Actions\SaveBrandAction;
use App\Modules\Catalog\Models\Brand;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Brand Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showBrandModal = false;

    public ?int $editingBrandId = null;

    public array $brandForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'status' => 'active'];

    public function mount(): void
    {
        Gate::authorize('products_categories_brands.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateBrandModal(): void
    {
        Gate::authorize('products_categories_brands.create');
        $this->editingBrandId = null;
        $this->brandForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'status' => 'active'];
        $this->resetValidation();
        $this->showBrandModal = true;
    }

    public function openEditBrandModal(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $brand = Brand::query()->findOrFail($id);
        $this->editingBrandId = $brand->id;
        $this->brandForm = ['code' => $brand->code, 'name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'status' => $brand->status];
        $this->resetValidation();
        $this->showBrandModal = true;
    }

    public function generateBrandCode(): void
    {
        Gate::authorize('products_categories_brands.create');

        if ($this->editingBrandId !== null) {
            return;
        }

        for ($number = 1; ; $number++) {
            $code = sprintf('BRD-%04d', $number);

            if (! Brand::query()->where('code', $code)->exists()) {
                $this->brandForm['code'] = $code;
                break;
            }
        }
    }

    public function saveBrand(SaveBrandAction $action): void
    {
        Gate::authorize($this->editingBrandId ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        $validated = $this->validate([
            'brandForm.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('brands', 'code')->ignore($this->editingBrandId)],
            'brandForm.name_ar' => ['required', 'string', 'max:255'],
            'brandForm.name_en' => ['required', 'string', 'max:255'],
            'brandForm.status' => ['required', 'in:active,inactive'],
        ])['brandForm'];

        try {
            $action->execute($validated, $this->editingBrandId);
            Flux::toast(variant: 'success', text: $this->editingBrandId ? __('Brand updated successfully.') : __('Brand created successfully.'));
            $this->showBrandModal = false;
        } catch (Throwable $exception) {
            $this->addError('brandForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function toggleBrandStatus(int $id, SaveBrandAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');
        $brand = Brand::query()->findOrFail($id);

        try {
            $action->execute($brand->only(['code', 'name_ar', 'name_en']) + ['status' => $brand->status === 'active' ? 'inactive' : 'active'], $id);
            Flux::toast(variant: 'success', text: __('Brand status updated successfully.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function bulkToggleBrandStatus(SaveBrandAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $brand = Brand::query()->findOrFail($id);
                $action->execute($brand->only(['code', 'name_ar', 'name_en']) + ['status' => $brand->status === 'active' ? 'inactive' : 'active'], $id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Brand status updated for :count records.', ['count' => $count]));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function render()
    {
        $query = Brand::query()->withCount('products');
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope->where('code', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('catalog.brands', ['brands' => $query->orderBy('code')->paginate(15)]);
    }
}; ?>

<x-app.page
    :title="__('Brand Masters')"
    :description="__('Maintain bilingual brand identity with dependency-aware status changes.')"
    max-width="7xl"
    class="catalog-screen"
    data-guide="brands-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar>
            @can('products_categories_brands.create')
                <flux:button href="{{ route('catalog.reference-import') }}" variant="subtle">{{ __('Excel import') }}</flux:button>
                <flux:button href="{{ route('catalog.data-exchange.template', ['type' => 'brands']) }}" variant="subtle" icon="arrow-down-tray">{{ __('Import template') }}</flux:button>
                <flux:button icon="plus" variant="primary" wire:click="openCreateBrandModal" data-guide="brands-add-action">{{ __('Add brand') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <flux:callout class="catalog-scope-note" variant="info" icon="tag" title="{{ __('Brand catalog') }}">{{ __('Manage brand identity here. Supplier terms, product media, and product details are managed in their related workspaces.') }}</flux:callout>
    @if ($errors->any())<flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Brand action could not be completed') }}"><ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>@endif

    <div id="brands-filters" class="catalog-filter-card scroll-mt-24 rounded-xl p-4 sm:p-5" data-guide="brands-filters"><div class="grid grid-cols-1 gap-3 md:grid-cols-2"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search brands')" :placeholder="__('Code or Arabic/English name...')" /><flux:select wire:model.live="statusFilter" :label="__('Status')"><flux:select.option value="all">{{ __('All statuses') }}</flux:select.option><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select></div></div>
    <div wire:loading.flex wire:target="search,statusFilter,gotoPage,previousPage,nextPage" role="status" aria-live="polite" class="catalog-loading"><flux:icon name="arrow-path" class="size-4 animate-spin" />{{ __('Loading brands...') }}</div>

    @if ($brands->isEmpty())
        <flux:card class="space-y-3 p-10 text-center" data-guide="brands-empty"><flux:icon name="tag" class="mx-auto size-12 text-zinc-400" /><flux:heading size="lg">{{ __('No brands found') }}</flux:heading><flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('Create a brand before assigning it to a product.') }}</flux:text>@can('products_categories_brands.create')<div class="flex justify-center pt-2"><flux:button type="button" variant="primary" icon="plus" wire:click="openCreateBrandModal">{{ __('Create brand') }}</flux:button></div>@endcan</flux:card>
    @else
        <div class="catalog-table-frame" data-guide="brands-table"><flux:table aria-label="{{ __('Brand masters') }}"><flux:table.columns><flux:table.column>{{ __('Code') }}</flux:table.column><flux:table.column>{{ __('Brand name') }}</flux:table.column><flux:table.column>{{ __('Products') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column><flux:table.column>{{ __('Actions') }}</flux:table.column></flux:table.columns><flux:table.rows>@foreach ($brands as $brand)<flux:table.row :key="$brand->id"><flux:table.cell><span class="catalog-code-chip">{{ $brand->code }}</span></flux:table.cell><flux:table.cell><div class="font-medium text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') ? $brand->name_ar : $brand->name_en }}</div><div class="catalog-secondary-line">{{ str_starts_with(app()->getLocale(), 'ar') ? $brand->name_en : $brand->name_ar }}</div></flux:table.cell><flux:table.cell><span class="font-mono text-xs text-text-muted">{{ number_format($brand->products_count) }}</span></flux:table.cell><flux:table.cell><flux:badge size="sm" :color="$brand->status === 'active' ? 'emerald' : 'zinc'">{{ __($brand->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge></flux:table.cell><flux:table.cell class="whitespace-nowrap">@can('products_categories_brands.edit')<div class="catalog-actions"><x-actions.button semantic="edit" :label="__('Edit')" size="xs" wire:click="openEditBrandModal({{ $brand->id }})" /><x-actions.button :semantic="$brand->status === 'active' ? 'disable' : 'restore'" :icon="$brand->status === 'active' ? 'pause' : 'play'" :label="$brand->status === 'active' ? __('Deactivate') : __('Activate')" size="xs" wire:click="toggleBrandStatus({{ $brand->id }})" /></div>@else<span class="text-xs font-medium text-text-muted">{{ __('View only') }}</span>@endcan</flux:table.cell></flux:table.row>@endforeach</flux:table.rows></flux:table></div>
        <div data-guide="brands-pagination">{{ $brands->links() }}</div>
    @endif

    <flux:modal wire:model="showBrandModal" class="max-w-xl"><div class="space-y-5"><div class="rounded-xl border border-border bg-surface-muted/40 p-4"><flux:heading size="lg">{{ $editingBrandId ? __('Edit brand') : __('Create brand') }}</flux:heading><flux:subheading class="mt-1">{{ __('Both Arabic and English names are required for catalog identity.') }}</flux:subheading></div><form wire:submit="saveBrand" novalidate class="space-y-4"><section class="space-y-4 rounded-xl border border-border p-4"><flux:heading size="sm">{{ __('Brand details') }}</flux:heading><div class="flex items-end gap-2"><flux:input class="min-w-0 flex-1" wire:model="brandForm.code" :label="__('Brand code')" required :disabled="$editingBrandId !== null" />@if ($editingBrandId === null)<flux:button type="button" variant="subtle" icon="sparkles" wire:click="generateBrandCode" wire:loading.attr="disabled" wire:target="generateBrandCode" title="{{ __('Generate code automatically') }}" aria-label="{{ __('Generate code automatically') }}">{{ __('Generate automatically') }}</flux:button>@endif</div><div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="brandForm.name_ar" :label="__('Arabic name')" required /><flux:input wire:model="brandForm.name_en" :label="__('English name')" required /></div></section><section class="rounded-xl border border-border p-4"><flux:select wire:model="brandForm.status" :label="__('Status')" required><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select></section><div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end"><flux:button type="button" variant="subtle" wire:click="$set('showBrandModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBrand">{{ __('Save') }}</flux:button></div></form></div></flux:modal>
</x-app.page>
