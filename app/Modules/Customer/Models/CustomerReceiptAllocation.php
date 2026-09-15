<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CustomerReceiptAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_receipt_id', 'sale_id', 'amount', 'created_at'];

    protected $casts = ['amount' => 'decimal:4', 'created_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Customer receipt allocations are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Customer receipt allocations are immutable.'));
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
