<?php

use App\Models\User;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Cash Drawer Masters')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $branchFilter = 'all';

    public string $statusFilter = 'all';
    #[Url(as: 'setup', except: false)]
    public bool $setupMode = false;

    // Drawer Modal State
    public bool $showDrawerModal = false;

    public ?int $editingDrawerId = null;

    public array $drawerForm = [
        'branch_id' => '',
        'store_id' => '',
        'assigned_user_id' => '',
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'status' => 'active',
        'policy_notes' => '',
    ];

    public function mount(): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDrawerFormBranchId(): void
    {
        $this->drawerForm['store_id'] = '';
        $this->resetValidation('drawerForm.store_id');
    }

    public function openCreateDrawerModal(): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.create');

        $this->editingDrawerId = null;
        $defaultBranchId = Branch::visibleTo(auth()->user())->where('status', 'active')->first()?->id ?? '';

        $this->drawerForm = [
            'branch_id' => (string) $defaultBranchId,
            'store_id' => '',
            'assigned_user_id' => '',
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'status' => 'active',
            'policy_notes' => '',
        ];
        $this->resetValidation();
        $this->showDrawerModal = true;
    }

    public function openEditDrawerModal(int $id): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');

        $drawer = CashDrawer::visibleTo(auth()->user())->findOrFail($id);
        $this->editingDrawerId = $drawer->id;
        $this->drawerForm = [
            'branch_id' => (string) $drawer->branch_id,
            'store_id' => (string) ($drawer->store_id ?? ''),
            'assigned_user_id' => (string) ($drawer->assigned_user_id ?? ''),
            'code' => $drawer->code,
            'name_ar' => $drawer->name_ar,
            'name_en' => $drawer->name_en,
            'status' => $drawer->status,
            'policy_notes' => $drawer->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showDrawerModal = true;
    }

    public function saveDrawer(SaveCashDrawerAction $action): void
    {
        Gate::authorize($this->editingDrawerId ? 'drawers_payments_tax_numbering_printers.edit' : 'drawers_payments_tax_numbering_printers.create');

        $validated = $this->validate([
            'drawerForm.branch_id' => ['required', 'exists:branches,id'],
            'drawerForm.store_id' => [Rule::requiredIf(($this->drawerForm['status'] ?? 'active') === 'active'), 'nullable', 'exists:stores,id'],
            'drawerForm.assigned_user_id' => ['nullable', 'exists:users,id'],
            'drawerForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('cash_drawers', 'code')
                    ->where('branch_id', $this->drawerForm['branch_id'])
                    ->ignore($this->editingDrawerId),
            ],
            'drawerForm.name_ar' => ['required', 'string', 'max:255'],
            'drawerForm.name_en' => ['required', 'string', 'max:255'],
            'drawerForm.status' => ['required', 'in:active,inactive,maintenance'],
            'drawerForm.policy_notes' => ['nullable', 'string'],
        ])['drawerForm'];

        try {
            $action->execute($validated, $this->editingDrawerId);
            Flux::toast(variant: 'success', text: $this->editingDrawerId ? __('Cash drawer updated successfully.') : __('Cash drawer created successfully.'));
            $this->showDrawerModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', heading: __('Cash drawer change blocked'), text: \App\Support\UserSafeError::message($e));
        }
    }

    public function toggleDrawerStatus(int $id, string $status, SaveCashDrawerAction $action): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');

        try {
            $action->toggleStatus($id, $status);
            Flux::toast(variant: 'success', text: __('Cash drawer status updated successfully.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', heading: __('Cash drawer change blocked'), text: \App\Support\UserSafeError::message($e));
        }
    }

    public function deleteDrawer(int $id, PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.logical_delete');

        try {
            $drawer = CashDrawer::visibleTo(auth()->user())->findOrFail($id);
            $approvalAction->request('cash_drawer_delete', $drawer->id, ['deleted' => true], $drawer->getAttributes(), $drawer->branch_id, $drawer->store_id);
            Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Cash drawer deletion submitted for independent approval.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($e));
        }
    }

    public function rendering(): void
    {
        // Compute available stores filtered by selected branch in modal form if chosen
    }
}; ?>

<x-app.page
    :title="__('Cash Drawer Masters')"
    :description="__('Configure branch-scoped cash drawers and default assignments for POS operations. Drawers with an active POS shift cannot be deactivated or reassigned.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="drawers-header"
