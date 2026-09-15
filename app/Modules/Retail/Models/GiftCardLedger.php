<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class GiftCardLedger extends Model
{
    public $timestamps = false;
    protected $table = 'gift_card_ledger';
    protected $fillable = ['gift_card_id', 'event_type', 'amount', 'balance_before', 'balance_after', 'source_type', 'source_id', 'source_reference', 'reason', 'created_by', 'idempotency_key', 'metadata', 'created_at'];
    protected $casts = ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2', 'metadata' => 'array', 'created_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Gift Card ledger entries are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Gift Card ledger entries are immutable.'));
    }
    public function giftCard(): BelongsTo { return $this->belongsTo(GiftCard::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
