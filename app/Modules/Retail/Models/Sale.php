<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerReceiptAllocation;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Sale extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['branch_id', 'store_id', 'cash_drawer_id', 'shift_id', 'cashier_id', 'customer_id', 'document_number', 'status', 'idempotency_key', 'request_fingerprint', 'subtotal', 'discount_total', 'invoice_discount_type', 'invoice_discount_reason', 'invoice_discount_applied_by', 'tax_total', 'tax_applicable', 'tax_setting_id', 'tax_rate_snapshot', 'tax_treatment_snapshot', 'tax_inclusive_snapshot', 'total', 'paid_total', 'outstanding_amount', 'payment_status', 'change_total', 'cash_rounding_amount', 'payable_total', 'currency_code', 'notes', 'approved_at', 'suspended_at', 'lock_version'];

    protected $casts = ['subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'paid_total' => 'decimal:2', 'outstanding_amount' => 'decimal:4', 'change_total' => 'decimal:2', 'cash_rounding_amount' => 'decimal:2', 'payable_total' => 'decimal:2', 'tax_applicable' => 'boolean', 'tax_inclusive_snapshot' => 'boolean', 'tax_rate_snapshot' => 'decimal:2', 'approved_at' => 'datetime', 'suspended_at' => 'datetime'];

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(CustomerReceiptAllocation::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<CashDrawer, $this> */
    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
    }

    /** @return BelongsTo<PosShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /** @return HasOne<SuspendedSale, $this> */
    public function suspendedSale(): HasOne
    {
        return $this->hasOne(SuspendedSale::class);
    }

    /** @return HasMany<SalePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /** @return HasMany<RetailReturn, $this> */
    public function retailReturns(): HasMany
    {
        return $this->hasMany(RetailReturn::class, 'source_sale_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
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
}
