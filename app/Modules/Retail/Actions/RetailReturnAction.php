<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Models\Exchange;
use App\Modules\Retail\Models\ExchangeLine;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\RetailReturnLine;
use App\Modules\Retail\Models\RetailReturnSettlement;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PaymentReferenceGuard;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\RetailRefundCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RetailReturnAction
{
    public function __construct(private readonly RetailRefundCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data, string $idempotencyKey): RetailReturn
    {
        $this->authorize($actor, 'returns.create');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => __('A refund idempotency key is required.')]);
        }
        $payloadHash = $this->payloadHash($data);

        return DB::transaction(function () use ($actor, $data, $idempotencyKey, $payloadHash): RetailReturn {
            $existing = RetailReturn::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! RetailReturn::query()->visibleTo($actor)->whereKey($existing->id)->exists() || ! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => __('This refund request token was already used with different details or scope.')]);
                }

                return $existing->load('lines', 'exchange.lines');
            }

            $receipt = $this->resolveReceipt($actor, $data);
            $saleId = (int) ($receipt?->sale_id ?? ($data['source_sale_id'] ?? 0));
            $sale = Sale::query()->visibleTo($actor)->approved()
                ->with(['lines', 'payments.paymentMethod', 'store.company'])
                ->whereKey($saleId)->lockForUpdate()->first();
            if ($sale === null) {
                throw ValidationException::withMessages(['source' => __('The original sale is unavailable, outside your scope, or not posted.')]);
            }
            if ($receipt !== null && (int) $receipt->store_id !== (int) $sale->store_id) {
                throw ValidationException::withMessages(['source' => __('The Gift Receipt source is inconsistent.')]);
            }

            $prepared = $this->calculator->prepare($actor, $sale, is_array($data['lines'] ?? null) ? $data['lines'] : [], $receipt);
            $settlementType = (string) ($data['settlement_type'] ?? 'original_tender');
            if (! in_array($settlementType, ['cash_refund', 'original_tender', 'gift_card', 'exchange'], true)) {
                throw ValidationException::withMessages(['settlement_type' => __('Select a supported refund method.')]);
            }
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => __('A refund reason is required.')]);
            }

            $totals = $prepared['totals'];
            $return = RetailReturn::query()->create([
                'branch_id' => $sale->branch_id,
                'store_id' => $sale->store_id,
                'cashier_id' => $actor->id,
                'customer_id' => $sale->customer_id,
                'source_sale_id' => $sale->id,
                'source_gift_receipt_id' => $receipt?->id,
                'return_number' => $this->number('retail_return', 'RET-', (int) $sale->branch_id),
                'status' => 'draft',
                'settlement_type' => $settlementType,
                'reason' => $reason,
                'eligible_value' => $totals['net_refund'],
                'subtotal_refund' => $totals['subtotal_refund'],
                'discount_refund' => $totals['discount_refund'],
                'tax_refund' => $totals['tax_refund'],
                'rounding_refund' => $totals['rounding_refund'],
                'settlement_value' => $totals['settlement_value'],
                'currency_code' => (string) $sale->currency_code,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'lock_version' => 1,
            ]);
            foreach ($prepared['lines'] as $line) {
                $return->lines()->create($line);
            }
            if ($settlementType === 'exchange') {
                $this->createExchange($return, $actor, is_array($data['exchange_lines'] ?? null) ? $data['exchange_lines'] : [], (int) $sale->store_id);
            }

            app(RecordAuditEvent::class)->execute(
                'retail', 'retail_return_created', $return, null,
                $this->auditReturn($return, $prepared['lines']),
                (int) $sale->branch_id, (int) $sale->store_id,
                reasonText: $return->reason,
                metadata: ['actor_id' => $actor->id, 'source_sale_id' => $sale->id, 'source_gift_receipt_id' => $receipt?->id],
            );

            return $return->load('lines', 'exchange.lines');
        }, 5);
    }

    public function submit(User $actor, RetailReturn $return): RetailReturn
    {
        $this->authorize($actor, 'returns.submit');

        return DB::transaction(function () use ($actor, $return): RetailReturn {
            $locked = $this->lockVisible($actor, $return);
            if (! in_array($locked->status, ['draft', 'inspection'], true)) {
                throw ValidationException::withMessages(['status' => __('Only a draft refund can be submitted.')]);
            }
            $before = ['status' => $locked->status];
            $locked->update(['status' => 'submitted', 'submitted_at' => now(), 'lock_version' => (int) $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_submitted', $locked, $before, ['status' => 'submitted'], (int) $locked->branch_id, (int) $locked->store_id, metadata: ['actor_id' => $actor->id]);

            return $locked->fresh('lines', 'exchange.lines');
        }, 5);
    }

    public function approve(User $actor, RetailReturn $return): RetailReturn
    {
        $this->authorize($actor, 'returns.approve');

        return DB::transaction(function () use ($actor, $return): RetailReturn {
            $locked = $this->lockVisible($actor, $return);
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => __('Only a submitted refund can be approved.')]);
            }
            if ((int) $locked->cashier_id === (int) $actor->id && ! $actor->canBypassApproval()) {
                throw ValidationException::withMessages(['approval' => __('The refund creator cannot approve their own refund.')]);
            }
            $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'lock_version' => (int) $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_approved', $locked, ['status' => 'submitted'], ['status' => 'approved', 'approved_by' => $actor->id], (int) $locked->branch_id, (int) $locked->store_id, metadata: ['actor_id' => $actor->id]);

            return $locked->fresh('lines', 'exchange.lines');
        }, 5);
    }

    public function reject(User $actor, RetailReturn $return, string $reason): RetailReturn
    {
        $this->authorize($actor, 'returns.approve');
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => __('A refund rejection reason is required.')]);
        }

        return DB::transaction(function () use ($actor, $return, $reason): RetailReturn {
            $locked = $this->lockVisible($actor, $return);
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => __('Only a submitted refund can be rejected.')]);
            }
            if ((int) $locked->cashier_id === (int) $actor->id && ! $actor->canBypassApproval()) {
                throw ValidationException::withMessages(['approval' => __('The refund creator cannot reject their own refund approval request.')]);
            }
            $locked->update(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejection_reason' => $reason, 'rejected_at' => now(), 'lock_version' => (int) $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_rejected', $locked, ['status' => 'submitted'], ['status' => 'rejected', 'rejected_by' => $actor->id], (int) $locked->branch_id, (int) $locked->store_id, reasonText: $reason, metadata: ['actor_id' => $actor->id]);

            return $locked->fresh('lines');
        }, 5);
    }

    /**
     * @param array<int, array<string, mixed>> $allocations
     */
    public function complete(User $actor, RetailReturn $return, string $idempotencyKey, ?int $paymentMethodId = null, ?int $originalPaymentId = null, array $allocations = []): RetailReturn
    {
        $this->authorize($actor, 'returns.complete');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => __('A refund completion idempotency key is required.')]);
        }
        $completionHash = $this->payloadHash(['return_id' => $return->id, 'payment_method_id' => $paymentMethodId, 'original_payment_id' => $originalPaymentId, 'allocations' => $allocations]);
        $lockName = 'toyjoy:return-sale:'.(int) $return->source_sale_id;
        $lockAcquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockName])->acquired ?? 0) === 1;
        if (! $lockAcquired) {
            throw ValidationException::withMessages(['concurrency' => __('Another refund for this sale is being completed. Retry after it finishes.')]);
        }

        try {
            return DB::transaction(function () use ($actor, $return, $idempotencyKey, $paymentMethodId, $originalPaymentId, $allocations, $completionHash): RetailReturn {
                $locked = $this->lockVisible($actor, $return)->load(['lines', 'exchange.lines']);
                if ($locked->status === 'completed') {
                    if ($locked->completion_idempotency_key === null || (hash_equals((string) $locked->completion_idempotency_key, $idempotencyKey) && hash_equals((string) $locked->completion_payload_hash, $completionHash))) {
                        return $locked->load('settlements');
                    }
                    throw ValidationException::withMessages(['idempotency_key' => __('This refund is already completed and cannot be posted again.')]);
                }
                if ($locked->status !== 'approved') {
                    throw ValidationException::withMessages(['status' => __('Only an approved refund can be completed.')]);
                }
                $keyOwner = RetailReturn::query()->where('completion_idempotency_key', $idempotencyKey)->where('id', '!=', $locked->id)->lockForUpdate()->first();
                if ($keyOwner !== null) {
                    throw ValidationException::withMessages(['idempotency_key' => __('This refund completion token was already used for another document.')]);
                }

                $sourceSale = Sale::query()->visibleTo($actor)->approved()
                    ->with(['store.company', 'payments.paymentMethod'])
                    ->whereKey($locked->source_sale_id)->lockForUpdate()->first();
                if ($sourceSale === null || (int) $sourceSale->branch_id !== (int) $locked->branch_id || (int) $sourceSale->store_id !== (int) $locked->store_id) {
                    throw ValidationException::withMessages(['source' => __('The original sale is no longer valid in this refund scope.')]);
                }

                $before = $locked->only(['status', 'eligible_value', 'settlement_value']);
                $giftReceipt = $locked->source_gift_receipt_id === null ? null : GiftReceipt::query()->visibleTo($actor)->whereKey($locked->source_gift_receipt_id)->first();
                $revalidated = $this->calculator->prepare($actor, $sourceSale, $locked->lines->map(fn (RetailReturnLine $line): array => [
                    'sale_line_id' => $line->sale_line_id,
                    'quantity' => (string) $line->quantity,
                    'condition' => $line->condition,
                    'disposition' => $line->disposition,
                    'return_store_id' => $line->return_store_id,
                ])->all(), $giftReceipt, (int) $locked->id);
                $legacyReturn = $locked->lines->contains(fn (RetailReturnLine $line): bool => $line->return_store_id === null);
                $expectedTotals = [
                    'eligible_value' => $revalidated['totals']['net_refund'],
                    'subtotal_refund' => $revalidated['totals']['subtotal_refund'],
                    'discount_refund' => $revalidated['totals']['discount_refund'],
                    'tax_refund' => $revalidated['totals']['tax_refund'],
                    'rounding_refund' => $revalidated['totals']['rounding_refund'],
                    'settlement_value' => $revalidated['totals']['settlement_value'],
                ];
                if (! $legacyReturn) {
                    foreach ($expectedTotals as $field => $value) {
                        if (bccomp((string) $locked->{$field}, $value, 2) !== 0) {
                            throw ValidationException::withMessages(['totals' => __('The approved refund totals no longer match the immutable original sale.')]);
                        }
                    }
                    $revalidatedLines = collect($revalidated['lines'])->keyBy('sale_line_id');
                    foreach ($locked->lines as $line) {
                        $expected = $revalidatedLines->get($line->sale_line_id);
                        foreach (['gross_value', 'discount_value', 'eligible_value', 'tax_value'] as $field) {
                            if (! is_array($expected) || bccomp((string) $line->{$field}, (string) ($expected[$field] ?? ''), 2) !== 0) {
                                throw ValidationException::withMessages(['totals' => __('The approved refund line values no longer match the original sale.')]);
                            }
                        }
                    }
                }
                $inventoryCost = '0.0000';
                foreach ($locked->lines as $line) {
                    $saleLine = SaleLine::query()->whereKey($line->sale_line_id)->lockForUpdate()->first();
                    if ($saleLine === null || (int) $saleLine->sale_id !== (int) $sourceSale->id) {
                        throw ValidationException::withMessages(['lines' => __('A refund item is not from the verified original sale.')]);
                    }
                    $remaining = $this->calculator->remainingQuantity($saleLine, (int) $locked->id);
                    if (bccomp((string) $line->quantity, $remaining, 6) > 0) {
                        throw ValidationException::withMessages(['lines' => __('A selected quantity is no longer refundable.')]);
                    }
                    $legacyReturnStoreId = $line->return_store_id;
                    if ($legacyReturnStoreId === null && $this->requiresDamagedStore($line)) {
                        $legacyReturnStoreId = Store::query()->visibleTo($actor)->where('company_id', $sourceSale->store?->company_id)->where('branch_id', $locked->branch_id)->where('type', 'damaged')->where('status', 'active')->value('id');
                    }
                    $returnStore = Store::query()->visibleTo($actor)->whereKey($legacyReturnStoreId ?: $locked->store_id)
                        ->where('company_id', $sourceSale->store?->company_id)
                        ->where('branch_id', $locked->branch_id)->where('status', 'active')->lockForUpdate()->first();
                    if ($returnStore === null) {
                        throw ValidationException::withMessages(['return_store' => __('The authorized inventory return location is no longer available.')]);
                    }
                    if ($this->requiresDamagedStore($line) && $returnStore->type !== 'damaged') {
                        throw ValidationException::withMessages(['return_store' => __('Damaged or non-saleable items require an active damaged inventory location.')]);
                    }
                    $unitCost = bccomp((string) $saleLine->quantity, '0', 6) > 0 && $saleLine->consumed_cost !== null
                        ? bcdiv((string) $saleLine->consumed_cost, (string) $saleLine->quantity, 4)
                        : '0.0000';
                    $movementType = $this->requiresDamagedStore($line) ? 'retail_return_damaged' : 'retail_return';
                    app(PostInventoryMovement::class)->execute(
                        (int) $line->product_id,
                        (int) $returnStore->id,
                        (string) $line->quantity,
                        $movementType,
                        $unitCost,
                        'retail-return:'.$locked->id.':line:'.$line->id,
                        RetailReturn::class,
                        (int) $locked->id,
                        (int) $line->id,
                        false,
                        $saleLine->stock_movement_id === null ? null : (int) $saleLine->stock_movement_id,
                    );
                    $inventoryCost = bcadd($inventoryCost, bcmul((string) $line->quantity, $unitCost, 4), 4);
                }

                $entitlement = (string) $locked->settlement_value;
                $arReduction = app(CustomerBalance::class)->outstandingForSale($sourceSale);
                $arReduction = bccomp($arReduction, $entitlement, 2) > 0 ? $entitlement : $arReduction;
                $actualRefund = bcsub($entitlement, $arReduction, 2);
                $locked->ar_reduction_value = $arReduction;
                $locked->actual_refund_value = $locked->settlement_type === 'gift_card' ? '0.00' : $actualRefund;
                $settlementValue = $locked->settlement_type === 'gift_card' ? $entitlement : $actualRefund;
                $locked->settlement_value = $settlementValue;
                $paymentSnapshot = [];
                if ($locked->settlement_type === 'gift_card') {
                    $card = app(GiftCardAction::class)->issue($actor, $settlementValue, (int) $locked->branch_id, (int) $locked->store_id, RetailReturn::class, (string) $locked->id, 'return-gift-card:'.$locked->id, $locked->return_number, $locked->customer_id, (string) $locked->currency_code);
                    RetailReturnSettlement::query()->create($this->settlementAttributes($locked, $actor, 1, $settlementValue, $idempotencyKey, 'gift_card', null, null, null, $card->id));
                    $paymentSnapshot[] = ['type' => 'gift_card', 'amount' => $settlementValue, 'gift_card_id' => $card->id];
                } elseif ($locked->settlement_type === 'exchange') {
                    $exchange = $locked->exchange;
                    if (! $exchange instanceof Exchange) {
                        throw ValidationException::withMessages(['exchange' => __('The approved exchange details are unavailable.')]);
                    }
                    foreach ($exchange->lines as $line) {
                        app(PostInventoryMovement::class)->execute((int) $line->product_id, (int) $locked->store_id, '-'.(string) $line->quantity, 'retail_exchange_out', null, 'retail-return:'.$locked->id.':exchange:'.$line->id, RetailReturn::class, (int) $locked->id, (int) $line->id);
                    }
                    $difference = (string) $exchange->difference_value;
                    if (bccomp($difference, '0.00', 2) !== 0) {
                        $method = PaymentMethod::query()->whereKey($paymentMethodId)->where('status', 'active')->first();
                        if ($method === null) {
                            throw ValidationException::withMessages(['payment' => __('Select an active payment method for the exchange difference.')]);
                        }
                        $amount = ltrim($difference, '-');
                        $direction = bccomp($difference, '0.00', 2) > 0 ? 'collect' : 'refund';
                        if ($direction === 'refund' && $method->isCash()) {
                            $context = app(PosContextResolver::class)->resolve($actor);
                            if (! $context->isReady() || (int) $context->branch?->id !== (int) $locked->branch_id || (int) $context->store?->id !== (int) $locked->store_id) {
                                throw ValidationException::withMessages(['cash_refund' => __('Cash refunds require your active authorized shift and drawer at the original selling outlet.')]);
                            }
                            $shift = \App\Modules\Retail\Models\PosShift::query()->whereKey($context->shift?->id)->lockForUpdate()->first();
                            $assigned = $shift !== null && DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)->where('cashier_id', $actor->id)->where('cash_drawer_id', $context->drawer?->id)->lockForUpdate()->exists();
                            if (! $assigned || ! $shift?->status->acceptsActivity()) {
                                throw ValidationException::withMessages(['cash_refund' => __('Cash refunds are blocked because the cashier shift or drawer is no longer open.')]);
                            }
                            $locked->shift_id = $shift->id;
                            $locked->cash_drawer_id = $context->drawer?->id;
                        }
                        RetailReturnSettlement::query()->create($this->settlementAttributes($locked, $actor, 1, $amount, $idempotencyKey, 'exchange_difference', $method, null, null, null, $direction));
                        $paymentSnapshot[] = ['method_code' => $method->code, 'method_type' => $method->type, 'direction' => $direction, 'amount' => $amount];
                    }
                    $exchange->update(['status' => 'completed']);
                } elseif (bccomp($settlementValue, '0.00', 2) > 0) {
                    $resolved = $this->resolveRefundAllocations($actor, $locked, $sourceSale, $settlementValue, $paymentMethodId, $originalPaymentId, $allocations);
                    foreach ($resolved as $index => $allocation) {
                        RetailReturnSettlement::query()->create($this->settlementAttributes(
                            $locked, $actor, $index + 1, $allocation['amount'], $idempotencyKey,
                            $locked->settlement_type, $allocation['method'], $allocation['original_payment_id'], $allocation['safe_reference'],
                        ));
                        $paymentSnapshot[] = [
                            'position' => $index + 1,
                            'method_code' => $allocation['method']->code,
                            'method_type' => $allocation['method']->type,
                            'amount' => $allocation['amount'],
                            'original_payment_id' => $allocation['original_payment_id'],
                            'safe_reference' => $allocation['safe_reference'],
                        ];
                    }
                    if (collect($resolved)->contains(fn (array $line): bool => $line['method']->isCash())) {
                        $context = app(PosContextResolver::class)->resolve($actor);
                        if (! $context->isReady() || (int) $context->branch?->id !== (int) $locked->branch_id || (int) $context->store?->id !== (int) $locked->store_id) {
                            throw ValidationException::withMessages(['cash_refund' => __('Cash refunds require your active authorized shift and drawer at the original selling outlet.')]);
                        }
                        $shift = \App\Modules\Retail\Models\PosShift::query()->whereKey($context->shift?->id)->lockForUpdate()->first();
                        $assigned = $shift !== null && DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)->where('cashier_id', $actor->id)->where('cash_drawer_id', $context->drawer?->id)->lockForUpdate()->exists();
                        if (! $assigned || ! $shift?->status->acceptsActivity()) {
                            throw ValidationException::withMessages(['cash_refund' => __('Cash refunds are blocked because the cashier shift or drawer is no longer open.')]);
                        }
                        $locked->shift_id = $shift->id;
                        $locked->cash_drawer_id = $context->drawer?->id;
                    }
                }

                $snapshot = [
                    'source_sale_id' => (int) $sourceSale->id,
                    'source_document_number' => (string) $sourceSale->document_number,
                    'currency_code' => (string) $locked->currency_code,
                    'revenue_reversal' => (string) $locked->eligible_value,
                    'gross_reversal' => (string) $locked->subtotal_refund,
                    'discount_reversal' => (string) $locked->discount_refund,
                    'tax_reversal' => (string) $locked->tax_refund,
                    'cash_rounding_reversal' => (string) $locked->rounding_refund,
                    'settlement_reversal' => $settlementValue,
                    'ar_reduction' => $arReduction,
                    'actual_refund' => (string) $locked->actual_refund_value,
                    'inventory_cost_returned' => $inventoryCost,
                    'payments' => $paymentSnapshot,
                    'approval' => ['approved_by' => $locked->approved_by, 'approved_at' => $locked->approved_at?->toIso8601String()],
                ];
                $locked->status = 'completed';
                $locked->completed_at = now();
                $locked->completion_idempotency_key = $idempotencyKey;
                $locked->completion_payload_hash = $completionHash;
                $locked->financial_reversal_snapshot = $snapshot;
                $locked->lock_version = (int) $locked->lock_version + 1;
                $locked->save();

                if ($locked->source_gift_receipt_id !== null) {
                    $receipt = GiftReceipt::query()->whereKey($locked->source_gift_receipt_id)->lockForUpdate()->first();
                    if ($receipt === null || $receipt->status !== 'active') {
                        throw ValidationException::withMessages(['source' => __('The Gift Receipt was already used by another refund.')]);
                    }
                    $receipt->update(['status' => 'used', 'used_return_id' => $locked->id, 'used_by' => $actor->id, 'used_at' => now(), 'lock_version' => (int) $receipt->lock_version + 1]);
                    app(RecordAuditEvent::class)->execute('retail', 'gift_receipt_used', $receipt, ['status' => 'active'], ['status' => 'used', 'return_id' => $locked->id], (int) $receipt->branch_id, (int) $receipt->store_id, metadata: ['actor_id' => $actor->id]);
                }

                app(RecordAuditEvent::class)->execute('retail', 'retail_return_completed', $locked, $before, ['status' => 'completed', 'financial_reversal_snapshot' => $snapshot], (int) $locked->branch_id, (int) $locked->store_id, reasonText: $locked->reason, metadata: ['actor_id' => $actor->id, 'source_sale_id' => $locked->source_sale_id, 'idempotency_key' => $idempotencyKey]);

                return $locked->fresh(['lines.returnStore', 'settlements.paymentMethod', 'exchange.lines']);
            }, 5);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    /** @param array<string, mixed> $data */
    private function resolveReceipt(User $actor, array $data): ?GiftReceipt
    {
        if (empty($data['source_gift_receipt_id']) && empty($data['source_gift_receipt_reference'])) {
            return null;
        }
        $query = GiftReceipt::query()->visibleTo($actor)->with(['lines', 'sale'])->lockForUpdate();
        $receipt = ! empty($data['source_gift_receipt_id'])
            ? $query->whereKey((int) $data['source_gift_receipt_id'])->first()
            : $query->where('reference', trim((string) $data['source_gift_receipt_reference']))->first();
        if ($receipt === null) {
            throw ValidationException::withMessages(['source' => __('The Gift Receipt is not available in your scope.')]);
        }
        if ($receipt->status !== 'active') {
            throw ValidationException::withMessages(['source' => __('The Gift Receipt has already been used or voided.')]);
        }

        return $receipt;
    }

    /**
     * @param array<int, array<string, mixed>> $allocations
     * @return array<int, array{method: PaymentMethod, original_payment_id: ?int, amount: string, safe_reference: ?string}>
     */
    private function resolveRefundAllocations(User $actor, RetailReturn $return, Sale $sale, string $total, ?int $paymentMethodId, ?int $originalPaymentId, array $allocations): array
    {
        if ($allocations === []) {
            if ($return->settlement_type === 'original_tender') {
                $allocations = $originalPaymentId !== null
                    ? [['original_payment_id' => $originalPaymentId, 'amount' => $total, 'safe_reference' => null]]
                    : $this->calculator->defaultAllocations($sale, $total);
            } else {
                $allocations = [['method_id' => $paymentMethodId, 'amount' => $total, 'safe_reference' => null]];
            }
        }
        if (count($allocations) > 12) {
            throw ValidationException::withMessages(['allocations' => __('A refund supports at most twelve payment allocations.')]);
        }

        $resolved = [];
        $allocated = '0.00';
        foreach (array_values($allocations) as $index => $input) {
            $amount = DecimalMoney::round((string) ($input['amount'] ?? ''), 2, __('A refund payment amount is invalid.'));
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['allocations.'.$index.'.amount' => __('Refund payment amounts must be greater than zero.')]);
            }
            $originalId = isset($input['original_payment_id']) && filled($input['original_payment_id']) ? (int) $input['original_payment_id'] : null;
            $original = $originalId === null ? null : $sale->payments()->whereKey($originalId)->lockForUpdate()->first();
            if ($return->settlement_type === 'original_tender' && $original === null) {
                throw ValidationException::withMessages(['allocations.'.$index => __('Every original-method refund allocation must reference a payment from this sale.')]);
            }
            $methodId = $original?->payment_method_id ?? (isset($input['method_id']) ? (int) $input['method_id'] : 0);
            $method = PaymentMethod::query()->whereKey($methodId)->where('status', 'active')->first();
            if ($method === null) {
                throw ValidationException::withMessages(['allocations.'.$index.'.method_id' => __('The selected refund payment method is no longer active.')]);
            }
            if ($return->settlement_type === 'cash_refund' && ! $method->isCash()) {
                throw ValidationException::withMessages(['allocations.'.$index.'.method_id' => __('A cash refund can use only an active configured cash method.')]);
            }
            if ($original !== null) {
                $already = (string) RetailReturnSettlement::query()->where('original_payment_id', $original->id)->where('direction', 'refund')->lockForUpdate()->sum('amount');
                $available = bcsub((string) $original->amount, $already, 2);
                if (bccomp($amount, $available, 2) > 0) {
                    throw ValidationException::withMessages(['allocations.'.$index.'.amount' => __('A refund allocation exceeds the remaining value of its original payment.')]);
                }
            }
            $reference = PaymentReferenceGuard::normalize(isset($input['safe_reference']) ? (string) $input['safe_reference'] : null);
            if ($method->requires_evidence && $reference === null) {
                throw ValidationException::withMessages(['allocations.'.$index.'.safe_reference' => __('A safe transaction reference is required for this payment method.')]);
            }
            $resolved[] = ['method' => $method, 'original_payment_id' => $original?->id, 'amount' => $amount, 'safe_reference' => $reference];
            $allocated = bcadd($allocated, $amount, 2);
        }
        if (bccomp($allocated, $total, 2) !== 0) {
            throw ValidationException::withMessages(['allocations' => __('Refund payment allocations must equal the final refundable amount exactly.')]);
        }

        return $resolved;
    }

    /** @return array<string, mixed> */
    private function settlementAttributes(RetailReturn $return, User $actor, int $position, string $amount, string $idempotencyKey, string $type, ?PaymentMethod $method = null, ?int $originalPaymentId = null, ?string $reference = null, ?int $giftCardId = null, string $direction = 'refund'): array
    {
        return [
            'retail_return_id' => $return->id,
            'payment_method_id' => $method?->id,
            'gift_card_id' => $giftCardId,
            'original_payment_id' => $originalPaymentId,
            'allocation_position' => $position,
            'method_code_snapshot' => $method?->code,
            'method_type_snapshot' => $method?->type,
            'safe_reference' => $reference,
            'direction' => $direction,
            'amount' => $amount,
            'settlement_type' => $type,
            'idempotency_key' => $idempotencyKey.':settlement:'.$position,
            'created_by' => $actor->id,
            'reason' => $return->reason,
        ];
    }

    /** @param array<int, array<string, mixed>> $requested */
    private function createExchange(RetailReturn $return, User $actor, array $requested, int $storeId): void
    {
        if ($requested === []) {
            throw ValidationException::withMessages(['exchange_lines' => __('An exchange must include replacement lines.')]);
        }
        $exchange = Exchange::query()->create(['retail_return_id' => $return->id, 'exchange_number' => $this->number('retail_exchange', 'EX-', (int) $return->branch_id), 'status' => 'draft', 'replacement_value' => '0.00', 'difference_value' => '0.00', 'difference_direction' => 'none']);
        $replacement = '0.00';
        foreach ($requested as $index => $input) {
            $product = Product::query()->sellable()->whereKey((int) ($input['product_id'] ?? 0))->first();
            $price = $product === null ? null : app(EffectivePriceResolver::class)->resolve((int) $product->id, $storeId);
            $quantity = (string) ($input['quantity'] ?? '0');
            if ($product === null || $price === null || ! preg_match('/^\d+$/', $quantity) || bccomp($quantity, '0', 0) <= 0) {
                throw ValidationException::withMessages(['exchange_lines.'.$index => __('The replacement product is not currently priced or the quantity is invalid.')]);
            }
            $value = bcmul($quantity, (string) $price->amount, 2);
            ExchangeLine::query()->create(['exchange_id' => $exchange->id, 'product_id' => $product->id, 'direction' => 'outbound', 'quantity' => $quantity, 'unit_value' => $price->amount, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en]);
            $replacement = bcadd($replacement, $value, 2);
        }
        $difference = bcsub($replacement, (string) $return->settlement_value, 2);
        $exchange->update(['replacement_value' => $replacement, 'difference_value' => $difference, 'difference_direction' => bccomp($difference, '0.00', 2) > 0 ? 'collect' : (bccomp($difference, '0.00', 2) < 0 ? 'refund' : 'none')]);
    }

    private function lockVisible(User $actor, RetailReturn $return): RetailReturn
    {
        $locked = RetailReturn::query()->visibleTo($actor)->whereKey($return->id)->lockForUpdate()->first();
        if ($locked === null) {
            throw ValidationException::withMessages(['refund' => __('The refund document is unavailable in your authorized scope.')]);
        }

        return $locked;
    }

    private function requiresDamagedStore(RetailReturnLine $line): bool
    {
        return $line->disposition === 'quarantine' || in_array($line->condition, ['non_sellable', 'damaged'], true);
    }

    private function authorize(User $actor, string $permission): void
    {
        abort_unless($actor->is_super_admin || $actor->can($permission), 403);
    }

    private function number(string $type, string $prefix, int $branchId): string
    {
        if (DocumentSequence::query()->where('document_type', $type)->exists()) {
            return app(AllocateDocumentNumber::class)->executeForBranch($type, $branchId);
        }

        return $prefix.strtoupper(Str::random(20));
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<int, array<string, mixed>> $lines @return array<string, mixed> */
    private function auditReturn(RetailReturn $return, array $lines): array
    {
        return [
            'return_number' => $return->return_number,
            'status' => $return->status,
            'source_sale_id' => $return->source_sale_id,
            'source_gift_receipt_id' => $return->source_gift_receipt_id,
            'line_count' => count($lines),
            'settlement_type' => $return->settlement_type,
            'settlement_value' => $return->settlement_value,
            'inspection' => array_map(static fn (array $line): array => [
                'sale_line_id' => $line['sale_line_id'],
                'return_store_id' => $line['return_store_id'],
                'condition' => $line['condition'],
                'disposition' => $line['disposition'],
                'inspection_notes' => $line['inspection_notes'],
            ], $lines),
        ];
    }
}
