<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class RetailReturnSettlement extends Model
{
    protected $fillable = ["retail_return_id", "payment_method_id", "gift_card_id", "original_payment_id", "allocation_position", "method_code_snapshot", "method_type_snapshot", "safe_reference", "direction", "amount", "settlement_type", "idempotency_key", "created_by", "reason"];
    protected $casts = ['amount' => 'decimal:2'];
    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Return settlements are append-only; post a referenced correction instead.'));
        self::deleting(fn (): never => throw new LogicException('Return settlements are append-only; post a referenced correction instead.'));
    }
    public function retailReturn(): BelongsTo { return $this->belongsTo(RetailReturn::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function giftCard(): BelongsTo { return $this->belongsTo(GiftCard::class); }
    public function originalPayment(): BelongsTo { return $this->belongsTo(SalePayment::class, 'original_payment_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
