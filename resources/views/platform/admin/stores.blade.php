<?php

use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Store & Inventory Mapping Masters')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'branch', except: 'all')]
    public string $branchFilter = 'all';

    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';
    #[Url(as: 'setup', except: false)]
    public bool $setupMode = false;

    // Store Modal State
    public bool $showStoreModal = false;

    public ?int $editingStoreId = null;

    public array $storeForm = [
        'branch_id' => '',
        'price_list_id' => '',
        'code' => '',
        'type' => 'selling',
        'name_ar' => '',
        'name_en' => '',
        'status' => 'active',
        'allows_negative_stock' => false,
        'policy_notes' => '',
    ];

    // Mapping Modal State
    public bool $showStoreMappingModal = false;

    public bool $showArchiveModal = false;

    public ?int $archiveStoreId = null;

    /** @var array<string, string> */
    public array $archiveStoreContext = [];

    public ?int $mappingStoreId = null;

    public ?string $mappingStoreName = null;

    public ?string $mappingBranchName = null;

    public ?int $selectedBranchId = null;

    public string $mappingApprovalNotes = '';

    public function mount(): void
    {
        Gate::authorize('branches_stores.view');

        $branchId = request()->integer('branch_id');
        if ($branchId > 0 && Branch::visibleTo(auth()->user())->whereKey($branchId)->where('status', 'active')->exists()) {
            $this->branchFilter = (string) $branchId;
            $this->storeForm['branch_id'] = (string) $branchId;
            if (request()->boolean('create') && auth()->user()?->can('branches_stores.create')) {
                $this->showStoreModal = true;
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateStoreModal(): void
    {
        Gate::authorize('branches_stores.create');

        $this->editingStoreId = null;
        $this->storeForm = [
            'branch_id' => '',
            'price_list_id' => '',
            'code' => '',
            'type' => 'selling',
            'name_ar' => '',
            'name_en' => '',
            'status' => 'active',
            'allows_negative_stock' => false,
            'policy_notes' => '',
        ];
        $this->resetValidation();
        $this->showStoreModal = true;
    }

    public function openEditStoreModal(int $id): void
    {
        Gate::authorize('branches_stores.edit');

        $store = Store::visibleTo(auth()->user())->findOrFail($id);
        $this->editingStoreId = $store->id;
        $this->storeForm = [
            'branch_id' => (string) ($store->branch_id ?? ''),
            'price_list_id' => (string) ($store->price_list_id ?? ''),
            'code' => $store->code,
            'type' => $store->type,
            'name_ar' => $store->name_ar,
            'name_en' => $store->name_en,
            'status' => $store->status,
            'allows_negative_stock' => (bool) $store->allows_negative_stock,
            'policy_notes' => $store->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showStoreModal = true;
    }

    public function saveStore(SaveStoreAction $action): void
    {
        Gate::authorize($this->editingStoreId ? 'branches_stores.edit' : 'branches_stores.create');

        $validated = $this->validate([
            'storeForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('stores', 'code')->ignore($this->editingStoreId),
            ],
            'storeForm.branch_id' => [
                Rule::requiredIf($this->storeForm['type'] === 'selling'),
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'storeForm.price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'storeForm.type' => ['required', 'in:'.implode(',', SaveStoreAction::ALLOWED_TYPES)],
            'storeForm.name_ar' => ['required', 'string', 'max:255'],
            'storeForm.name_en' => ['required', 'string', 'max:255'],
            'storeForm.status' => ['required', 'in:active,inactive'],
            'storeForm.allows_negative_stock' => ['boolean'],
            'storeForm.policy_notes' => ['nullable', 'string'],
        ], [], [
            'storeForm.code' => str_starts_with(app()->getLocale(), 'ar') ? 'رمز المخزن' : __('Location Code'),
            'storeForm.branch_id' => str_starts_with(app()->getLocale(), 'ar') ? 'الفرع' : __('Branch'),
            'storeForm.name_ar' => str_starts_with(app()->getLocale(), 'ar') ? 'اسم الموقع بالعربية' : __('Arabic Name'),
            'storeForm.name_en' => str_starts_with(app()->getLocale(), 'ar') ? 'اسم الموقع بالإنجليزية' : __('English Name'),
            'storeForm.status' => str_starts_with(app()->getLocale(), 'ar') ? 'حالة الموقع' : __('Status'),
        ])['storeForm'];

        try {
            $action->execute($validated, $this->editingStoreId);
            Flux::toast(variant: 'success', text: $this->editingStoreId ? __('Location updated successfully.') : __('Location created successfully.'));
            $this->showStoreModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function toggleStoreStatus(int $id, SaveStoreAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $action->toggleStatus($id);
            Flux::toast(variant: 'success', text: __('Store status updated successfully.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function openArchiveModal(int $id, SaveStoreAction $storeAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            $store = Store::visibleTo(auth()->user())->with('branch')->findOrFail($id);
            if ($store->status !== 'active') {
                throw new \InvalidArgumentException(__('Only active locations can be submitted for archive approval.'));
            }
            $storeAction->assertStoreDependencyFree($store->id, 'archive', false);
            $this->archiveStoreId = $store->id;
            $this->archiveStoreContext = [
                'code' => $store->code,
                'name' => str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en,
                'type' => match ($store->type) {
                    'selling' => __('Point of Sale (POS)'),
                    'warehouse' => __('Warehouse'),
                    'party' => __('Service Center'),
                    'damaged' => __('Damaged & Defective Stock'),
                    'transit' => __('Stock in Transit'),
                    default => str($store->type)->headline(),
                },
                'branch' => $store->branch === null
                    ? __('Central / Unassigned')
                    : $store->branch->code.' — '.(str_starts_with(app()->getLocale(), 'ar') ? $store->branch->name_ar : $store->branch->name_en),
            ];
            $this->resetValidation();
            $this->showArchiveModal = true;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function requestArchive(PlatformSettingsApprovalAction $approvalAction, SaveStoreAction $storeAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            if ($this->archiveStoreId === null) {
                throw new \InvalidArgumentException(__('Select an active location before requesting archive approval.'));
            }
            $store = Store::visibleTo(auth()->user())->findOrFail($this->archiveStoreId);
            if ($store->status !== 'active') {
                throw new \InvalidArgumentException(__('Only active locations can be submitted for archive approval.'));
            }
            $storeAction->assertStoreDependencyFree($store->id, 'archive', false);
            $approvalAction->request('store_archive', $store->id, ['status' => 'inactive'], $store->getAttributes(), $store->branch_id, $store->id);
            $this->showArchiveModal = false;
            Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Archive request submitted for independent approval.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function openStoreMappingModal(int $storeId): void
    {
        Gate::authorize('branches_stores.edit');

        $store = Store::visibleTo(auth()->user())->with('branch')->findOrFail($storeId);
        if ($store->type !== 'selling') {
            Flux::toast(variant: 'danger', text: __('Only points of sale can be set as a branch primary point of sale.'));

            return;
        }

        if ($store->status !== 'active') {
            Flux::toast(variant: 'danger', text: __('Point of sale must be active to set it as primary.'));

            return;
        }

        if ($store->branch === null) {
            Flux::toast(variant: 'danger', text: __('Select a branch for this point of sale first.'));

            return;
        }

        $this->mappingStoreId = $store->id;
        $this->mappingStoreName = str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en;
        $this->mappingBranchName = str_starts_with(app()->getLocale(), 'ar') ? $store->branch->name_ar : $store->branch->name_en;
        $this->selectedBranchId = $store->branch->id;
        $this->mappingApprovalNotes = '';
        $this->resetValidation();
        $this->showStoreMappingModal = true;
    }

    public function saveStoreMapping(SaveBranchSellingStoreMappingAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        $validated = $this->validate([
            'selectedBranchId' => ['required', 'exists:branches,id'],
            'mappingApprovalNotes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute(
                branchId: (int) $validated['selectedBranchId'],
                storeId: $this->mappingStoreId,
                approvalNotes: $validated['mappingApprovalNotes']
            );
            Flux::toast(variant: 'success', text: __('Primary point of sale saved successfully.'));
            $this->showStoreMappingModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function render()
    {
        $query = Store::visibleTo(auth()->user())->with([
            'branch',
            'sellingStoreMappings' => fn ($mappingQuery) => $mappingQuery->where('status', 'active'),
        ]);
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope
                ->where('code', 'like', $like)
                ->orWhere('name_ar', 'like', $like)
                ->orWhere('name_en', 'like', $like));
        }

        if ($this->branchFilter !== 'all') {
            $this->branchFilter === 'unassigned'
                ? $query->whereNull('branch_id')
                : $query->where('branch_id', (int) $this->branchFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('platform.admin.stores', [
            'branchesList' => Branch::visibleTo(auth()->user())->orderBy('code')->get(),
            'activeBranchesList' => Branch::visibleTo(auth()->user())
                ->where('company_id', Company::query()->where('status', 'active')->value('id'))
                ->where('status', 'active')
                ->whereHas('company', fn ($query) => $query->where('status', 'active'))
                ->orderBy('code')
                ->get(),
            'activePriceLists' => PriceList::query()->where('company_id', Company::query()->where('status', 'active')->value('id'))->where('status', 'active')->orderBy('list_number')->get(),
            'stores' => $query->orderByRaw("COALESCE(NULLIF(name_ar,''), NULLIF(name_en,''), code)")->orderBy('code')->paginate(10),
            'pendingArchiveStoreIds' => ApprovalRecord::query()
                ->where('source_type', 'platform_settings')
                ->whereIn('requested_action', ['store_archive', 'store_delete'])
                ->where('approval_state', 'pending')
                ->whereNotNull('store_id')
                ->pluck('store_id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Warehouses & Points of Sale')"
    :description="__('Manage warehouses and selling stores, including physical inventory and POS operations, with their branch context.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="stores-header"
>
    <flux:callout variant="info" icon="information-circle"><strong>{{ __('Sales Outlet') }}:</strong> {{ __('Sales Outlet help') }}</flux:callout>
    <x-slot:actions>
        <x-tables.resource-toolbar>
            @can('branches_stores.create')
                <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateStoreModal" data-guide="stores-add-action">{{ __('Add warehouse') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <!-- Filters & Search -->
    <flux:card id="stores-filters" class="scroll-mt-24 space-y-4 p-4 sm:p-5" data-guide="stores-filters">
        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :label="__('Search')"
                placeholder="{{ __('Search warehouse code or name...') }}"
                size="sm"
            />

            <flux:select wire:model.live="branchFilter" size="sm" :label="__('Branch Filter')">
                <flux:select.option value="all">{{ __('All Branches') }}</flux:select.option>
                <flux:select.option value="unassigned">{{ __('Unassigned Branch') }}</flux:select.option>
                @foreach ($branchesList as $b)
                    <flux:select.option :value="$b->id">
                        {{ $b->code }} - {{ str_starts_with(app()->getLocale(), 'ar') ? $b->name_ar : $b->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="typeFilter" size="sm" :label="__('Warehouse Type')">
                <flux:select.option value="all">{{ __('All Warehouse Types') }}</flux:select.option>
                <flux:select.option value="selling">{{ __('Point of Sale (POS)') }}</flux:select.option>
                <flux:select.option value="warehouse">{{ __('Warehouse — physical inventory') }}</flux:select.option>
                <flux:select.option value="party">{{ __('Service Center') }}</flux:select.option>
                <flux:select.option value="damaged">{{ __('Damaged & Defective Stock — inventory routing') }}</flux:select.option>
                <flux:select.option value="transit">{{ __('Stock in Transit — inventory routing') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="statusFilter" size="sm" :label="__('Status Filter')">
                <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <!-- Stores Table -->

    @if ($stores->isEmpty())
        <flux:card class="p-8 text-center space-y-3" data-guide="stores-empty">
            <div class="flex justify-center">
                <flux:icon icon="building-storefront" class="size-12 text-zinc-400" />
            </div>
            <flux:heading level="3" size="lg">{{ __('No Warehouses Configured') }}</flux:heading>
            <flux:text class="text-zinc-500 max-w-md mx-auto">
                {{ __('Add a warehouse to make it available for inventory or selling-store operations.') }}
            </flux:text>
            <div class="pt-2">
                @can('branches_stores.create')
                    <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateStoreModal">{{ __('Add warehouse') }}</flux:button>
                @endcan
            </div>
        </flux:card>
    @else
        <div class="space-y-3 md:hidden" data-guide="stores-cards" aria-label="{{ __('Warehouse list') }}">
            @foreach ($stores as $st)
                <?php
                    $isPendingArchive = in_array($st->id, $pendingArchiveStoreIds, true);
                ?>
                <flux:card wire:key="store-card-{{ $st->id }}" class="space-y-4 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $st->name_ar }}</div>
                            <div class="truncate text-sm text-zinc-500">{{ $st->name_en }}</div>
                            <div class="mt-1 font-mono text-xs text-zinc-500">{{ $st->code }}</div>
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-1">
                            @switch($st->type)
                                @case('selling')
                                    <flux:badge size="sm" variant="subtle" color="zinc"><span data-testid="store-type-{{ $st->id }}">{{ __('Point of Sale (POS)') }}</span></flux:badge>
                                    @break
                                @case('warehouse')
                                    <flux:badge size="sm" variant="subtle" color="zinc"><span data-testid="store-type-{{ $st->id }}">{{ __('Warehouse') }}</span></flux:badge>
                                    @break
                                @case('party')
                                    <flux:badge size="sm" variant="subtle" color="zinc">{{ __('Service Center') }}</flux:badge>
                                    @break
                                @case('damaged')
                                    <flux:badge size="sm" variant="subtle" color="rose">{{ __('Damaged & Defective Stock') }}</flux:badge>
                                    @break
                                @case('transit')
                                    <flux:badge size="sm" variant="subtle" color="amber"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Stock in Transit') }}</span></flux:badge>
                                    @break
                                @default
                                    <flux:badge size="sm" variant="subtle">{{ $st->type }}</flux:badge>
                            @endswitch
                            @if ($st->sellingStoreMappings->isNotEmpty())
                                <flux:badge size="sm" color="emerald">{{ __('Primary') }}</flux:badge>
                            @endif
                            @if ($isPendingArchive)
                                <flux:badge size="sm" color="amber" inset="top"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Pending archive approval') }}</span></flux:badge>
                            @elseif ($st->status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top" class="!bg-emerald-700 !text-white">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __('Inactive / Archived') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-3 border-y border-zinc-200 py-3 text-sm dark:border-zinc-700">
                        <div>
                            <div class="text-xs text-zinc-500">{{ __('Branch Context') }}</div>
                            @if ($st->branch)
                                <div class="mt-0.5 font-medium text-zinc-700 dark:text-zinc-300">{{ \App\Support\HumanName::name($st->branch) }}</div><div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $st->branch->code }}</div>
                            @else
                                <div class="mt-0.5 text-zinc-600 dark:text-zinc-300">{{ __('Central / Unassigned') }}</div>
                            @endif
                        </div>
                        <div><div class="text-xs text-zinc-500">{{ __('Linked Sales Outlet') }}</div>@if($st->type === 'selling')<div class="mt-0.5 font-medium text-zinc-700 dark:text-zinc-300">{{ \App\Support\HumanName::name($st) }}</div><div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $st->code }}</div>@else<div class="mt-0.5">{{ __('Unlinked') }}</div>@endif</div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-zinc-500">{{ __('Negative Stock') }}</span>
                            @if ($st->allows_negative_stock)
                                <flux:badge size="sm" color="amber" variant="subtle"><span style="color: light-dark(#78350f, #fde68a)">{{ __('Allowed (Warning)') }}</span></flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" variant="subtle">{{ __('Blocked (Safe)') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        @can('branches_stores.edit')
                            <x-actions.button semantic="edit" :label="__('Edit')" size="sm" variant="subtle" icon="pencil" wire:click="openEditStoreModal({{ $st->id }})">{{ __('Edit') }}</x-actions.button>
                            @if ($st->type === 'selling' && $st->status === 'active' && $st->branch)
                                <x-actions.button semantic="assign" :label="__('Assign')" size="sm" variant="subtle" icon="arrows-right-left" wire:click="openStoreMappingModal({{ $st->id }})">{{ __('Set as primary point of sale') }}</x-actions.button>
                            @endif
                            @if ($st->status === 'active' && ! $isPendingArchive)
                                <x-actions.button semantic="disable" :label="__('Deactivate')" size="sm" variant="subtle" icon="pause" wire:click="toggleStoreStatus({{ $st->id }})">{{ __('Deactivate') }}</x-actions.button>
                            @elseif ($st->status !== 'active')
                                <x-actions.button semantic="restore" :label="__('Activate')" size="sm" variant="subtle" icon="play" wire:click="toggleStoreStatus({{ $st->id }})">{{ __('Activate') }}</x-actions.button>
                            @endif
                        @endcan
                        @can('branches_stores.logical_delete')
                            @if ($st->status === 'active' && ! $isPendingArchive)
                                <x-actions.button semantic="archive" :label="__('Request archive')" size="sm" variant="subtle" icon="archive-box" wire:click="openArchiveModal({{ $st->id }})">{{ __('Request archive') }}</x-actions.button>
                            @endif
                        @endcan
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 px-2 dark:border-zinc-700 md:block" aria-label="{{ __('Warehouse list') }}">
            <flux:table data-guide="stores-table" class="w-full">
            <flux:table.columns>
                <flux:table.column class="min-w-52"><span class="block whitespace-normal leading-tight">{{ __('Location identity') }}</span></flux:table.column>
                <flux:table.column class="min-w-36 whitespace-nowrap">{{ __('Type') }}</flux:table.column>
                <flux:table.column class="min-w-40"><span class="block whitespace-normal leading-tight">{{ __('Branch Context') }}</span></flux:table.column>
                <flux:table.column class="min-w-40"><span class="block whitespace-normal leading-tight">{{ __('Linked Sales Outlet') }}</span></flux:table.column>
                <flux:table.column class="min-w-32"><span class="block whitespace-normal leading-tight">{{ __('Negative Stock') }}</span></flux:table.column>
                <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="min-w-44 whitespace-nowrap text-end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($stores as $st)
                    <?php
                $isPendingArchive = in_array($st->id, $pendingArchiveStoreIds, true);
?>
                    <flux:table.row :key="$st->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $st->name_ar }}</div>
                            <div class="text-xs text-zinc-500">{{ $st->name_en }}</div>
                            <span class="mt-1 inline-block rounded bg-primary/10 px-1.5 py-0.5 font-mono text-xs text-primary" dir="ltr">{{ $st->code }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            @switch($st->type)
                                @case('selling')
                                    <flux:badge size="sm" variant="subtle" color="zinc"><span data-testid="store-type-{{ $st->id }}">{{ __('Point of Sale (POS)') }}</span></flux:badge>
                                    @break
                                @case('warehouse')
                                    <flux:badge size="sm" variant="subtle" color="zinc"><span data-testid="store-type-{{ $st->id }}">{{ __('Warehouse') }}</span></flux:badge>
                                    @break
                                @case('party')
                                    <flux:badge size="sm" variant="subtle" color="zinc">{{ __('Service Center') }}</flux:badge>
                                    @break
                                @case('damaged')
                                    <flux:badge size="sm" variant="subtle" color="rose">{{ __('Damaged & Defective Stock') }}</flux:badge>
                                    @break
                                @case('transit')
                                    <flux:badge size="sm" variant="subtle" color="amber">{{ __('Stock in Transit') }}</flux:badge>
                                    @break
                                @default
                                    <flux:badge size="sm" variant="subtle">{{ $st->type }}</flux:badge>
                            @endswitch
                            @if ($st->sellingStoreMappings->isNotEmpty())
                                <flux:badge size="sm" color="emerald">{{ __('Primary') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="align-top text-xs">
                            @if ($st->branch)
                                <span class="block font-medium text-zinc-700 dark:text-zinc-300">{{ \App\Support\HumanName::name($st->branch) }}</span>
                                <span class="block font-mono leading-relaxed text-zinc-500" dir="ltr">{{ $st->branch->code }}</span>
                            @else
                                <span class="font-italic text-zinc-600 dark:text-zinc-300">{{ __('Central / Unassigned') }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($st->type === 'selling')
                                <div class="font-medium">{{ \App\Support\HumanName::name($st) }}</div><div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $st->code }}</div>
                            @else
                                <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('Unlinked') }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($st->allows_negative_stock)
                                <flux:badge size="sm" color="amber" variant="subtle">{{ __('Allowed (Warning)') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" variant="subtle">{{ __('Blocked (Safe)') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($isPendingArchive)
                                <flux:badge size="sm" color="amber" inset="top" class="font-medium">{{ __('Pending archive approval') }}</flux:badge>
                            @elseif ($st->status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top" class="font-medium !bg-emerald-700 !text-white">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __('Inactive / Archived') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="align-top text-end">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @can('branches_stores.edit')
                                    <x-actions.button semantic="edit" :label="__('Edit')" size="xs" variant="subtle" icon="pencil" wire:click="openEditStoreModal({{ $st->id }})" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}" />
                                    @if ($st->type === 'selling' && $st->status === 'active' && $st->branch)
                                        <x-actions.button semantic="assign" :label="__('Assign')" size="xs" variant="subtle" icon="arrows-right-left" wire:click="openStoreMappingModal({{ $st->id }})" aria-label="{{ __('Set as primary point of sale') }}" title="{{ __('Set as primary point of sale') }}" />
                                    @endif
                                    @if ($st->status === 'active' && ! $isPendingArchive)
                                        <x-actions.button semantic="disable" :label="__('Deactivate')" size="xs" variant="subtle" icon="pause" class="whitespace-nowrap" wire:click="toggleStoreStatus({{ $st->id }})" aria-label="{{ __('Deactivate') }}" title="{{ __('Deactivate') }}">{{ __('Deactivate') }}</x-actions.button>
                                    @else
                                        @if ($st->status !== 'active')
                                            <x-actions.button semantic="restore" :label="__('Activate')" size="xs" variant="subtle" icon="play" class="whitespace-nowrap" wire:click="toggleStoreStatus({{ $st->id }})" aria-label="{{ __('Activate') }}" title="{{ __('Activate') }}">{{ __('Activate') }}</x-actions.button>
                                        @endif
                                    @endif
                                @endcan
                                @can('branches_stores.logical_delete')
                                    @if ($st->status === 'active' && ! $isPendingArchive)
                                        <x-actions.button semantic="archive" :label="__('Request archive')" size="xs" variant="subtle" icon="archive-box" class="whitespace-nowrap" wire:click="openArchiveModal({{ $st->id }})" aria-label="{{ __('Request archive') }}" title="{{ __('Request archive') }}">{{ __('Request archive') }}</x-actions.button>
                                    @endif
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
            </flux:table>
        </div>

        <div class="pt-4" data-guide="stores-pagination">
            <p class="mb-2 text-sm text-zinc-500">{{ __('Showing :from–:to of :total locations', ['from'=>$stores->firstItem()??0,'to'=>$stores->lastItem()??0,'total'=>$stores->total()]) }}</p>
            {{ $stores->links() }}
        </div>
    @endif

    <!-- Create / Edit Store Modal -->
    <flux:modal wire:model="showStoreModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingStoreId ? __('Edit warehouse or point of sale') : __('Create warehouse or point of sale') }}</flux:heading>
            <flux:subheading>{{ __('Define a location code, location type, bilingual names, branch context, and negative stock policy.') }}</flux:subheading>
        </div>

        <form wire:submit="saveStore" novalidate class="space-y-4">
            @if ($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    <ul class="list-disc space-y-1 ps-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:input
                    wire:model="storeForm.code"
                    :label="__('Warehouse Code')"
                    placeholder="STR-01"
                    required
                />

                <flux:select wire:model.live="storeForm.type" :label="__('Location type')" required>
                    <flux:select.option value="selling">{{ __('Point of Sale (POS)') }}</flux:select.option>
                    <flux:select.option value="warehouse">{{ __('Warehouse — physical inventory') }}</flux:select.option>
                </flux:select>

                @if (! $editingStoreId)
                    <flux:select wire:model="storeForm.status" :label="__('Status')" required>
                        <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                    </flux:select>
                @else
                    <flux:callout icon="information-circle" variant="info">
                        {{ __('Use Deactivate for a reversible status change, or Request archive for the approval-backed Inactive / Archived transition.') }}
                    </flux:callout>
                @endif
            </div>

            <div class="flex items-center">
                <flux:checkbox
                    wire:model="storeForm.allows_negative_stock"
                    :label="__('Allow negative stock')"
                />
            </div>

            <flux:select wire:model="storeForm.branch_id" :label="__('Branch')" :required="$storeForm['type'] === 'selling'" class="w-full" data-testid="store-branch-context-selector">
                @if ($storeForm['type'] === 'selling')
                    <flux:select.option value="">{{ __('Select an active branch...') }}</flux:select.option>
                @else
                    <flux:select.option value="">{{ __('Central / No Direct Branch') }}</flux:select.option>
                @endif
                @foreach ($activeBranchesList as $b)
                    <flux:select.option :value="$b->id">
                        {{ $b->code }} - {{ str_starts_with(app()->getLocale(), 'ar') ? $b->name_ar : $b->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            @if ($storeForm['type'] === 'selling')
                <p class="text-xs text-zinc-500">{{ __('A sales outlet must belong to an active branch. Its own linked cash drawer identifies the exact POS stock source.') }}</p>
                <flux:select wire:model="storeForm.price_list_id" :label="__('Price list')"><flux:select.option value="">{{ __('Inherit branch default or List 0') }}</flux:select.option>@foreach($activePriceLists as $priceList)<flux:select.option :value="$priceList->id">{{ $priceList->code }} · {{ str_starts_with(app()->getLocale(), 'ar')?$priceList->name_ar:$priceList->name_en }}</flux:select.option>@endforeach</flux:select>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="storeForm.name_ar"
                    :label="__('Arabic Name')"
                    placeholder="مخزن المعرض الرئيسي"
                    required
                />

                <flux:input
                    wire:model="storeForm.name_en"
                    :label="__('English Name')"
                    placeholder="Main Showroom Store"
                    required
                />
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showStoreModal', false)">{{ __('Cancel') }}</x-actions.button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveStore"><span wire:loading.remove wire:target="saveStore">{{ __('Save location') }}</span><span wire:loading wire:target="saveStore">{{ __('Saving...') }}</span></x-actions.button>
            </div>
        </form>
    </flux:modal>

    <!-- Archive approval confirmation -->
    <flux:modal wire:model="showArchiveModal" class="md:w-140 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Request archive approval') }}</flux:heading>
            <flux:subheading>{{ __('Review the location context before submitting this approval-backed status change.') }}</flux:subheading>
        </div>

        <dl class="grid gap-3 rounded-xl border border-border-subtle bg-zinc-50 p-4 text-sm dark:bg-zinc-900 sm:grid-cols-2">
            <div><dt class="text-text-muted">{{ __('Warehouse code') }}</dt><dd class="font-mono font-medium">{{ $archiveStoreContext['code'] ?? '—' }}</dd></div>
            <div><dt class="text-text-muted">{{ __('Warehouse name') }}</dt><dd class="font-medium">{{ $archiveStoreContext['name'] ?? '—' }}</dd></div>
            <div><dt class="text-text-muted">{{ __('Warehouse type') }}</dt><dd>{{ $archiveStoreContext['type'] ?? '—' }}</dd></div>
            <div><dt class="text-text-muted">{{ __('Branch context') }}</dt><dd>{{ $archiveStoreContext['branch'] ?? '—' }}</dd></div>
        </dl>

        <flux:callout icon="archive-box" variant="warning">
            <strong>{{ __('History is preserved.') }}</strong>
            {{ __('After independent approval, this location will remain in the system and be marked Inactive / Archived. It will not be hard-deleted.') }}
        </flux:callout>
        <flux:callout icon="exclamation-triangle" variant="warning">
            {{ __('Archive and delete are dependency-checked. Inventory, stock movements, transactions, POS links, open counts, transfers, drawers, and historical references can block the action; the exact dependency category is shown in the error.') }}
            {{ __('Deactivate is the reversible option for stopping active use while retaining history.') }}
        </flux:callout>
        <flux:callout icon="shield-check" variant="info">
            {{ __('A second authorized approver is required. Submitting now does not change the location status.') }}
        </flux:callout>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <flux:button variant="subtle" wire:click="$set('showArchiveModal', false)">{{ __('Cancel') }}</flux:button>
            <x-actions.button semantic="archive" :label="__('Request archive')" variant="primary"  wire:click="requestArchive">{{ __('Request archive') }}</x-actions.button>
        </div>
    </flux:modal>

    <!-- Set primary point of sale modal -->
    <flux:modal wire:model="showStoreMappingModal" class="md:w-140 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Set as primary point of sale') }}</flux:heading>
            <flux:subheading><strong class="text-zinc-900 dark:text-zinc-100">{{ $mappingStoreName }}</strong> · {{ $mappingBranchName }}</flux:subheading>
        </div>

        <form wire:submit="saveStoreMapping" class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('This changes the primary point of sale for the branch. The previous choice stays in the history.') }}</p>

            <flux:textarea
                wire:model="mappingApprovalNotes"
                :label="__('Notes (optional)')"
                placeholder="{{ __('Add a short note for this change.') }}"
                rows="3"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showStoreMappingModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveStoreMapping"><span wire:loading.remove wire:target="saveStoreMapping">{{ __('Set as primary') }}</span><span wire:loading wire:target="saveStoreMapping">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
