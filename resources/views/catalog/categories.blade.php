<?php

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Models\Category;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Category Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showCategoryModal = false;

    public ?int $editingCategoryId = null;

    public array $categoryForm = [
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'parent_id' => '',
        'status' => 'active',
        'sort_order' => 0,
    ];

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

    public function openCreateCategoryModal(): void
    {
        Gate::authorize('products_categories_brands.create');
        $this->editingCategoryId = null;
        $this->categoryForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'parent_id' => '', 'status' => 'active', 'sort_order' => 0];
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function openEditCategoryModal(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $category = Category::query()->findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryForm = [
            'code' => $category->code,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'parent_id' => (string) ($category->parent_id ?? ''),
            'status' => $category->status,
            'sort_order' => $category->sort_order,
        ];
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function generateCategoryCode(): void
    {
        Gate::authorize('products_categories_brands.create');

        if ($this->editingCategoryId !== null) {
            return;
        }

        for ($number = 1; ; $number++) {
            $code = sprintf('CAT-%04d', $number);

            if (! Category::query()->where('code', $code)->exists()) {
                $this->categoryForm['code'] = $code;
                break;
            }
        }
    }

    public function saveCategory(SaveCategoryAction $action): void
    {
        Gate::authorize($this->editingCategoryId ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $validated = $this->validate([
            'categoryForm.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('categories', 'code')->ignore($this->editingCategoryId)],
            'categoryForm.name_ar' => ['required', 'string', 'max:255'],
            'categoryForm.name_en' => ['nullable', 'string', 'max:255'],
            'categoryForm.parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'categoryForm.status' => ['required', 'in:active,inactive'],
            'categoryForm.sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [], [
            'categoryForm.code' => __('Category code'),
            'categoryForm.name_ar' => __('Arabic name'),
            'categoryForm.name_en' => __('English name (optional)'),
            'categoryForm.parent_id' => __('Parent category'),
            'categoryForm.status' => __('Status'),
            'categoryForm.sort_order' => __('Display order'),
        ])['categoryForm'];

        try {
            $action->execute($validated, $this->editingCategoryId);
            Flux::toast(variant: 'success', text: $this->editingCategoryId ? __('Category updated successfully.') : __('Category created successfully.'));
            $this->showCategoryModal = false;
        } catch (Throwable $exception) {
            $this->addError('categoryForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function toggleCategoryStatus(int $id, SaveCategoryAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');
        $category = Category::query()->findOrFail($id);

        try {
            $action->execute($category->only(['code', 'name_ar', 'name_en', 'parent_id', 'sort_order']) + ['status' => $category->status === 'active' ? 'inactive' : 'active'], $id);
            Flux::toast(variant: 'success', text: __('Category status updated successfully.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function bulkToggleCategoryStatus(SaveCategoryAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $category = Category::query()->findOrFail($id);
                $action->execute($category->only(['code', 'name_ar', 'name_en', 'parent_id', 'sort_order']) + ['status' => $category->status === 'active' ? 'inactive' : 'active'], $id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Category status updated for :count records.', ['count' => $count]));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function render()
    {
        $query = Category::query()->with('parent');
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope->where('code', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('catalog.categories', [
            'categories' => $query->hierarchical()->paginate(15),
            'parentOptions' => Category::query()->where('status', 'active')->when($this->editingCategoryId, fn ($q) => $q->whereKeyNot($this->editingCategoryId))->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Product categories')"
    :description="__('Organize product categories and subcategories clearly.')"
    max-width="7xl"
    class="catalog-screen"
    data-guide="categories-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar>
            @can('products_categories_brands.create')
                <flux:button href="{{ route('catalog.reference-import') }}" wire:navigate variant="subtle">{{ __('Excel import') }}</flux:button>
                <flux:button href="{{ route('catalog.data-exchange.template', ['type' => 'categories']) }}" variant="subtle" icon="arrow-down-tray">{{ __('Import template') }}</flux:button>
                <flux:button icon="plus" variant="primary" wire:click="openCreateCategoryModal" data-guide="categories-add-action">{{ __('Add category') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <flux:text class="text-sm text-text-muted" data-guide="categories-order-help">
        {{ __('Sort each category within its main category so the list stays organized.') }}
    </flux:text>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Category action could not be completed') }}">
            <ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    <div id="categories-filters" class="catalog-filter-card scroll-mt-24 rounded-xl border border-border-subtle bg-surface-muted/40 p-4 sm:p-5" data-guide="categories-filters">
        <div class="catalog-filter-heading mb-3">
            <div>
                <flux:heading size="sm">{{ __('Search and filter') }}</flux:heading>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_14rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search categories')" :placeholder="__('Search by code or name...')" />
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div wire:loading.flex wire:target="search,statusFilter,gotoPage,previousPage,nextPage" role="status" aria-live="polite" class="catalog-loading"><flux:icon name="arrow-path" class="size-4 animate-spin" />{{ __('Loading hierarchy...') }}</div>

    @if ($categories->isEmpty())
        <flux:card class="space-y-3 p-10 text-center" data-guide="categories-empty">
            <flux:icon name="squares-2x2" class="mx-auto size-12 text-zinc-400" />
            <flux:heading size="lg">{{ __('No categories found') }}</flux:heading>
            <flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('Create a root category, then add child categories from the same form.') }}</flux:text>
            @can('products_categories_brands.create')
                <div class="flex justify-center pt-2"><flux:button type="button" variant="primary" icon="plus" wire:click="openCreateCategoryModal">{{ __('Create root category') }}</flux:button></div>
            @endcan
        </flux:card>
    @else
        <div class="catalog-table-frame" data-guide="categories-table">
            <flux:table aria-label="{{ __('Category hierarchy') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Category name') }}</flux:table.column>
                    <flux:table.column>{{ __('Parent / level') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($categories as $category)
                        <flux:table.row :key="$category->id">
                            <flux:table.cell class="whitespace-nowrap"><span class="catalog-code-chip">{{ $category->code }}</span></flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-start gap-2" style="padding-inline-start: calc({{ min((int) ($category->hierarchy_depth ?? 0), 12) }} * 1.25rem)">
                                    <span class="catalog-tree-marker {{ $category->parent ? 'is-child' : '' }}" aria-hidden="true">{{ $category->parent ? '└─' : '' }}</span>
                                    <div class="min-w-0"><div class="font-medium text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') || blank($category->name_en) ? $category->name_ar : $category->name_en }}</div>@if (filled($category->name_en) && str_starts_with(app()->getLocale(), 'ar'))<div class="catalog-secondary-line">{{ $category->name_en }}</div>@elseif (filled($category->name_ar) && ! str_starts_with(app()->getLocale(), 'ar'))<div class="catalog-secondary-line">{{ $category->name_ar }}</div>@endif</div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs">@if ($category->parent)<span class="text-text-muted">{{ __('Child of') }}</span> <span class="font-mono">{{ $category->parent->code }}</span>@else<flux:badge size="sm" color="zinc">{{ __('Root') }}</flux:badge>@endif</flux:table.cell>
                            <flux:table.cell><span class="font-mono text-xs text-text-muted">{{ $category->sort_order }}</span></flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$category->status === 'active' ? 'emerald' : 'zinc'">{{ __($category->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">@can('products_categories_brands.edit')<div class="catalog-actions"><x-actions.button semantic="edit" :label="__('Edit')" size="xs" wire:click="openEditCategoryModal({{ $category->id }})" /><x-actions.button :semantic="$category->status === 'active' ? 'disable' : 'restore'" :icon="$category->status === 'active' ? 'pause' : 'play'" :label="$category->status === 'active' ? __('Deactivate') : __('Activate')" size="xs" wire:click="toggleCategoryStatus({{ $category->id }})" onclick="if (! window.confirm(@js(__('Change category :name to :status? Its historical records are preserved.', ['name' => str_starts_with(app()->getLocale(), 'ar') || blank($category->name_en) ? $category->name_ar : $category->name_en, 'status' => $category->status === 'active' ? __('Inactive') : __('Active')])))) { event.preventDefault(); event.stopImmediatePropagation(); event.stopPropagation(); return false; }" /></div>@else<span class="text-xs font-medium text-text-muted">{{ __('View only') }}</span>@endcan</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
        <div data-guide="categories-pagination">{{ $categories->links() }}</div>
    @endif

    <flux:modal wire:model="showCategoryModal" class="max-w-xl">
        <div class="catalog-modal-section space-y-1"><flux:heading size="lg">{{ $editingCategoryId ? __('Edit category') : __('Create category') }}</flux:heading><flux:subheading>{{ __('Choose no parent for a root category. Server validation rejects self-parenting and descendant cycles.') }}</flux:subheading></div>
        <form wire:submit="saveCategory" novalidate class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2"><div class="flex items-end gap-2"><label for="category-code" class="sr-only">{{ __('Category code') }}</label><flux:input id="category-code" class="min-w-0 flex-1" wire:model="categoryForm.code" :label="__('Category code')" required :disabled="$editingCategoryId !== null" />@if ($editingCategoryId === null)<flux:button type="button" variant="subtle" icon="sparkles" wire:click="generateCategoryCode" wire:loading.attr="disabled" wire:target="generateCategoryCode" title="{{ __('Generate code automatically') }}" aria-label="{{ __('Generate code automatically') }}">{{ __('Generate automatically') }}</flux:button>@endif</div><label for="category-sort-order" class="sr-only">{{ __('Display order') }}</label><flux:input id="category-sort-order" wire:model="categoryForm.sort_order" type="number" min="0" :label="__('Display order')" required /></div>
            <div class="grid gap-4 md:grid-cols-2"><label for="category-name-ar" class="sr-only">{{ __('Arabic name') }}</label><flux:input id="category-name-ar" wire:model="categoryForm.name_ar" :label="__('Arabic name')" required /><label for="category-name-en" class="sr-only">{{ __('English name (optional)') }}</label><flux:input id="category-name-en" wire:model="categoryForm.name_en" :label="__('English name (optional)')" /><p class="text-xs text-text-muted md:col-start-2">{{ __('English name (optional)') }}</p></div>
            <flux:select wire:model="categoryForm.parent_id" :label="__('Parent category')"><flux:select.option value="">{{ __('No parent (root)') }}</flux:select.option>@foreach ($parentOptions as $parent)<flux:select.option :value="$parent->id">{{ $parent->code }} · {{ str_starts_with(app()->getLocale(), 'ar') || blank($parent->name_en) ? $parent->name_ar : $parent->name_en }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model="categoryForm.status" :label="__('Status')" required><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end"><flux:button type="button" variant="subtle" wire:click="$set('showCategoryModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCategory">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</x-app.page>
