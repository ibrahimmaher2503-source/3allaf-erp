<?php

use App\Modules\Platform\Actions\SaveRoleAction;
use App\Modules\Platform\Models\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Roles')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public bool $roleModalOpen = false;
    public ?int $editingRoleId = null;
    public array $roleForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'description_ar' => '', 'description_en' => '', 'status' => 'active'];

    public function mount(): void
    {
        Gate::authorize('users_roles_permissions.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateRole(): void
    {
        Gate::authorize('users_roles_permissions.create');
        $this->editingRoleId = null;
        $this->roleForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'description_ar' => '', 'description_en' => '', 'status' => 'active'];
        $this->resetValidation();
        $this->roleModalOpen = true;
    }

    public function openEditRole(int $roleId): void
    {
        Gate::authorize('users_roles_permissions.edit');
        $role = Role::query()->findOrFail($roleId);
        abort_if(SaveRoleAction::isCanonical($role), 403);
        $this->editingRoleId = $role->id;
        $this->roleForm = $role->only(['code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'status']);
        $this->resetValidation();
        $this->roleModalOpen = true;
    }

    public function saveRole(SaveRoleAction $action): void
    {
        Gate::authorize($this->editingRoleId === null ? 'users_roles_permissions.create' : 'users_roles_permissions.edit');
        $this->roleForm['code'] = strtolower(trim($this->roleForm['code']));
        $validated = $this->validate([
            'roleForm.code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('roles', 'code')->ignore($this->editingRoleId)],
            'roleForm.name_ar' => ['required', 'string', 'max:255'],
            'roleForm.name_en' => ['required', 'string', 'max:255'],
            'roleForm.description_ar' => ['nullable', 'string', 'max:2000'],
            'roleForm.description_en' => ['nullable', 'string', 'max:2000'],
            'roleForm.status' => ['required', 'in:active,inactive'],
        ])['roleForm'];

        try {
            $action->execute($validated, $this->editingRoleId === null ? null : Role::query()->findOrFail($this->editingRoleId));
            $this->roleModalOpen = false;
            Flux::toast(variant: 'success', text: __('Role saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('roleForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function render(): mixed
    {
        return view('platform.admin.roles', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.trim($this->search).'%')->orWhere('name_ar', 'like', '%'.trim($this->search).'%')->orWhere('name_en', 'like', '%'.trim($this->search).'%')))
                ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
                ->orderBy('code')
                ->paginate(20),
        ]);
    }
}; ?>

<x-app.page :title="__('Roles')" :description="__('Review canonical roles and maintain permitted local roles. Permission mappings are managed separately and every change is audited.')" max-width="7xl" class="space-y-5">
    <x-slot:actions>
        <x-context-help :title="__('Roles and permissions help')" :label="__('Open roles and permissions help')"><ul><li>{{ __('Roles define what a user may do; branch and location assignments define where they may do it.') }}</li><li>{{ __('A warehouse or point of sale belongs to a branch, and user access must remain inside the authorized branch chain.') }}</li><li>{{ __('Open Permissions for a role to review its actions. Canonical role safeguards remain unchanged.') }}</li></ul></x-context-help>
        @can('users_roles_permissions.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateRole">{{ __('Add role') }}</flux:button>
        @endcan
    </x-slot:actions>

    <x-tables.data-panel :title="__('Roles')" :description="__('Search roles, review their current assignments, then open the permission matrix for details.')">
        <x-slot:toolbar>
            <x-tables.filter-bar>
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search roles')" :placeholder="__('Code or name')" />
                <flux:select wire:model.live="statusFilter" :label="__('Status')" class="sm:w-40">
                    <option value="all">{{ __('All Statuses') }}</option><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if ($roles->isEmpty())
            <x-state.empty :title="__('No roles found')" :description="__('Change the filter or add a permitted local role.')" icon="shield-check" />
        @else
            <div class="overflow-x-auto rounded-xl border border-border">
                <table class="data-table min-w-[56rem] w-full text-sm">
                    <thead><tr><th class="min-w-32 whitespace-nowrap px-3 py-2.5 text-start">{{ __('Code') }}</th><th class="min-w-48 px-3 py-2.5 text-start">{{ __('Role') }}</th><th class="min-w-48 px-3 py-2.5 text-start">{{ __('Assignments') }}</th><th class="min-w-40 px-3 py-2.5 text-start">{{ __('Status') }}</th><th class="min-w-44 whitespace-nowrap px-3 py-2.5 text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @foreach ($roles as $role)
                            @php($canonical = SaveRoleAction::isCanonical($role))
                            <tr class="data-table-row">
                                <td class="px-3 py-3 align-top font-mono text-xs">{{ $role->code }}</td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? $role->name_ar : $role->name_en }}</div>
                                    <div class="text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? $role->name_en : $role->name_ar }}</div>
                                    @php($roleDescription = str_starts_with(app()->getLocale(), 'ar') ? $role->description_ar : $role->description_en)
                                    @if ($role->code === 'cashier')
                                        <p class="mt-1 max-w-xl text-xs leading-5 text-text-muted">{{ $roleDescription ?: __('Cashier role scope description') }}</p>
                                    @elseif ($roleDescription)
                                        <p class="mt-1 max-w-xl text-xs leading-5 text-text-muted">{{ $roleDescription }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 align-top text-text-muted whitespace-normal">{{ __(':users users · :permissions permissions', ['users' => $role->users_count, 'permissions' => $role->permissions_count]) }}</td>
                                <td class="px-3 py-3 align-top"><div class="flex flex-wrap items-center gap-1"><x-status.badge :status="$role->status" :label="$role->status === 'active' ? __('Active') : __('Inactive')" /> @if ($canonical)<flux:badge size="sm" variant="outline">{{ __('Canonical role') }}</flux:badge>@endif</div></td>
                                <td class="px-3 py-3 align-top text-end"><div class="flex flex-wrap justify-end gap-1"> <x-actions.button semantic="assign" :label="__('Permissions')" size="xs" variant="subtle" icon="key" :href="route('admin.role-permissions', $role)" wire:navigate>{{ __('Permissions') }}</x-actions.button>@if (! $canonical) @can('users_roles_permissions.edit')<x-actions.button semantic="edit" :label="__('Edit')" size="xs" wire:click="openEditRole({{ $role->id }})">{{ __('Edit') }}</x-actions.button>@endcan @endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-slot:footer><div class="flex justify-end">{{ $roles->links() }}</div></x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="roleModalOpen" class="md:max-w-xl">
        <form wire:submit="saveRole" class="space-y-5">
            <div><flux:heading size="lg">{{ $editingRoleId === null ? __('Add role') : __('Edit role') }}</flux:heading><flux:text class="mt-1 text-text-muted">{{ __('A local role starts without permissions. Open its matrix after saving.') }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="roleForm.code" :label="__('Role code')" placeholder="store-observer" required dir="ltr" /><flux:input wire:model="roleForm.name_ar" :label="__('Arabic role name')" required dir="rtl" /><flux:input wire:model="roleForm.name_en" :label="__('English role name')" required dir="ltr" /><flux:select wire:model="roleForm.status" :label="__('Status')"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select></div>
            <flux:textarea wire:model="roleForm.description_ar" :label="__('Arabic description (optional)')" dir="rtl" rows="2" /><flux:textarea wire:model="roleForm.description_en" :label="__('English description (optional)')" dir="ltr" rows="2" />
            @error('roleForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
            <div class="flex justify-end gap-3 border-t border-border pt-4"><flux:button type="button" variant="subtle" wire:click="$set('roleModalOpen', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveRole">{{ __('Save role') }}</flux:button></div>
        </form>
    </flux:modal>
</x-app.page>
