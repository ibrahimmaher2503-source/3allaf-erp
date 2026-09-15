<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\LinkAttachmentToSource;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Capture one immutable, source-linked tender against an unapproved sale. */
final class CapturePaymentAction
{
    public function __construct(private readonly PosCalculationService $calculator) {}

    public function execute(
        User $cashier,
        Sale $sale,
        PaymentMethod $method,
        string $amount,
        string $idempotencyKey,
        ?string $tenderedAmount = null,
        ?string $evidenceReference = null,
        ?string $evidenceAttachmentId = null,
        ?GiftCard $giftCard = null,
    ): SalePayment {
        abort_unless($cashier->can('pos_sales.payment_create'), 403);

        try {
            return $this->attempt($cashier, $sale, $method, $amount, $idempotencyKey, $tenderedAmount, $evidenceReference, $evidenceAttachmentId, $giftCard);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'idempotency_key')) {
                throw $exception;
            }

            return $this->assertReplaySafe(
                SalePayment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail(),
                $sale,
                $method,
                $amount,
                $tenderedAmount,
                $evidenceReference,
                $evidenceAttachmentId,
                $giftCard,
            );
        }
    }

    private function attempt(
        User $cashier,
        Sale $sale,
        PaymentMethod $method,
        string $amount,
        string $idempotencyKey,
        ?string $tenderedAmount,
        ?string $evidenceReference,
        ?string $evidenceAttachmentId,
        ?GiftCard $giftCard,
    ): SalePayment {
        return DB::transaction(function () use ($cashier, $sale, $method, $amount, $idempotencyKey, $tenderedAmount, $evidenceReference, $evidenceAttachmentId, $giftCard): SalePayment {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            $existing = SalePayment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $sale, $method, $amount, $tenderedAmount, $evidenceReference, $evidenceAttachmentId, $giftCard);
            }

            if (in_array($sale->status, ['approved', 'cancelled'], true)) {
                throw new InvalidArgumentException(__('This sale is already settled and cannot accept another payment.'));
            }
            if ($method->status !== 'active') {
                throw new InvalidArgumentException(__('This payment method is not active.'));
            }

            $isGiftCard = (string) $method->type === 'gift_card';
            if ($isGiftCard !== ($giftCard instanceof GiftCard)) {
                throw new InvalidArgumentException($isGiftCard
                    ? __('A Gift Card tender requires a valid Gift Card.')
                    : __('A Gift Card may only be used with a Gift Card payment method.'));
            }
            if ($giftCard instanceof GiftCard) {
                $giftCard = GiftCard::query()->visibleTo($cashier)->findOrFail($giftCard->id);
                if ((int) $giftCard->branch_id !== (int) $sale->branch_id || (int) $giftCard->store_id !== (int) $sale->store_id) {
                    throw new InvalidArgumentException(__('The Gift Card is not valid for this sale scope.'));
                }
                if (strtoupper((string) $giftCard->currency_code) !== strtoupper((string) $sale->currency_code)) {
                    throw new InvalidArgumentException(__('The Gift Card currency does not match this sale.'));
                }
            }

            $isCash = $method->isCash();
            $payable = DecimalMoney::round((string) $sale->payable_total);
            $alreadyPaid = $this->paidSoFar($sale);
            $residual = bcsub($payable, $alreadyPaid, 2);
            if (bccomp($residual, '0', 2) <= 0) {
                throw new InvalidArgumentException(__('This sale is already fully settled.'));
            }

            if ($isCash) {
                // POSF-02: this check applies to every cash tender, including an
                // amount that happens to have no rounding delta.
                $this->calculator->cashRoundingDenomination();
                $creditCustomer = $sale->customer_id !== null && Customer::query()
                    ->whereKey($sale->customer_id)->where('status', 'active')
                    ->whereIn('customer_type', ['credit', 'both'])->exists();
                $amount = $creditCustomer
                    ? DecimalMoney::round($amount, 2, __('A payment amount must be a valid number.'))
                    : $residual;
                if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $residual, 2) > 0) {
                    throw new InvalidArgumentException(__('The cash payment must be positive and cannot exceed the outstanding amount.'));
                }
            } else {
                $amount = DecimalMoney::round($amount, 2, __('A payment amount must be a valid number.'));
                if (bccomp($amount, '0', 2) <= 0) {
                    throw new InvalidArgumentException(__('A payment amount must be greater than zero.'));
                }
                if (bccomp($amount, $residual, 2) > 0) {
                    throw new InvalidArgumentException(__('An electronic payment cannot exceed the outstanding amount.'));
                }
            }

            $attachment = $this->validatedEvidence($cashier, $sale, $method, $evidenceAttachmentId);
            if ($method->requires_evidence && $attachment === null) {
                throw new InvalidArgumentException(__('This electronic payment requires protected payment evidence before it can be recorded.'));
            }

            $change = '0.00';
            if ($isCash) {
                $tendered = $tenderedAmount === null || trim($tenderedAmount) === ''
                    ? $amount
                    : DecimalMoney::round($tenderedAmount, 2, __('The cash tendered must be a valid amount.'));
                if (bccomp($tendered, $amount, 2) < 0) {
                    throw new InvalidArgumentException(__('The tendered cash cannot be less than the remaining amount.'));
                }
                $change = bcsub($tendered, $amount, 2);
                $tenderedAmount = $tendered;
            } else {
                $tenderedAmount = null;
            }

            $payment = SalePayment::query()->create([
                'sale_id' => $sale->id,
                'payment_method_id' => $method->id,
                'gift_card_id' => $giftCard?->id,
                'method_code' => $method->code,
                'method_type' => $method->type,
                'amount' => $amount,
                'tendered_amount' => $tenderedAmount,
                'change_amount' => $change,
                'evidence_reference' => filled($evidenceReference) ? trim((string) $evidenceReference) : null,
                'evidence_attachment_id' => $attachment?->id,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $cashier->id,
            ]);

            if ($attachment !== null) {
                app(LinkAttachmentToSource::class)->execute(
                    $attachment,
                    new AttachmentSourceReference(
                        sourceType: SalePayment::class,
                        sourceId: (string) $payment->id,
                        branchId: (int) $sale->branch_id,
                        storeId: (int) $sale->store_id,
                        visibility: 'private',
                    ),
                    static fn (User $user, Attachment $candidate, AttachmentSourceReference $source): bool =>
                        $user->id === $cashier->id
                        && $user->can('pos_sales.payment_evidence_upload')
                        && $candidate->purpose === 'payment_evidence'
                        && $source->sourceType === SalePayment::class
                        && $source->sourceId === (string) $payment->id,
                );
            }

            if ($giftCard instanceof GiftCard) {
                app(GiftCardAction::class)->redeem(
                    $cashier,
                    $giftCard,
                    $amount,
                    'SALE-PAYMENT:'.$payment->id.':GIFT-CARD',
                    Sale::class,
                    (string) $sale->id,
                    'SALE:'.$sale->id,
                );
            }

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'sale_payment_captured',
                source: $payment,
                after: [
                    'sale_id' => $sale->id,
                    'method_code' => $payment->method_code,
                    'amount' => $amount,
                    'tendered_amount' => $tenderedAmount,
                    'change_amount' => $change,
                    'gift_card_id' => $giftCard?->id,
                    'evidence_attachment_id' => $attachment?->id,
                ],
                branchId: (int) $sale->branch_id,
                storeId: (int) $sale->store_id,
                metadata: ['idempotency_key' => $idempotencyKey, 'source_linked' => true],
            );

            return $payment->load('evidenceAttachment');
        });
    }

    private function validatedEvidence(User $cashier, Sale $sale, PaymentMethod $method, ?string $attachmentId): ?Attachment
    {
        if ($attachmentId === null || trim($attachmentId) === '') {
            return null;
        }

        abort_unless($cashier->can('pos_sales.payment_evidence_upload'), 403);

        $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachmentId);
        $valid = $attachment->status === AttachmentState::Temporary
            && $attachment->source_type === null
            && $attachment->source_id === null
            && $attachment->purpose === 'payment_evidence'
            && (int) $attachment->uploaded_by === (int) $cashier->id
            && (int) $attachment->branch_id === (int) $sale->branch_id
            && (int) $attachment->store_id === (int) $sale->store_id;

        if (! $valid) {
            throw new InvalidArgumentException(__('The selected payment evidence is not a valid temporary upload for this sale scope.'));
        }

        return $attachment;
    }

    private function assertReplaySafe(
        SalePayment $existing,
        Sale $sale,
        PaymentMethod $method,
        string $amount,
        ?string $tenderedAmount,
        ?string $evidenceReference,
        ?string $evidenceAttachmentId,
        ?GiftCard $giftCard,
    ): SalePayment {
        $isCash = $method->isCash();
        $creditCustomer = $isCash && $sale->customer_id !== null && Customer::query()
            ->whereKey($sale->customer_id)->where('status', 'active')
            ->whereIn('customer_type', ['credit', 'both'])->exists();
        $expectedAmount = $isCash && ! $creditCustomer ? (string) $existing->amount : DecimalMoney::round($amount);
        $expectedTendered = $tenderedAmount === null || trim($tenderedAmount) === '' ? null : DecimalMoney::round($tenderedAmount);
        $replaySafe = (int) $existing->sale_id === (int) $sale->id
            && (int) $existing->payment_method_id === (int) $method->id
            && (int) ($existing->gift_card_id ?? 0) === (int) ($giftCard?->id ?? 0)
            && bccomp((string) $existing->amount, $expectedAmount, 2) === 0
            && ($isCash || $existing->tendered_amount === null)
            && (! $isCash || $expectedTendered === null || bccomp((string) $existing->tendered_amount, $expectedTendered, 2) === 0)
            && (string) ($existing->evidence_reference ?? '') === trim((string) ($evidenceReference ?? ''))
            && (string) ($existing->evidence_attachment_id ?? '') === (string) ($evidenceAttachmentId ?? '');

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different payment payload.'));
        }

        return $existing;
    }

    /** @return numeric-string */
    public function paidSoFar(Sale $sale): string
    {
        return $this->sum($sale, 'amount');
    }

    /** @return numeric-string */
    public function changeSoFar(Sale $sale): string
    {
        return $this->sum($sale, 'change_amount');
    }

    /** @return numeric-string */
    private function sum(Sale $sale, string $column): string
    {
        $total = '0.00';
        foreach (SalePayment::query()->where('sale_id', $sale->id)->pluck($column) as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }
}
