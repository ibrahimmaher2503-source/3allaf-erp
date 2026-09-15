<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class GiftCardPrintEvent extends Model
{
    protected $fillable = ['gift_card_id', 'printed_by', 'format', 'is_reprint', 'reason', 'idempotency_key', 'printed_at'];
    protected $casts = ['is_reprint' => 'boolean', 'printed_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Gift Card print history is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Gift Card print history is immutable.'));
    }
    public function giftCard(): BelongsTo { return $this->belongsTo(GiftCard::class); }
    public function printer(): BelongsTo { return $this->belongsTo(User::class, 'printed_by'); }
}
