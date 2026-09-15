<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CustomerMergeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'duplicate_customer_id', 'survivor_customer_id', 'reason', 'merged_by',
        'branch_id', 'store_id', 'idempotency_key', 'created_at',
    ];

    protected $casts = ['created_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Customer merge history is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Customer merge history cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function duplicateCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'duplicate_customer_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function survivorCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'survivor_customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function merger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
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
}
