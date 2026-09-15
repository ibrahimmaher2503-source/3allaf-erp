<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CashMovement extends Model implements ImmutableSourceContract
{
    public const TYPE_CASH_IN = 'cash_in';

    public const TYPE_CASH_OUT = 'cash_out';

    public const TYPE_PETTY_DISBURSEMENT = 'petty_disbursement';

    public const TYPE_SAFE_DEPOSIT = 'safe_deposit';

    public const TYPE_FLOAT_ADJUSTMENT = 'float_adjustment';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_CASH_IN,
        self::TYPE_CASH_OUT,
        self::TYPE_PETTY_DISBURSEMENT,
        self::TYPE_SAFE_DEPOSIT,
        self::TYPE_FLOAT_ADJUSTMENT,
    ];

    protected $fillable = [
        'shift_id', 'cash_drawer_id', 'branch_id', 'store_id', 'movement_type',
        'amount', 'reason', 'reference', 'idempotency_key', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'approved_at' => 'datetime'];

    /**
     * Expected totals are derived from these rows (`docs/32` §9), so a movement
     * is corrected by a referenced counter-movement, never by editing.
     */
    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Cash movements are append-only; post a referenced correction instead.'));
        self::deleting(fn (): never => throw new LogicException('Cash movements are append-only; post a referenced correction instead.'));
    }

    /** @return BelongsTo<PosShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    /** @return BelongsTo<CashDrawer, $this> */
    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceType(): string { return self::class; }
    public function sourceId(): string { return (string) $this->getKey(); }
    public function sourceState(): string { return 'posted'; }
    public function sourceVersion(): ?string { return null; }
    public function sourceHash(): ?string
    {
        $attributes = $this->getAttributes();
        unset($attributes['created_at'], $attributes['updated_at']);
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    public function sourceBranchId(): ?int { return $this->branch_id === null ? null : (int) $this->branch_id; }
    public function sourceStoreId(): ?int { return $this->store_id === null ? null : (int) $this->store_id; }
}