>
    <flux:callout variant="info" icon="information-circle"><strong>{{ __('Cashier Till') }}:</strong> {{ __('Cashier Till help') }} <span class="ms-2 text-text-muted">{{ __('Branch → Sales Outlet → Cashier Till') }} → {{ __('Shift') }}</span></flux:callout>
    <x-slot:actions>
        <x-tables.resource-toolbar>
            @can('drawers_payments_tax_numbering_printers.create')
                <flux:button type="button" variant="primary" icon="plus" wire:click="openCreateDrawerModal" data-guide="drawers-add-action">{{ __('Add Cash Drawer') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <!-- Filters & Search -->
    <flux:card id="drawers-filters" class="scroll-mt-24 p-4 sm:p-5" data-guide="drawers-filters">
        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :label="__('Search')"
                :placeholder="__('Search drawers by code or name...')"
                size="sm"
            />

            <flux:select wire:model.live="branchFilter" :label="__('Branch')" size="sm">
                <option value="all">{{ __('All Branches') }}</option>
                @foreach(Branch::visibleTo(auth()->user())->orderBy('name_en')->get() as $branchOption)
                    <option value="{{ $branchOption->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $branchOption->name_ar : $branchOption->name_en }} ({{ $branchOption->code }})</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" :label="__('Status')" size="sm">
                <option value="all">{{ __('All Statuses') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
                <option value="maintenance">{{ __('Maintenance') }}</option>
            </flux:select>
        </div>
    </flux:card>

    @php
        $query = CashDrawer::visibleTo(auth()->user())->with(['branch', 'store', 'assignedUser']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhere('name_ar', 'like', '%' . $search . '%')
                  ->orWhere('name_en', 'like', '%' . $search . '%');
            });
        }

        if ($branchFilter !== 'all') {
            $query->where('branch_id', $branchFilter);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $drawers = $query->orderBy('branch_id')->orderBy('code')->paginate(15);
        $branches = Branch::visibleTo(auth()->user())->where('status', 'active')->with('activeSellingStoreMapping.store')->orderBy('name_en')->get();
        // Resolve the dependent option from the current Livewire branch value,
        // rather than relying on the eager-loaded branch collection surviving
        // a reactive re-render.
        $selectedBranchId = (int) ($drawerForm['branch_id'] ?? 0);
        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
        $selectedSellingStoreId = $selectedBranch !== null && $selectedBranchId > 0
            ? BranchSellingStore::query()
                ->where('branch_id', $selectedBranchId)
                ->where('status', 'active')
                ->latest('id')
                ->value('store_id')
            : null;
        $sellingStores = $selectedSellingStoreId
            ? Store::visibleTo(auth()->user())->whereKey($selectedSellingStoreId)->where('branch_id', $selectedBranch->id)->where('type', 'selling')->where('status', 'active')->orderBy('name_en')->get()
            : collect();
        $users = User::orderBy('name')->get();
    @endphp

    <!-- Cash Drawers Data Table -->
    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800 overflow-hidden shadow-xs" data-guide="drawers-table">
        @if($drawers->count() > 0)
            <div class="grid gap-3 p-3 md:hidden">
                @foreach($drawers as $drawer)
                    <flux:card class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3"><div><div class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $drawer->name_ar : $drawer->name_en }}</div><div class="text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? $drawer->name_en : $drawer->name_ar }}</div><span class="font-mono text-xs text-primary" dir="ltr">{{ $drawer->code }}</span></div><x-status.badge :status="$drawer->status === 'active' ? 'active' : ($drawer->status === 'maintenance' ? 'warning' : 'inactive')" :label="__($drawer->status === 'active' ? 'Active' : ($drawer->status === 'maintenance' ? 'Maintenance' : 'Inactive'))" /></div>
                        <dl class="grid gap-2 text-sm"><div><dt class="text-xs text-text-muted">{{ __('Branch → Sales Outlet → Cashier Till') }}</dt><dd>{{ $drawer->branch?->code ?? '—' }} → {{ $drawer->store?->code ?? '—' }} → <span dir="ltr">{{ $drawer->code }}</span></dd></div><div><dt class="text-xs text-text-muted">{{ __('Assigned Cashier / User') }}</dt><dd>{{ $drawer->assignedUser?->name ?? __('Unassigned') }}</dd></div></dl>
                        <div class="flex flex-wrap gap-2 border-t border-border pt-3">
                            @can('drawers_payments_tax_numbering_printers.edit')
                                <x-actions.button semantic="edit" :label="__('Edit')" size="sm" variant="subtle" color="blue" icon="pencil" wire:click="openEditDrawerModal({{ $drawer->id }})">{{ __('Edit') }}</x-actions.button>
                                @if($drawer->status === 'active')
                                    <x-actions.button semantic="unavailable" :label="__('Mark unavailable')" size="sm" variant="subtle" color="amber" icon="wrench-screwdriver" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'maintenance')" title="{{ __('Mark unavailable for shifts') }}">{{ __('Mark unavailable') }}</x-actions.button>
                                    <x-actions.button semantic="disable" :label="__('Deactivate')" size="sm" variant="subtle" color="amber" icon="pause" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'inactive')" wire:confirm="{{ __('Confirm this action?') }}">{{ __('Deactivate') }}</x-actions.button>
                                @else
                                    <x-actions.button semantic="restore" :label="__('Activate')" size="sm" variant="subtle" color="green" icon="play" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'active')">{{ __('Activate') }}</x-actions.button>
                                @endif
                            @endcan
                            @can('drawers_payments_tax_numbering_printers.logical_delete')
                                <x-actions.button semantic="delete" :label="__('Delete')" size="sm" variant="subtle" color="red" icon="trash" wire:click="deleteDrawer({{ $drawer->id }})" wire:confirm="{{ __('Confirm this action?') }}">{{ __('Delete') }}</x-actions.button>
                            @endcan
                        </div>
                    </flux:card>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table w-full text-start text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Code') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Drawer Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Branch → POS selling location / stock source') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Assigned Cashier / User') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($drawers as $drawer)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-primary">
                                    {{ $drawer->code }}
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ str_starts_with(app()->getLocale(), 'ar') ? $drawer->name_ar : $drawer->name_en }}
                                    <div class="text-xs font-normal text-zinc-600 dark:text-zinc-300">
                                        {{ str_starts_with(app()->getLocale(), 'ar') ? $drawer->name_en : $drawer->name_ar }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if($drawer->branch)
                                        <div class="flex min-w-[15rem] items-start gap-1.5">
                                            <flux:icon name="building-office-2" class="mt-0.5 size-3.5 shrink-0 text-zinc-400" />
                                            <div class="min-w-0">
                                                <div class="font-medium">{{ $drawer->branch->code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? $drawer->branch->name_ar : $drawer->branch->name_en }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">→ {{ $drawer->store?->code ?? __('No POS location') }}{{ $drawer->store ? ' — '.(str_starts_with(app()->getLocale(), 'ar') ? $drawer->store->name_ar : $drawer->store->name_en) : '' }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="font-italic text-zinc-600 dark:text-zinc-300">{{ __('Unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if($drawer->assignedUser)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium">
                                            <flux:icon name="user" class="size-3.5 text-zinc-400" />
                                            {{ $drawer->assignedUser->name }}
                                        </span>
                                    @else
                                        <span class="text-xs font-italic text-zinc-600 dark:text-zinc-300">{{ __('Unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($drawer->status === 'active')
                                        <x-status.badge status="active" :label="__('Active')" />
                                    @elseif($drawer->status === 'maintenance')
                                        <x-status.badge status="warning" :label="__('Maintenance')" />
                                    @else
                                        <x-status.badge status="inactive" :label="__('Inactive')" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('drawers_payments_tax_numbering_printers.edit')
                                            <x-actions.button semantic="edit" :label="__('Edit')" size="xs" variant="subtle" icon="pencil" wire:click="openEditDrawerModal({{ $drawer->id }})">{{ __('Edit') }}</x-actions.button>
                                            @if($drawer->status === 'active')
                                                <x-actions.button semantic="unavailable" :label="__('Mark unavailable')" size="xs" variant="subtle" class="text-amber-600 dark:text-amber-400" icon="wrench-screwdriver" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'maintenance')" title="{{ __('Mark unavailable for shifts') }}">{{ __('Mark unavailable') }}</x-actions.button>
                                                <flux:button size="xs" variant="subtle" class="text-red-600 dark:text-red-400" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'inactive')" wire:confirm="{{ __('Confirm this action?') }}">{{ __('Deactivate') }}</flux:button>
                                            @else
                                                <flux:button size="xs" variant="subtle" class="text-emerald-600 dark:text-emerald-400" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'active')">{{ __('Activate') }}</flux:button>
                                            @endif
                                        @endcan
                                        @can('drawers_payments_tax_numbering_printers.logical_delete')
                                            <flux:button size="xs" variant="subtle" class="text-red-700 dark:text-red-300" wire:click="deleteDrawer({{ $drawer->id }})" wire:confirm="{{ __('Confirm this action?') }}">{{ __('Delete') }}</flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-700" data-guide="drawers-pagination">
                {{ $drawers->links() }}
            </div>
        @else
            <x-state.empty
                :title="__('No Cash Drawers Found')"
                :description="__('No cash drawers match your search or active filters.')"
                icon="inbox-stack"
            >
                <x-slot:actions>
                    <flux:button type="button" variant="primary" icon="plus" wire:click="openCreateDrawerModal">
                        {{ __('Create Cash Drawer') }}
                    </flux:button>
                </x-slot:actions>
            </x-state.empty>
        @endif
    </div>

    <!-- Modal: Create / Edit Cash Drawer -->
    @if ($showDrawerModal)
    <flux:modal wire:model.self="showDrawerModal" class="max-w-xl space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingDrawerId ? __('Edit Cash Drawer') : __('Add Cash Drawer') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Configure the drawer identifier and its required POS selling location / stock source for shifts.') }}
            </flux:subheading>
        </div>

        <form wire:submit="saveDrawer" novalidate class="space-y-4">
            @if ($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    <ul class="list-disc space-y-1 ps-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select
                    wire:model.live="drawerForm.branch_id"
                    :label="__('Branch')"
                    required
                >
                    <option value="">{{ __('Select Branch') }}</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $b->name_ar : $b->name_en }} ({{ $b->code }})</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="drawerForm.code"
                    :label="__('Drawer Code')"
                    placeholder="CDR-001"
                    required
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="drawerForm.name_ar"
                    :label="__('Arabic Name')"
                    placeholder="درج النقدية الأول"
                    required
                    dir="rtl"
                />

                <flux:input
                    wire:model="drawerForm.name_en"
                    :label="__('English Name')"
                    placeholder="Main Cash Drawer 1"
                    required
                    dir="ltr"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select
                    wire:key="cash-drawer-store-select-{{ $editingDrawerId ?? 'create' }}-{{ $drawerForm['branch_id'] ?: 'none' }}"
                    wire:model="drawerForm.store_id"
                    :label="__('POS selling location / stock source')"
                    required
                >
                    <option value="">{{ __('Select POS selling location / stock source') }}</option>
                    @foreach($sellingStores as $st)
                        <option value="{{ $st->id }}">{{ $st->code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? $st->name_ar : $st->name_en }}</option>
                    @endforeach
                </flux:select>

                @if (! empty($drawerForm['branch_id']) && $sellingStores->isEmpty())
                    <flux:callout class="md:col-span-2" variant="warning" icon="exclamation-triangle">
                        {{ __('No active POS selling location / stock source is assigned to this branch. Configure the branch POS selling-location assignment first, then return here.') }}
                    </flux:callout>
                @else
                    <flux:text class="md:col-span-2 -mt-2 text-xs text-zinc-500">{{ __('Only the selected branch’s active POS selling location is available; it is also the stock source for this drawer’s shifts.') }}</flux:text>
                @endif

                <flux:select
                    wire:model="drawerForm.assigned_user_id"
                    :label="__('Default Assigned Cashier (Optional)')"
                >
                    <option value="">{{ __('Unassigned / Any Cashier') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username ?? $u->email }})</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select
                    wire:model="drawerForm.status"
                    :label="__('Status')"
                    required
                >
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                    <option value="maintenance">{{ __('Maintenance') }}</option>
                </flux:select>
            </div>

            <flux:textarea
                wire:model="drawerForm.policy_notes"
                :label="__('Operational notes')"
                rows="2"
            />

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button type="button" variant="subtle" wire:click="$set('showDrawerModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveDrawer">
                    <span wire:loading.remove wire:target="saveDrawer">{{ $editingDrawerId ? __('Save Changes') : __('Create Cash Drawer') }}</span><span wire:loading wire:target="saveDrawer">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</x-app.page>
