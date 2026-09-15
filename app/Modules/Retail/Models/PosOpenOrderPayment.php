<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PosOpenOrderPayment extends Model
{
    protected $fillable = ['pos_open_order_id', 'payment_method_id', 'line_position', 'amount', 'tendered_amount', 'safe_reference', 'gift_card_identifier'];
    protected $casts = ['amount' => 'decimal:2', 'tendered_amount' => 'decimal:2'];

    public function order(): BelongsTo { return $this->belongsTo(PosOpenOrder::class, 'pos_open_order_id'); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
}
