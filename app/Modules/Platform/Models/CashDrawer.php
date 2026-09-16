<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use Database\Factories\CashDrawerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class CashDrawer extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (CashDrawer $drawer): void {
            $branchId = (int) $drawer->getAttribute('branch_id');
            $branch = $branchId > 0 ? Branch::query()->find($branchId) : null;

            if ($branch === null || (int) $branch->getAttribute('company_id') < 1) {
                throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
            }

            $storeId = (int) $drawer->getAttribute('store_id');
            if ($storeId < 1) {
                if ($drawer->getAttribute('status') === 'active') {
                    throw new InvalidArgumentException(__('An active cash drawer requires a POS selling location / stock source. Assign the branch\'s active POS location before saving.'));
                }

                if (! Company::query()->whereKey((int) $branch->getAttribute('company_id'))->exists()) {
                    throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
                }

                $drawer->setAttribute('company_id', (int) $branch->getAttribute('company_id'));

                return;
            }

            $store = Store::query()->find($storeId);
            if ($store === null
                || (int) $store->getAttribute('branch_id') !== $branchId
                || (int) $store->getAttribute('company_id') < 1
                || (int) $store->getAttribute('company_id') !== (int) $branch->getAttribute('company_id')
                || ! Company::query()->whereKey((int) $store->getAttribute('company_id'))->exists()) {
                throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
            }

            // Store ownership is authoritative. Never persist a caller-supplied
            // company ID independently from the selected store.
            $drawer->setAttribute('company_id', (int) $store->getAttribute('company_id'));
        });
    }

    protected static function newFactory(): Factory
    {
        return CashDrawerFactory::new();
    }

    protected $fillable = [
        'company_id',
        'branch_id',
        'store_id',
        'assigned_user_id',
        'treasury_cash_account_id',
        'code',
        'name_ar',
        'name_en',
        'status',
        'policy_notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function treasuryCashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'treasury_cash_account_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }

    public function scopeOperationallyConsistent(Builder $query): Builder
    {
        return $query
            ->where('cash_drawers.company_id', '>', 0)
            ->where('cash_drawers.branch_id', '>', 0)
            ->where('cash_drawers.store_id', '>', 0)
            ->whereHas('company')
            ->whereHas('branch', fn (Builder $branch): Builder => $branch
                ->whereColumn('branches.company_id', 'cash_drawers.company_id'))
            ->whereHas('store', fn (Builder $store): Builder => $store
                ->whereColumn('stores.company_id', 'cash_drawers.company_id')
                ->whereColumn('stores.branch_id', 'cash_drawers.branch_id'));
    }
}
