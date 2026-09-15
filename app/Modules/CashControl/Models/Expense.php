<?php

declare(strict_types=1);

namespace App\Modules\CashControl\Models;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class Expense extends Model
{
    protected $fillable = ['company_id', 'expense_category_id', 'cash_account_id', 'payment_method_id', 'expense_date', 'amount', 'description', 'reference', 'status', 'idempotency_key', 'payload_hash', 'created_by', 'approved_by', 'approved_at'];

    protected $casts = ['expense_date' => 'date', 'amount' => 'decimal:4', 'approved_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Approved expenses are immutable; correct them by reference.'));
        self::deleting(fn (): never => throw new LogicException('Approved expenses are immutable and cannot be deleted.'));
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }

    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }

    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
