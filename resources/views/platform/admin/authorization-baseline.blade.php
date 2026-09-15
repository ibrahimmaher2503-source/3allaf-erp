<?php

use App\Models\User;
use App\Modules\Platform\Actions\SaveUserAction;
use App\Modules\Platform\Support\UserManagementScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Authorization Baseline')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingUserId = null;

    public bool $authorizationModalOpen = false;

    public array $userForm = [
        'name' => '',
        'username' => '',
        'email' => '',
        'password' => '',
        'status' => 'active',
    ];

    public array $roleIds = [];

    public array $branchIds = [];

    public array $storeIds = [];

    public function mount(): void
    {
        Gate::authorize('users_roles_permissions.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function editAuthorization(int $userId, UserManagementScope $scope): void
    {
        Gate::authorize('users_roles_permissions.edit');

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $user = $scope->visibleUsers($actor)->findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->authorizationModalOpen = true;
        $this->userForm = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'password' => '',
            'status' => $user->status ?: 'active',
        ];
        $this->roleIds = $user->roles()->pluck('roles.id')->all();
        $this->branchIds = $user->branchScopes()->pluck('branch_id')->all();
        $this->storeIds = $user->storeScopes()->pluck('store_id')->all();
    }

    public function openCreateUserModal(): void
    {
        Gate::authorize('users_roles_permissions.create');
        $this->editingUserId = null;
        $this->authorizationModalOpen = true;
        $this->userForm = ['name' => '', 'username' => '', 'email' => '', 'password' => '', 'status' => 'active'];
        $this->roleIds = [];
        $this->branchIds = [];
        $this->storeIds = [];
        $this->resetValidation();
    }

    public function saveAuthorization(SaveUserAction $action): void
    {
        Gate::authorize($this->editingUserId === null ? 'users_roles_permissions.create' : 'users_roles_permissions.edit');

        $validated = $this->validate([
            'editingUserId' => ['nullable', 'exists:users,id'],
            'userForm.name' => ['required', 'string', 'max:255'],
            'userForm.username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('users', 'username')->ignore($this->editingUserId)],
            'userForm.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingUserId)],
            'userForm.password' => [$this->editingUserId === null ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'userForm.status' => ['required', Rule::in(['active', 'inactive'])],
            'roleIds' => ['array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
            'branchIds' => ['array'],
            'branchIds.*' => ['integer', 'exists:branches,id'],
            'storeIds' => ['array'],
            'storeIds.*' => ['integer', 'exists:stores,id'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $scope = app(UserManagementScope::class);

        $action->execute(
            $validated['userForm'],
            array_map('intval', $validated['roleIds']),
            array_map('intval', $validated['branchIds']),
            array_map('intval', $validated['storeIds']),
            $this->editingUserId === null ? null : $scope->visibleUsers($actor)->findOrFail($this->editingUserId),
        );

        $this->closeAuthorization();
    }

    public function closeAuthorization(): void
    {
        $this->authorizationModalOpen = false;
        $this->editingUserId = null;
        $this->roleIds = [];
        $this->branchIds = [];
        $this->storeIds = [];
        $this->userForm = ['name' => '', 'username' => '', 'email' => '', 'password' => '', 'status' => 'active'];
    }

    public function render(): mixed
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $scope = app(UserManagementScope::class);

        return view('platform.admin.authorization-baseline', [
            'users' => $scope->visibleUsers($actor)
                ->with('roles:id,name_ar,name_en')
                ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('username', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }))
                ->latest()
                ->paginate(15),
            'roles' => $this->authorizationModalOpen ? $scope->assignableRoles($actor) : [],
            'branches' => $this->authorizationModalOpen ? $scope->branches($actor)->where('status', 'active')->orderBy('name_en')->get(['id', 'name_ar', 'name_en']) : [],
            'stores' => $this->authorizationModalOpen ? $scope->stores($actor)->where('status', 'active')->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'branch_id']) : [],
        ]);
    }
}; ?>

<x-app.page
    :title="__('User access and permissions')"
    :description="__('Manage sign-in accounts, approved roles, and branch or store access.')"
    max-width="7xl"
    class="space-y-5"
    data-guide="auth-header"
