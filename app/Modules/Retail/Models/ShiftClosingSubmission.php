<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One blind close attempt (`docs/32` §11). Stored immutably with its
 * server-derived expected values so a later recount cannot rewrite history.
 */
final class ShiftClosingSubmission extends Model
{
    protected $fillable = [
        'shift_id', 'attempt', 'actual_cash', 'actual_by_method', 'expected_cash', 'expected_by_method',
        'cash_variance', 'method_variance', 'total_variance', 'notes', 'idempotency_key', 'submitted_by', 'submitted_at',
    ];

    protected $casts = [
        'actual_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_variance' => 'decimal:2',
        'total_variance' => 'decimal:2',
        'actual_by_method' => 'array',
        'expected_by_method' => 'array',
        'method_variance' => 'array',
        'submitted_at' => 'datetime',
        'attempt' => 'integer',
    ];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('A submitted close is immutable; submit a new recount attempt instead.'));
        self::deleting(fn (): never => throw new LogicException('A submitted close is immutable.'));
    }

    /** @return BelongsTo<PosShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
