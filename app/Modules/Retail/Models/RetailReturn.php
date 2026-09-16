<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class RetailReturn extends Model
{
    protected $fillable = ["branch_id", "store_id", "shift_id", "cash_drawer_id", "cashier_id", "approved_by", "rejected_by", "customer_id", "source_sale_id", "source_gift_receipt_id", "approval_record_id", "return_number", "status", "settlement_type", "reason", "rejection_reason", "eligible_value", "subtotal_refund", "discount_refund", "tax_refund", "rounding_refund", "settlement_value", "ar_reduction_value", "actual_refund_value", "currency_code", "idempotency_key", "payload_hash", "completion_idempotency_key", "completion_payload_hash", "financial_reversal_snapshot", "submitted_at", "approved_at", "rejected_at", "completed_at", "lock_version"];
    protected $casts = ["eligible_value" => "decimal:2", "subtotal_refund" => "decimal:2", "discount_refund" => "decimal:2", "tax_refund" => "decimal:2", "rounding_refund" => "decimal:2", "settlement_value" => "decimal:2", "ar_reduction_value" => "decimal:2", "actual_refund_value" => "decimal:2", "financial_reversal_snapshot" => "array", "submitted_at" => "datetime", "approved_at" => "datetime", "rejected_at" => "datetime", "completed_at" => "datetime"];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function shift(): BelongsTo { return $this->belongsTo(PosShift::class); }
    public function cashDrawer(): BelongsTo { return $this->belongsTo(CashDrawer::class); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function sourceSale(): BelongsTo { return $this->belongsTo(Sale::class, 'source_sale_id'); }
    public function sourceGiftReceipt(): BelongsTo { return $this->belongsTo(GiftReceipt::class, 'source_gift_receipt_id'); }
    public function lines(): HasMany { return $this->hasMany(RetailReturnLine::class); }
    public function exchange(): HasOne { return $this->hasOne(Exchange::class); }
    public function settlements(): HasMany { return $this->hasMany(RetailReturnSettlement::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
