<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class LoyaltyPointAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['debit_ledger_id', 'earn_ledger_id', 'points', 'created_at'];

    protected $casts = ['points' => 'integer', 'created_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Loyalty allocations are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Loyalty allocations cannot be deleted.'));
    }

    /** @return BelongsTo<LoyaltyLedger, $this> */
    public function debitLedger(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLedger::class, 'debit_ledger_id');
    }

    /** @return BelongsTo<LoyaltyLedger, $this> */
    public function earnLedger(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLedger::class, 'earn_ledger_id');
    }
}
