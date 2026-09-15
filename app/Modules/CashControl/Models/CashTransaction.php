<?php

declare(strict_types=1);

namespace App\Modules\CashControl\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

final class CashTransaction extends Model
{
    protected $fillable = ['cash_account_id', 'transaction_date', 'amount', 'transaction_type', 'source_type', 'source_id', 'reversal_of_id', 'idempotency_key', 'payload_hash', 'description', 'reference', 'created_by'];

    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:4'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Cash transactions are append-only; post a reversal instead.'));
        self::deleting(fn (): never => throw new LogicException('Cash transactions are append-only and cannot be deleted.'));
    }

    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }

    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }

    public function reversal(): HasOne { return $this->hasOne(self::class, 'reversal_of_id'); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
