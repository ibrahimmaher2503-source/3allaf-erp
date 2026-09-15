<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Platform\Models\UserUiPreference;
use App\Modules\Platform\Support\RequestPermissionLookup;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string $status
 * @property bool $is_super_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'status'])]
#[Hidden(['password', 'is_super_admin', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'status' => 'string',
            'password' => 'hashed',
        ];
    }

    /**
     * A disabled account is never privileged, even when the immutable
     * database flag records its former Super Admin designation.
     */
    protected function isSuperAdmin(): Attribute
    {
        return Attribute::get(fn (mixed $value): bool => (bool) $value && $this->status === 'active');
    }

    public function canBypassApproval(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function assignedCashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class, 'assigned_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasPermission(string $code): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        return isset($this->activePermissionLookup()[$code]);
    }

    /** @return array<string, true> */
    private function activePermissionLookup(): array
    {
        return app(RequestPermissionLookup::class)->for($this);
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        if ($this->is_super_admin) {
            return true;
        }

        return $this->branchScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->exists();
    }

    public function canAccessStore(int $storeId): bool
    {
        if ($this->status === 'inactive') {
            return false;
        }

        if ($this->is_super_admin) {
            return true;
        }

        if ($this->storeScopes()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->exists()) {
            return true;
        }

        $branchId = Store::query()->whereKey($storeId)->value('branch_id');

        return $branchId !== null && $this->canAccessBranch((int) $branchId);
    }

    public function branchScopes(): HasMany
    {
        return $this->hasMany(UserBranchScope::class);
    }

    public function storeScopes(): HasMany
    {
        return $this->hasMany(UserStoreScope::class);
    }

    public function uiPreference(): HasOne
    {
        return $this->hasOne(UserUiPreference::class);
    }
}
