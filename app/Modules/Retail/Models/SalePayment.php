<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Support\PaymentMethodSemantics;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SalePayment extends Model implements ImmutableSourceContract
{
    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'gift_card_id',
        'method_code',
        'method_type',
        'amount',
        'tendered_amount',
        'change_amount',
        'evidence_reference',
        'evidence_attachment_id',
        'idempotency_key',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tendered_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    /**
     * Payment rows are settlement evidence. Correcting one means reversing the
     * sale, not editing the tender (docs/19 immutability).
     */
    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Sale payments are immutable once captured.'));
        self::deleting(fn (): never => throw new LogicException('Sale payments are immutable once captured.'));
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return BelongsTo<GiftCard, $this> */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function evidenceAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'evidence_attachment_id');
    }

    public function isCash(): bool
    {
        return PaymentMethodSemantics::isCashType((string) $this->getAttribute('method_type'));
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
    public function sourceBranchId(): ?int { return $this->sale()->value('branch_id') === null ? null : (int) $this->sale()->value('branch_id'); }
    public function sourceStoreId(): ?int { return $this->sale()->value('store_id') === null ? null : (int) $this->sale()->value('store_id'); }
}
