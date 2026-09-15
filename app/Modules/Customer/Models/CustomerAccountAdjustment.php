<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class CustomerAccountAdjustment extends Model
{
    protected $fillable = ['public_id', 'customer_id', 'sale_id', 'adjustment_date', 'amount', 'reason', 'reference', 'status', 'created_by', 'approved_by', 'approved_at', 'idempotency_key', 'payload_hash'];

    protected $casts = ['adjustment_date' => 'date', 'amount' => 'decimal:4', 'approved_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $adjustment): string => $adjustment->public_id ??= (string) Str::uuid());
        self::updating(fn (): never => throw new LogicException('Customer account adjustments are immutable; correct them by reference.'));
        self::deleting(fn (): never => throw new LogicException('Customer account adjustments are immutable; correct them by reference.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