>
    <x-slot:actions>
        <x-context-help :title="__('User access help')" :label="__('Open user access help')">
            <ul>
                <li>{{ __('A branch defines the business location. Warehouses and points of sale belong to that branch.') }}</li>
                <li>{{ __('Assign branch access first, then only the warehouse or point-of-sale locations the user needs.') }}</li>
                <li>{{ __('Roles grant permitted actions; location access limits where those actions can be used.') }}</li>
                <li>{{ __('Saving access does not change the existing authorization model or grant hidden permissions.') }}</li>
            </ul>
        </x-context-help>
        @can('manage-global-role-catalog')
            <flux:button variant="subtle" icon="shield-check" :href="route('admin.roles')" wire:navigate>{{ __('Manage roles') }}</flux:button>
        @endcan
    </x-slot:actions>

    @if ($errors->any())
        <flux:callout variant="danger" title="{{ __('Authorization change could not be completed') }}">
            {{ __('Review the highlighted values. No partial authorization change was performed.') }}
        </flux:callout>
    @endif

    <div wire:loading.flex wire:target="editAuthorization" role="status" aria-live="polite" class="items-center gap-2 text-sm text-text-muted">
        <flux:icon name="arrow-path" class="size-4 animate-spin" />
        {{ __('Opening authorization…') }}
    </div>

    <x-tables.data-panel :title="__('Users')" :description="__('Review current access before opening an authorized assignment.')" data-guide="auth-users-table">
        <x-slot:toolbar>
            <x-tables.filter-bar>
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search users')" :placeholder="__('Name or email')" data-guide="auth-users-search" />
                <x-slot:actions>
                    @can('users_roles_permissions.create')
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateUserModal">{{ __('New user') }}</flux:button>
                    @endcan
                    <flux:badge size="sm" variant="outline" icon="user-group">{{ __('Records: :count', ['count' => $users->total()]) }}</flux:badge>
                </x-slot:actions>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if ($users->isEmpty())
            <x-state.empty :title="__('No users found')" :description="__('No user records match this search.')" />
        @else
            <table class="data-table w-full min-w-[48rem] text-sm">
                <caption class="sr-only">{{ __('Current user authorization inventory') }}</caption>
                <thead class="bg-surface-muted/70 text-xs text-text-muted">
                    <tr>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('User') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Email') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Roles') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Status') }}</th>
                        <th class="px-3 py-2.5 text-start font-semibold">{{ __('Verification') }}</th>
                        <th class="px-3 py-2.5 text-end font-semibold">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php
                            $roleNames = $user->roles
                                ->map(fn ($role) => str_starts_with(app()->getLocale(), 'ar') ? $role->name_ar : $role->name_en)
                                ->filter()
                                ->join(', ');
                        @endphp
                        <tr class="data-table-row">
                            <td class="px-3 py-3"><div class="font-medium text-text-primary">{{ $user->name }}</div>@if ($user->is_super_admin)<div class="mt-1"><x-status.badge status="override" :label="__('Super Admin')" /></div>@endif</td>
                            <td class="px-3 py-3 text-text-muted">{{ $user->email }}</td>
                            <td class="px-3 py-3 text-text-muted">{{ $roleNames ?: __('No role assigned') }}</td>
                            <td class="px-3 py-3"><x-status.badge :status="$user->status === 'active' ? 'active' : 'inactive'" :label="$user->status === 'active' ? __('Active') : __('Inactive')" /></td>
                            <td class="px-3 py-3"><x-status.badge :status="$user->email_verified_at ? 'active' : 'pending'" :label="$user->email_verified_at ? __('Verified') : __('Not verified')" /></td>
                            <td class="px-3 py-3 text-end">
                                @can('users_roles_permissions.edit')
                                    <x-actions.button semantic="edit" :label="__('Manage')" size="xs" wire:click="editAuthorization({{ $user->id }})" wire:loading.attr="disabled" wire:target="editAuthorization" data-guide="auth-users-manage-action">
                                        <span wire:loading.remove wire:target="editAuthorization">{{ __('Manage') }}</span>
                                        <span wire:loading wire:target="editAuthorization">{{ __('Opening authorization…') }}</span>
                                    </flux:button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <x-slot:footer>
            <div class="flex justify-end">{{ $users->links() }}</div>
        </x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="authorizationModalOpen" class="max-w-2xl">
        <form wire:submit="saveAuthorization" class="space-y-5">
            <div><flux:heading size="lg">{{ $editingUserId === null ? __('Create user') : __('User authorization') }}</flux:heading><flux:text class="mt-1 text-text-muted">{{ __('Assign only approved roles and approved branch or store scope.') }}</flux:text></div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="userForm.name" :label="__('Name')" required />
                <flux:input wire:model="userForm.username" :label="__('Username')" autocomplete="username" required />
                <flux:input wire:model="userForm.email" :label="__('Email')" type="email" required />
                <flux:input wire:model="userForm.password" :label="$editingUserId === null ? __('Password') : __('New password (optional)')" type="password" :required="$editingUserId === null" autocomplete="new-password" />
                <flux:select wire:model="userForm.status" :label="__('Status')">
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div x-data="{ roleSearch: '' }" class="space-y-2">
                    <details class="rounded-lg border border-border bg-surface-muted/20">
                        <summary class="cursor-pointer list-none px-3 py-2.5 font-semibold text-text-primary">{{ __('Roles') }}</summary>
                        <div class="space-y-3 border-t border-border p-3">
                            <flux:input x-model="roleSearch" :label="__('Search roles')" :placeholder="__('Search roles')" />
                            <flux:checkbox.group wire:model="roleIds" class="max-h-52 space-y-2 overflow-y-auto">
                                @foreach ($roles as $role)
                                    @php($roleLabel = str_starts_with(app()->getLocale(), 'ar') ? $role->name_ar : $role->name_en)
                                    <div x-show="roleSearch.trim() === '' || $el.dataset.roleLabel.includes(roleSearch.trim().toLowerCase())" data-role-label="{{ mb_strtolower($roleLabel) }}">
                                        <flux:checkbox value="{{ $role->id }}" :label="$roleLabel" />
                                    </div>
                                @endforeach
                            </flux:checkbox.group>
                            <p x-show="roleSearch.trim() !== '' && ![...$el.parentElement.querySelectorAll('[data-role-label]')].some(role => role.dataset.roleLabel.includes(roleSearch.trim().toLowerCase()))" class="text-sm text-text-muted">{{ __('No roles found') }}</p>
                        </div>
                    </details>
                </div>
                <flux:checkbox.group wire:model="branchIds" :label="__('Branch scopes')" class="space-y-2">
                    @foreach ($branches as $branch)
                        <flux:checkbox value="{{ $branch->id }}" :label="str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en" />
                    @endforeach
                </flux:checkbox.group>
                <flux:checkbox.group wire:model="storeIds" :label="__('Store scopes')" class="space-y-2">
                    @foreach ($stores as $store)
                        <flux:checkbox value="{{ $store->id }}" :label="str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en" />
                    @endforeach
                </flux:checkbox.group>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <flux:button type="button" variant="subtle" wire:click="closeAuthorization">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="saveAuthorization"><span wire:loading.remove wire:target="saveAuthorization">{{ __('Save authorization') }}</span><span wire:loading wire:target="saveAuthorization">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
