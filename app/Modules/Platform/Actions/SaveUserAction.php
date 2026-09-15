<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Support\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveUserAction
{
    /** @param array<string, mixed> $data @param array<int, int> $roleIds @param array<int, int> $branchIds @param array<int, int> $storeIds */
    public function execute(array $data, array $roleIds, array $branchIds, array $storeIds, ?User $user = null): User
    {
        Gate::authorize($user === null ? 'users_roles_permissions.create' : 'users_roles_permissions.edit');
        $creating = $user === null;

        $status = (string) ($data['status'] ?? 'active');
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => __('The user status is invalid.')]);
        }

        $username = Str::lower(trim((string) ($data['username'] ?? '')));
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/', $username)) {
            throw ValidationException::withMessages(['username' => __('Username must be 3–50 lowercase letters, numbers, dots, underscores, or hyphens and start with a letter or number.')]);
        }

        try {
            return DB::transaction(function () use ($data, $roleIds, $branchIds, $storeIds, $user, $status, $username, $creating): User {
                $attributes = [
                    'name' => trim((string) $data['name']),
                    'username' => $username,
                    'email' => strtolower(trim((string) $data['email'])),
                    'status' => $status,
                ];
                if (filled($data['password'] ?? null)) {
                    $attributes['password'] = Hash::make((string) $data['password']);
                }

                if ($user === null) {
                    $saved = User::query()->create($attributes);
                    $event = 'create_user';
                    $before = null;
                } else {
                    $saved = User::query()->lockForUpdate()->findOrFail($user->id);
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 403);
                    app(UserManagementScope::class)->assertTargetIsManageable($actor, $saved);
                    $before = $saved->only(['name', 'username', 'email', 'status']);
                    $saved->update($attributes);
                    $event = 'update_user';
                }

                app(SaveUserAuthorizationAction::class)->execute($saved, $roleIds, $branchIds, $storeIds, $status, $creating);

                app(RecordAuditEvent::class)->execute(
                    category: 'authorization',
                    event: $event,
                    source: $saved,
                    before: $before,
                    after: $saved->fresh()->only(['name', 'username', 'email', 'status']),
                );

                return $saved->fresh();
            });
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'users_username_unique')) {
                throw ValidationException::withMessages(['username' => __('This username is already in use.')]);
            }

            if (str_contains($message, 'users_email_unique')) {
                throw ValidationException::withMessages(['email' => __('This email address is already in use.')]);
            }

            throw $exception;
        }
    }
}
