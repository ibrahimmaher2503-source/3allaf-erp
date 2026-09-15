<?php

use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Branch Management')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';
    #[Url(as: 'setup', except: false)]
    public bool $setupMode = false;

    #[Url(as: 'section', except: 'branch-masters')]
    public string $section = 'branch-masters';

    // Branch Modal State
    public bool $showBranchModal = false;

    public ?int $editingBranchId = null;

    public ?int $savedBranchId = null;

    public ?string $savedBranchName = null;

    public array $branchForm = [
        'code' => '',
        'default_price_list_id' => '',
        'name_ar' => '',
        'name_en' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'status' => 'active',
        'policy_notes' => '',
    ];

    // Mapping Modal State
    public bool $showMappingModal = false;

    public ?int $mappingBranchId = null;

    public ?string $mappingBranchName = null;

    public ?int $selectedStoreId = null;

    public string $mappingApprovalNotes = '';

    // History Modal State
    public bool $showHistoryModal = false;

    public ?int $historyBranchId = null;

    public ?string $historyBranchName = null;

    public array $historyRecords = [];

    public function mount(): void
    {
        $this->section = 'branch-masters';
        Gate::authorize('branches_stores.view');
    }

    public function rendering(): void
    {
        if ($this->section !== 'branch-masters') {
            $this->section = 'branch-masters';
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

    public function openCreateBranchModal(): void
    {
        Gate::authorize('branches_stores.create');

        $this->editingBranchId = null;
        $this->branchForm = [
            'code' => '',
            'default_price_list_id' => '',
            'name_ar' => '',
            'name_en' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'status' => 'active',
            'policy_notes' => '',
        ];
        $this->resetValidation();
        $this->showBranchModal = true;
    }

    public function openEditBranchModal(int $id): void
    {
        Gate::authorize('branches_stores.edit');

        $branch = Branch::visibleTo(auth()->user())->findOrFail($id);
        $this->editingBranchId = $branch->id;
        $this->branchForm = [
            'code' => $branch->code,
            'default_price_list_id' => (string) ($branch->default_price_list_id ?? ''),
            'name_ar' => $branch->name_ar,
            'name_en' => $branch->name_en,
            'phone' => $branch->phone ?? '',
            'email' => $branch->email ?? '',
            'address' => $branch->address ?? '',
            'status' => $branch->status,
            'policy_notes' => $branch->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showBranchModal = true;
    }

    public function saveBranch(SaveBranchAction $action): void
    {
        Gate::authorize($this->editingBranchId ? 'branches_stores.edit' : 'branches_stores.create');

        $this->branchForm['code'] = strtoupper(trim($this->branchForm['code']));

        $validated = $this->validate([
            'branchForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('branches', 'code')->ignore($this->editingBranchId),
            ],
            'branchForm.name_ar' => ['required', 'string', 'max:255'],
            'branchForm.name_en' => ['required', 'string', 'max:255'],
            'branchForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'branchForm.email' => ['nullable', 'email', 'max:255'],
            'branchForm.address' => ['nullable', 'string'],
            'branchForm.status' => ['required', 'in:active,inactive'],
            'branchForm.policy_notes' => ['nullable', 'string'],
            'branchForm.default_price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('status', 'active'))],
        ], [], [
            'branchForm.code' => str_starts_with(app()->getLocale(), 'ar') ? 'رمز الفرع' : __('Branch Code'),
            'branchForm.name_ar' => str_starts_with(app()->getLocale(), 'ar') ? 'اسم الفرع بالعربية' : __('Arabic Name'),
            'branchForm.name_en' => str_starts_with(app()->getLocale(), 'ar') ? 'اسم الفرع بالإنجليزية' : __('English Name'),
            'branchForm.status' => str_starts_with(app()->getLocale(), 'ar') ? 'حالة الفرع' : __('Status'),
        ])['branchForm'];

        try {
            $wasCreating = $this->editingBranchId === null;
            $branch = $action->execute($validated, $this->editingBranchId);
            Flux::toast(variant: 'success', text: $this->editingBranchId ? __('Branch updated successfully.') : __('Branch created successfully.'));
            $this->showBranchModal = false;
            if ($wasCreating) {
                $this->savedBranchId = $branch->id;
                $this->savedBranchName = str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en;
            }
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function toggleBranchStatus(int $id, SaveBranchAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $action->toggleStatus($id);
            Flux::toast(variant: 'success', text: __('Branch status updated successfully.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function bulkToggleBranchStatus(SaveBranchAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $action->toggleStatus($id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Branch status updated for :count records.', ['count' => $count]));
        } catch (Exception $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function deleteBranch(int $id, PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            $branch = Branch::visibleTo(auth()->user())->findOrFail($id);
            $approvalAction->request('branch_delete', $branch->id, ['deleted' => true], $branch->getAttributes(), $branch->id);
            Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Branch deletion submitted for independent approval.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function openMappingModal(int $branchId): void
    {
        Gate::authorize('branches_stores.edit');

        $branch = Branch::visibleTo(auth()->user())->findOrFail($branchId);
        $this->mappingBranchId = $branch->id;
        $this->mappingBranchName = str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en;
        $this->selectedStoreId = $branch->activeSellingStoreMapping?->store_id;
        $this->mappingApprovalNotes = '';
        $this->resetValidation();
        $this->showMappingModal = true;
    }

    public function saveSellingStoreMapping(SaveBranchSellingStoreMappingAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        $validated = $this->validate([
            'selectedStoreId' => ['required', 'exists:stores,id'],
            'mappingApprovalNotes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute(
                branchId: $this->mappingBranchId,
                storeId: (int) $validated['selectedStoreId'],
                approvalNotes: $validated['mappingApprovalNotes']
            );
            Flux::toast(variant: 'success', text: __('Primary point of sale saved successfully.'));
            $this->showMappingModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function openHistoryModal(int $branchId): void
    {
        Gate::authorize('branches_stores.view');

        $branch = Branch::visibleTo(auth()->user())->with(['sellingStoreMappings.store', 'sellingStoreMappings.creator'])->findOrFail($branchId);
        $this->historyBranchId = $branch->id;
        $this->historyBranchName = str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en;
        $this->historyRecords = $branch->sellingStoreMappings
            // MySQL's default `timestamp` precision is whole seconds, so two
            // mappings created within the same second tie on `created_at` and previously fell
            // back to insertion order — showing history oldest-first instead
            // of newest-first. `id` is a monotonically increasing, unambiguous
            // proxy for creation order regardless of timestamp precision.
            ->sortByDesc('id')
            ->map(fn ($item) => [
                'id' => $item->id,
                'store_code' => $item->store?->code,
                'store_name' => str_starts_with(app()->getLocale(), 'ar') ? $item->store?->name_ar : $item->store?->name_en,
                'effective_from' => $item->effective_from?->format('Y-m-d H:i:s'),
                'effective_to' => $item->effective_to?->format('Y-m-d H:i:s') ?? __('Current Active'),
                'status' => $item->status,
                'approval_notes' => $item->approval_notes,
                'creator_name' => $item->creator?->name ?? __('System'),
            ])
            ->values()
            ->toArray();

        $this->showHistoryModal = true;
    }

    public function render()
    {
        $query = Branch::visibleTo(auth()->user())
            ->with('activeSellingStoreMapping.store')
            ->withCount([
                'warehouses as active_warehouse_count' => fn ($warehouseQuery) => $warehouseQuery
                    ->whereColumn('stores.company_id', 'branches.company_id'),
            ]);

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn ($scope) => $scope
                ->where('code', 'like', $term)
                ->orWhere('name_ar', 'like', $term)
                ->orWhere('name_en', 'like', $term));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('platform.admin.branches', [
            'branches' => $query->orderBy('code')->paginate(10),
            'activePriceLists' => PriceList::query()->where('company_id', Company::query()->where('status', 'active')->value('id'))->where('status', 'active')->orderBy('list_number')->get(),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Branch Masters')"
    :description="__('Manage branch identity, contacts, status, and default selling price list.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="branches-header"
>
    @if ($savedBranchId)
        <flux:callout variant="success" icon="check-circle" title="{{ __('Branch created successfully.') }}">
            <p>{{ __('The branch :name is saved.', ['name' => $savedBranchName]) }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button :href="route('admin.branches')" size="sm" variant="subtle" wire:navigate>{{ __('Return to Branches') }}</flux:button>
                <flux:button :href="route('initial-setup')" size="sm" variant="subtle" wire:navigate>{{ __('Return to Setup Center') }}</flux:button>
            </div>
        </flux:callout>
    @endif
    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2" data-guide="branches-workspace-navigation">
            @if ($section === 'branch-masters')
                <x-context-help :title="__('Branch help')" :label="__('Open branch help')">
                    <ul>
                        <li>{{ __('A branch is the business location that owns its warehouse and point-of-sale access context.') }}</li>
                        <li>{{ __('Create the branch identity here. Manage warehouses and points of sale from their own authorized screen.') }}</li>
                        <li>{{ __('Company-wide defaults are managed in General Settings, not on branch records.') }}</li>
                        <li>{{ __('User access is assigned from branch to warehouse or point of sale, then limited by role permissions.') }}</li>
                    </ul>
                </x-context-help>
                <div data-guide="branch-masters-actions">
                    <x-tables.resource-toolbar>
                        @can('branches_stores.create')
                            <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateBranchModal" data-guide="branches-add-action">{{ __('Add Branch') }}</flux:button>
                        @endcan
                    </x-tables.resource-toolbar>
                </div>
            @endif
        </div>
    </x-slot:actions>

    @if ($section === 'selling-store-mapping')
        <section data-branch-section="selling-store-mapping" data-guide="selling-store-mapping-workspace" class="space-y-4">
            <flux:card class="space-y-4 p-4 sm:p-5">
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table data-guide="selling-store-mapping-table" class="w-full">
                    <flux:table.columns>
                        <flux:table.column class="min-w-44">{{ __('Branch') }}</flux:table.column>
                        <flux:table.column class="min-w-52">{{ __('Primary point of sale') }}</flux:table.column>
                        <flux:table.column class="w-28">{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="w-40 text-end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($branches as $branch)
                            <flux:table.row :key="$branch->id">
                                <flux:table.cell class="whitespace-normal align-top">
                                    <div class="space-y-1">
                                        <div class="font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</div>
                                        <div class="font-mono text-xs text-zinc-500">{{ $branch->code }}</div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-normal align-top">
                                    @if ($branch->activeSellingStoreMapping && $branch->activeSellingStoreMapping->store)
                                        <div class="space-y-1">
                                            <div class="font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? $branch->activeSellingStoreMapping->store->name_ar : $branch->activeSellingStoreMapping->store->name_en }}</div>
                                            <div class="font-mono text-xs text-zinc-500">{{ $branch->activeSellingStoreMapping->store->code }}</div>
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            <span class="text-zinc-500">{{ __('No primary point of sale') }}</span>
                                            @can('branches_stores.create')
                                                <div><flux:button href="{{ route('admin.stores') }}" wire:navigate size="xs" variant="subtle" icon="plus">{{ __('Create point of sale') }}</flux:button></div>
                                            @endcan
                                        </div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="align-top">
                                    <flux:badge size="sm" :color="$branch->activeSellingStoreMapping ? 'green' : 'amber'">{{ $branch->activeSellingStoreMapping ? __('Ready') : __('Needs setup') }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="align-top text-end">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                    @can('branches_stores.edit')
                                        <x-actions.button semantic="assign" :label="__('Assign')" size="sm" variant="subtle" icon="arrows-right-left" wire:click="openMappingModal({{ $branch->id }})">{{ $branch->activeSellingStoreMapping ? __('Change') : __('Choose') }}</x-actions.button>
                                    @endcan
                                    <flux:button size="xs" variant="subtle" icon="clock" wire:click="openHistoryModal({{ $branch->id }})" title="{{ __('Mapping History') }}" aria-label="{{ __('Mapping History') }}" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center py-4">{{ __('No branches are available.') }}</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                </div>
                {{ $branches->links() }}
            </flux:card>
        </section>
    @else
    <section data-branch-section="branch-masters" data-guide="branch-masters-workspace" class="space-y-4">

    <!-- Filters & Controls -->
    <x-tables.filter-bar id="branches-filters" class="scroll-mt-24" data-guide="branches-filters">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :label="__('Search')"
                placeholder="{{ __('Search code or name...') }}"
                size="sm"
            />

            <flux:select wire:model.live="statusFilter" size="sm" :label="__('Status Filter')">
                <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
    </x-tables.filter-bar>

    <!-- Data Table -->

    @if ($branches->isEmpty())
        <flux:card class="p-8 text-center space-y-3" data-guide="branches-empty">
            <div class="flex justify-center">
                <flux:icon icon="building-office-2" class="size-12 text-zinc-400" />
            </div>
            <flux:heading level="3" size="lg">{{ __('No Branches Configured') }}</flux:heading>
            <flux:text class="text-zinc-500 max-w-md mx-auto">
                {{ __('Add a branch to organize stores and daily operations.') }}
            </flux:text>
            <div class="pt-2">
                @can('branches_stores.create')
                    <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateBranchModal">{{ __('Add Branch') }}</flux:button>
                @endcan
            </div>
        </flux:card>
    @else
        <div class="grid gap-3 md:hidden" aria-label="{{ __('Branch directory') }}">
            @foreach ($branches as $branch)
                <flux:card class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="font-semibold text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</div><div class="text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_en : $branch->name_ar }}</div><span class="mt-1 inline-block font-mono text-xs text-primary" dir="ltr">{{ $branch->code }}</span></div><flux:badge size="sm" color="{{ $branch->status === 'active' ? 'emerald' : 'zinc' }}">{{ __($branch->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge></div>
                    <dl class="grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-text-muted">{{ __('Phone') }}</dt><dd dir="ltr">{{ $branch->phone ?: '—' }}</dd></div><div><dt class="text-xs text-text-muted">{{ __('Warehouses') }}</dt><dd>{{ $branch->active_warehouse_count }}</dd></div></dl>
                    <div class="flex flex-wrap gap-2 border-t border-border pt-3">
                        @can('branches_stores.edit')
                            <x-actions.button semantic="edit" :label="__('Edit')" size="sm" variant="subtle" color="blue" icon="pencil" wire:click="openEditBranchModal({{ $branch->id }})">{{ __('Edit') }}</x-actions.button>
                            @if ($branch->status === 'active')
                                <x-actions.button semantic="disable" :label="__('Deactivate')" size="sm" variant="subtle" color="amber" icon="pause" wire:click="toggleBranchStatus({{ $branch->id }})" wire:confirm="{{ __('Confirm this action?') }}">{{ __('Deactivate') }}</x-actions.button>
                            @else
                                <x-actions.button semantic="restore" :label="__('Activate')" size="sm" variant="subtle" color="green" icon="play" wire:click="toggleBranchStatus({{ $branch->id }})">{{ __('Activate') }}</x-actions.button>
                            @endif
                        @endcan
                        @can('branches_stores.logical_delete')
                            <x-actions.button semantic="delete" :label="__('Delete')" size="sm" variant="subtle" color="red" icon="trash" wire:click="deleteBranch({{ $branch->id }})" wire:confirm="{{ __('Confirm this action?') }}">{{ __('Delete') }}</x-actions.button>
                        @endcan
                    </div>
                </flux:card>
            @endforeach
        </div>
        <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 md:block" aria-label="{{ __('Branch directory table') }}">
            <flux:table data-guide="branch-masters-table" class="w-full">
            <flux:table.columns>
                <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Branch identity') }}</span></flux:table.column>
                <flux:table.column class="min-w-32 whitespace-nowrap">{{ __('Phone') }}</flux:table.column>
                <flux:table.column class="min-w-32 whitespace-nowrap">{{ __('Warehouses') }}</flux:table.column>
                <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="min-w-44 whitespace-nowrap text-end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($branches as $branch)
                    <flux:table.row :key="$branch->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $branch->name_ar }}</div>
                            <div class="text-xs text-zinc-500">{{ $branch->name_en }}</div>
                            <span class="mt-1 inline-block rounded bg-primary/10 px-1.5 py-0.5 font-mono text-xs text-primary" dir="ltr">{{ $branch->code }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="text-xs">
                            {{ $branch->phone ?: '—' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" variant="subtle">
                                <span class="inline-flex items-center gap-1">
                                    <span data-testid="branch-warehouse-count-{{ $branch->id }}">{{ $branch->active_warehouse_count }}</span>
                                    <span data-testid="branch-warehouse-label-{{ $branch->id }}">{{ (int) $branch->active_warehouse_count === 1 ? __('Warehouse') : __('Warehouses') }}</span>
                                </span>
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($branch->status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top" class="font-medium">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-end space-x-1 rtl:space-x-reverse">
                            @can('branches_stores.edit')
                                <x-actions.button semantic="edit" :label="__('Edit')" size="xs" variant="subtle" icon="pencil" wire:click="openEditBranchModal({{ $branch->id }})" title="{{ __('Edit') }}" />
                            @endcan

                            @can('branches_stores.edit')
                                @if ($branch->status === 'active')
                                <x-actions.button semantic="disable" :label="__('Deactivate')" size="xs" variant="subtle" icon="pause" wire:click="toggleBranchStatus({{ $branch->id }})" wire:confirm="{{ __('Confirm this action?') }}" title="{{ __('Deactivate') }}" />
                                @else
                                    <x-actions.button semantic="restore" :label="__('Activate')" size="xs" variant="subtle" icon="play" wire:click="toggleBranchStatus({{ $branch->id }})" title="{{ __('Activate') }}" />
                                @endif
                            @endcan
                            @can('branches_stores.logical_delete')
                                <x-actions.button semantic="delete" :label="__('Delete')" size="xs" variant="subtle" color="red" icon="trash" wire:click="deleteBranch({{ $branch->id }})" wire:confirm="{{ __('Confirm this action?') }}" title="{{ __('Delete') }}" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
            </flux:table>
        </div>

        <div class="pt-4" data-guide="branches-pagination">
            {{ $branches->links() }}
        </div>
    @endif

    <!-- Create / Edit Branch Modal -->
    <flux:modal wire:model="showBranchModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingBranchId ? __('Edit Branch Master') : __('Create Branch Master') }}</flux:heading>
            <flux:subheading>{{ __('Define the branch code, bilingual name, contact information, and operating policies.') }}</flux:subheading>
        </div>

        <form wire:submit="saveBranch" novalidate class="space-y-4" data-guide="branch-master-form">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branchForm.code"
                    :label="__('Branch Code')"
                    placeholder="BR-01"
                    required
                />

                <flux:select wire:model="branchForm.status" :label="__('Status')" required>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branchForm.name_ar"
                    :label="__('Arabic Name')"
                    placeholder="فرع الرياض الرئيسي"
                    required
                />

                <flux:input
                    wire:model="branchForm.name_en"
                    :label="__('English Name')"
                    placeholder="Riyadh Main Branch"
                    required
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branchForm.phone"
                    :label="__('Phone')"
                    :placeholder="__('e.g. 01012345678 or +20 1012345678')"
                    dir="ltr"
                />

                <flux:input
                    wire:model="branchForm.email"
                    :label="__('Email')"
                    type="email"
                    placeholder="branch@toyjoy.com"
                />

            </div>

            <flux:textarea
                wire:model="branchForm.address"
                :label="__('Address')"
                placeholder="{{ __('Physical branch address details...') }}"
                rows="2"
            />
            <flux:select wire:model="branchForm.default_price_list_id" :label="__('Default price list for Sales Outlets')"><flux:select.option value="">{{ __('Base Price List 0') }}</flux:select.option>@foreach($activePriceLists as $priceList)<flux:select.option :value="$priceList->id">{{ $priceList->code }} · {{ str_starts_with(app()->getLocale(), 'ar')?$priceList->name_ar:$priceList->name_en }}</flux:select.option>@endforeach</flux:select>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showBranchModal', false)">{{ __('Cancel') }}</x-actions.button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBranch"><span wire:loading.remove wire:target="saveBranch">{{ __('Save Branch') }}</span><span wire:loading wire:target="saveBranch">{{ __('Saving...') }}</span></x-actions.button>
            </div>
        </form>
    </flux:modal>
    </section>
    @endif

    <!-- Choose primary point of sale modal -->
    @if ($section === 'selling-store-mapping')
    <flux:modal wire:model="showMappingModal" class="md:w-[42rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ $selectedStoreId ? __('Change primary point of sale') : __('Choose primary point of sale') }}</flux:heading>
            <flux:subheading>{{ $mappingBranchName }}</flux:subheading>
        </div>

        <?php
    $availableSellingStores = Store::visibleTo(auth()->user())
        ->where('type', 'selling')
        ->where('status', 'active')
        ->when($mappingBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
        ->orderBy('code')
        ->get();
?>

        @if ($availableSellingStores->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="space-y-2">
                    <p class="font-semibold">{{ __('No point of sale is available for this branch.') }}</p>
                    <p class="text-sm">{{ __('Create a point of sale for this branch first, then choose it here.') }}</p>
                    @can('branches_stores.create')
                        <flux:button href="{{ route('admin.stores') }}" size="sm" variant="primary" wire:navigate>{{ __('Create point of sale') }}</x-actions.button>
                    @endcan
                </div>
            </flux:callout>
        @endif
        <form wire:submit="saveSellingStoreMapping" class="space-y-4">
            <flux:select wire:model="selectedStoreId" :label="__('Point of sale')" :disabled="$availableSellingStores->isEmpty()" required>
                <flux:select.option value="">{{ __('Select a point of sale...') }}</flux:select.option>
                @foreach ($availableSellingStores as $st)
                    <flux:select.option :value="$st->id">
                        {{ $st->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $st->name_ar : $st->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            @if ($availableSellingStores->isNotEmpty())
                <p class="text-xs text-zinc-500">{{ __('Only active points of sale for this branch are shown.') }}</p>
            @endif

            <flux:textarea
                wire:model="mappingApprovalNotes"
                :label="__('Notes (optional)')"
                placeholder="{{ __('Add a short note for this change.') }}"
                rows="3"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showMappingModal', false)">{{ __('Cancel') }}</x-actions.button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveSellingStoreMapping"><span wire:loading.remove wire:target="saveSellingStoreMapping">{{ __('Save') }}</span><span wire:loading wire:target="saveSellingStoreMapping">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- History Drawer / Modal -->
    <flux:modal wire:model="showHistoryModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Primary point of sale history') }}</flux:heading>
            <flux:subheading>{{ $historyBranchName }}</flux:subheading>
        </div>

        @if (empty($historyRecords))
            <flux:text class="text-center py-4 text-zinc-500">{{ __('No primary point of sale history for this branch.') }}</flux:text>
        @else
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                @foreach ($historyRecords as $record)
                    <div class="p-3 border rounded-lg border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono font-bold text-zinc-800 dark:text-zinc-200">{{ $record['store_code'] }} - {{ $record['store_name'] }}</span>
                            @if ($record['status'] === 'active')
                                <flux:badge size="sm" color="emerald">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Superceded') }}</flux:badge>
                            @endif
                        </div>

                        <div class="text-xs text-zinc-500 flex justify-between">
                            <span>{{ __('From:') }} {{ $record['effective_from'] }}</span>
                            <span>{{ __('To:') }} {{ $record['effective_to'] }}</span>
                        </div>

                        @if ($record['approval_notes'])
                            <div class="text-xs italic text-zinc-600 dark:text-zinc-400 pt-1">
                                "{{ $record['approval_notes'] }}"
                            </div>
                        @endif

                        <div class="text-[11px] text-zinc-400 text-end">
                            {{ __('Updated by:') }} {{ $record['creator_name'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end pt-2">
            <flux:button variant="subtle" wire:click="$set('showHistoryModal', false)">{{ __('Close') }}</flux:button>
        </div>
    </flux:modal>
    @endif
</x-app.page>
