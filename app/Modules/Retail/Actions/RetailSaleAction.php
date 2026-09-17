<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Services\ConvertProductQuantity;
use App\Modules\Customer\Actions\EarnLoyaltyAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\OpenPricePolicy;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SuspendedSale;
use App\Modules\Retail\Services\DiscountPolicy;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use App\Support\ProductQuantity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Atomic POS sale posting boundary for POS-03 through POS-06 and PRC-08. */
final class RetailSaleAction
{
    public function __construct(
        private readonly EffectivePriceResolver $prices,
        private readonly PosCalculationService $calculator,
        private readonly CapturePaymentAction $payments,
        private readonly DiscountPolicy $discounts,
        private readonly OpenPricePolicy $openPrices,
        private readonly ConvertProductQuantity $quantityConverter,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $requestedLines
     * @param  array<int, array{method: PaymentMethod, amount?: numeric-string, tendered?: numeric-string|null, evidence_reference?: string|null, evidence_attachment_id?: string|null, gift_card?: GiftCard|null}>  $tenders
     * @param  array{tax_applicable?: bool}  $financial
     */
    public function create(
        User $cashier,
        Store $store,
        array $requestedLines,
        string $idempotencyKey,
        bool $suspend = false,
        array $tenders = [],
        array $financial = [],
        ?Customer $customer = null,
    ): Sale {
        abort_unless($cashier->can('pos_sales.create'), 403);
        abort_unless(Store::query()->visibleTo($cashier)->whereKey($store->id)->exists(), 403);
        $store->loadMissing('company');
        if ($customer !== null) {
            abort_unless($customer->status === 'active', 404);
            abort_unless(Customer::query()->visibleFrom($cashier, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);
        }

        $lines = $this->resolveLines($cashier, $store, $requestedLines, $customer);
        $tax = $this->resolveTax($cashier, (bool) ($financial['tax_applicable'] ?? false));
        $tenders = $this->orderedTenders($tenders);
        $fingerprint = $this->fingerprint($store, $cashier, $lines, $tax, $tenders, $suspend, $customer);

        $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->with('lines', 'payments')->first();
        if ($existing !== null) {
            return $this->assertReplaySafe($existing, $store, $cashier, $fingerprint, $suspend, $customer);
        }

        try {
            $shift = $this->openShift($cashier, $store);

            return $this->createSale($cashier, $store, $shift, $lines, $idempotencyKey, $fingerprint, $tax, $suspend, $tenders, $customer);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'idempotency_key')) {
                throw $exception;
            }

            $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->with('lines', 'payments')->first();
            if ($existing === null) {
                throw new InvalidArgumentException(__('The checkout is already being processed. Retry with the same checkout token.'));
            }

            return $this->assertReplaySafe($existing, $store, $cashier, $fingerprint, $suspend, $customer);
        }
    }

    private function assertReplaySafe(Sale $existing, Store $store, User $cashier, string $fingerprint, bool $suspend, ?Customer $customer): Sale
    {
        $safe = (int) $existing->store_id === (int) $store->id
            && (int) $existing->cashier_id === (int) $cashier->id
            && (int) ($existing->customer_id ?? 0) === (int) ($customer?->id ?? 0)
            && ($existing->suspended_at !== null) === $suspend
            && hash_equals((string) $existing->request_fingerprint, $fingerprint);

        if (! $safe) {
            throw new InvalidArgumentException(__('This checkout token was already used with a different basket or financial payload.'));
        }

        return $existing;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function createSale(
        User $cashier,
        Store $store,
        PosShift $shift,
        array $lines,
        string $idempotencyKey,
        string $fingerprint,
        ?TaxSetting $tax,
        bool $suspend,
        array $tenders,
        ?Customer $customer,
    ): Sale {
        return DB::transaction(function () use ($cashier, $store, $shift, $lines, $idempotencyKey, $fingerprint, $tax, $suspend, $tenders, $customer): Sale {
            $shift = PosShift::query()->lockForUpdate()->findOrFail((int) $shift->getKey());
            /** @var ShiftState $shiftState */
            $shiftState = $shift->status;
            if (! $shiftState->acceptsActivity()) {
                throw new RuntimeException(__('This shift is closing and no longer accepts sales.'));
            }
            if (! DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)
                ->where('cashier_id', $cashier->id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists()) {
                throw new RuntimeException(__('The shift no longer has an active cashier and drawer assignment.'));
            }

            $this->assertPricesRemainCurrent($store, $lines);
            $this->assertOpenPriceApprovalsRemainCurrent($cashier, $store, $lines);
            $this->assertDiscountApprovalsRemainCurrent($cashier, $store, $lines);

            $totals = $this->calculator->calculate(
                array_map(static fn (array $line): array => [
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'],
                ], $lines),
                '0.00',
                $tax === null
                    ? ['applicable' => false]
                    : ['applicable' => true, 'rate' => (string) $tax->rate, 'inclusive' => (bool) $tax->is_tax_inclusive],
            );
            $this->assertMinimumPrices($cashier, $lines, $totals);

            $hasCash = ! $suspend && $this->hasCashTender($tenders);
            $cashRounding = $hasCash ? $this->calculator->cashRoundingAdjustment($totals['total']) : '0.00';
            $payable = bcadd($totals['total'], $cashRounding, 2);
            if (! $suspend) {
                $tenders = $this->validatedTenderAllocation($tenders, $payable, $customer !== null && in_array($customer->customer_type, ['credit', 'both'], true));
            }

            $currencyCode = trim((string) $store->company?->currency_code);
            if ($currencyCode === '' || strtoupper($currencyCode) === 'TBD') {
                throw new InvalidArgumentException(__('The company currency must be configured before a sale can be posted.'));
            }
            if (strtoupper($currencyCode) !== strtoupper((string) $shift->currency_code)) {
                throw new InvalidArgumentException(__('The active shift currency does not match the selling store currency.'));
            }

            $sale = Sale::query()->create([
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'cash_drawer_id' => $shift->cash_drawer_id,
                'shift_id' => $shift->id,
                'cashier_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'status' => $suspend ? 'suspended' : 'draft',
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'currency_code' => $currencyCode,
                'suspended_at' => $suspend ? now() : null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'tax_applicable' => $tax !== null,
                'tax_setting_id' => $tax?->id,
                'tax_rate_snapshot' => $tax?->rate,
                'tax_treatment_snapshot' => $tax?->treatment,
                'tax_inclusive_snapshot' => (bool) ($tax?->is_tax_inclusive ?? false),
                'total' => $totals['total'],
                'paid_total' => '0.00',
                'outstanding_amount' => $payable,
                'payment_status' => 'unpaid',
                'change_total' => '0.00',
                'cash_rounding_amount' => $cashRounding,
                'payable_total' => $payable,
            ]);

            foreach ($lines as $index => $line) {
                $computed = $totals['lines'][$index];
                $saleLine = SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'product_unit_id' => $line['product_unit_id'],
                    'entered_quantity' => $line['entered_quantity'],
                    'conversion_factor_snapshot' => $line['conversion_factor_snapshot'],
                    'entered_unit_price' => $line['entered_unit_price'],
                    'price_list_id' => $line['price_list_id'],
                    'customer_product_price_id' => $line['customer_product_price_id'],
                    'price_source' => $line['price_source'],
                    'minimum_price_snapshot' => $line['minimum_price'],
                    'line_number' => $index + 1,
                    'item_code' => $line['product']->item_code,
                    'name_ar' => $line['product']->name_ar,
                    'name_en' => $line['product']->name_en,
                    'variant_snapshot' => $line['product']->variantSnapshot(),
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'reference_price' => $line['reference_price'],
                    'is_open_price' => $line['is_open_price'],
                    'open_price_authorized_by' => $line['is_open_price'] ? ($line['open_price_approver_id'] ?? $cashier->id) : null,
                    'open_price_approval_record_id' => $line['open_price_approval_id'],
                    'open_price_minimum_snapshot' => $line['open_price_minimum'],
                    'open_price_maximum_snapshot' => $line['open_price_maximum'],
                    'open_price_reason' => $line['open_price_reason'],
                    'gross_amount' => $computed['gross_amount'],
                    'discount_amount' => $computed['discount_amount'],
                    'discount_type' => $line['discount_type'],
                    'discount_reason' => $line['discount_reason'],
                    'discount_applied_by' => $line['discount_type'] ? $cashier->id : null,
                    'discount_replaced_by' => $line['discount_replaced'] ? $cashier->id : null,
                    'discount_replaced_at' => $line['discount_replaced'] ? now() : null,
                    'discount_approval_record_id' => $line['discount_approval_id'],
                    'allocated_invoice_discount' => '0.00',
                    'net_amount' => $computed['net_amount'],
                ]);

                if ($line['discount_replaced']) {
                    app(RecordAuditEvent::class)->execute(
                        category: 'retail',
                        event: 'sale_discount_replaced',
                        source: $saleLine,
                        before: ['type' => $line['discount_previous_type'], 'amount' => $line['discount_previous_amount']],
                        after: ['type' => $line['discount_type'], 'amount' => $line['discount_amount']],
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        reasonText: $line['discount_reason'],
                        metadata: ['actor_id' => $cashier->id, 'sale_id' => $sale->id],
                    );
                }
                if ($line['discount_approval_id'] !== null) {
                    app(RecordAuditEvent::class)->execute(
                        category: 'retail',
                        event: 'sale_discount_approved',
                        source: $saleLine,
                        after: ['amount' => $line['discount_amount'], 'type' => $line['discount_type'], 'approval_record_id' => $line['discount_approval_id'], 'approver_id' => $line['discount_approver_id']],
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        reasonText: $line['discount_reason'],
                        metadata: ['actor_id' => $cashier->id, 'sale_id' => $sale->id],
                    );
                }
                if ($line['is_open_price']) {
                    app(RecordAuditEvent::class)->execute(
                        category: 'pricing',
                        event: 'sale_open_price_applied',
                        source: $saleLine,
                        before: ['reference_price' => $line['reference_price']],
                        after: ['selling_price' => $line['unit_price'], 'minimum' => $line['open_price_minimum'], 'maximum' => $line['open_price_maximum'], 'approval_record_id' => $line['open_price_approval_id'], 'approver_id' => $line['open_price_approver_id']],
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        reasonText: $line['open_price_reason'],
                        metadata: ['actor_id' => $cashier->id, 'sale_id' => $sale->id, 'approval_limit_percent' => $line['open_price_approval_limit']],
                    );
                }
                if ($line['below_minimum']) {
                    app(RecordAuditEvent::class)->execute(
                        category: 'pricing', event: 'sale_below_minimum_authorized', source: $saleLine,
                        before: ['minimum_price' => $line['minimum_price'], 'resolved_price' => $line['resolved_entered_price']],
                        after: ['net_unit_price' => $line['net_entered_unit_price'], 'entered_unit_price' => $line['entered_unit_price']],
                        branchId: (int) $store->branch_id, storeId: (int) $store->id, reasonText: $line['minimum_override_reason'],
                        metadata: ['actor_id' => $cashier->id, 'sale_id' => $sale->id, 'customer_id' => $customer?->id, 'product_id' => $line['product']->id, 'product_unit_id' => $line['product_unit_id']],
                    );
                }
            }

            if ($suspend) {
                SuspendedSale::query()->create([
                    'sale_id' => $sale->id,
                    'resume_code' => 'S-'.strtoupper(Str::random(10)),
                    'created_by' => $cashier->id,
                    'status' => 'suspended',
                ]);

                return $sale->fresh('lines', 'suspendedSale');
            }

            $this->captureTenders($cashier, $sale, $tenders);

            return $this->finalize($sale, $cashier);
        });
    }

    /**
     * Revalidate a suspended sale for the resume payment screen without
     * mutating it. Finalization repeats these checks under row locks.
     *
     * @return array{sale: Sale, shift: PosShift, lines: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function suspendedResumePreview(User $cashier, Sale $sale): array
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        $sale = Sale::query()->with(['lines.product', 'suspendedSale', 'store.company'])->findOrFail($sale->id);
        $context = $this->revalidateSuspendedContext($cashier, $sale, false);

        return [
            'sale' => $sale,
            'shift' => $context['shift'],
            'lines' => $context['lines'],
            'totals' => $context['totals'],
        ];
    }

    /** @param array<int, array<string, mixed>> $tenders */
    public function finalizeSuspended(User $cashier, Sale $sale, array $tenders = []): Sale
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        $tenders = $this->orderedTenders($tenders);

        return DB::transaction(function () use ($sale, $cashier, $tenders): Sale {
            $sale = Sale::query()->with(['lines.product', 'suspendedSale', 'store.company'])->lockForUpdate()->findOrFail($sale->id);
            $context = $this->revalidateSuspendedContext($cashier, $sale, true);
            $lines = $context['lines'];
            $totals = $context['totals'];
            $tax = $context['tax'];
            $shift = $context['shift'];

            $rounding = $this->hasCashTender($tenders) ? $this->calculator->cashRoundingAdjustment($totals['total']) : '0.00';
            $sale->update([
                'branch_id' => $shift->branch_id,
                'store_id' => $shift->store_id,
                'cash_drawer_id' => $shift->cash_drawer_id,
                'shift_id' => $shift->id,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'tax_applicable' => $tax !== null,
                'tax_setting_id' => $tax?->id,
                'tax_rate_snapshot' => $tax?->rate,
                'tax_treatment_snapshot' => $tax?->treatment,
                'tax_inclusive_snapshot' => (bool) ($tax?->is_tax_inclusive ?? false),
                'total' => $totals['total'],
                'cash_rounding_amount' => $rounding,
                'payable_total' => bcadd($totals['total'], $rounding, 2),
            ]);
            $sale->loadMissing('customer');
            $tenders = $this->validatedTenderAllocation($tenders, (string) $sale->payable_total, $sale->customer !== null && in_array($sale->customer->customer_type, ['credit', 'both'], true));

            foreach ($sale->lines->values() as $index => $saleLine) {
                $line = $lines[$index];
                $computed = $totals['lines'][$index];
                $saleLine->update([
                    'item_code' => $line['product']->item_code,
                    'name_ar' => $line['product']->name_ar,
                    'name_en' => $line['product']->name_en,
                    'variant_snapshot' => $line['product']->variantSnapshot(),
                    'product_unit_id' => $line['product_unit_id'],
                    'entered_quantity' => $line['entered_quantity'],
                    'conversion_factor_snapshot' => $line['conversion_factor_snapshot'],
                    'entered_unit_price' => $line['entered_unit_price'],
                    'price_list_id' => $line['price_list_id'],
                    'customer_product_price_id' => $line['customer_product_price_id'],
                    'price_source' => $line['price_source'],
                    'minimum_price_snapshot' => $line['minimum_price'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'reference_price' => $line['reference_price'],
                    'is_open_price' => $line['is_open_price'],
                    'open_price_authorized_by' => $line['is_open_price'] ? ($line['open_price_approver_id'] ?? $cashier->id) : null,
                    'open_price_approval_record_id' => $line['open_price_approval_id'],
                    'open_price_minimum_snapshot' => $line['open_price_minimum'],
                    'open_price_maximum_snapshot' => $line['open_price_maximum'],
                    'open_price_reason' => $line['open_price_reason'],
                    'gross_amount' => $computed['gross_amount'],
                    'discount_amount' => $computed['discount_amount'],
                    'discount_type' => $line['discount_type'],
                    'discount_reason' => $line['discount_reason'],
                    'discount_applied_by' => $line['discount_type'] ? $cashier->id : null,
                    'discount_approval_record_id' => $line['discount_approval_id'],
                    'allocated_invoice_discount' => '0.00',
                    'net_amount' => $computed['net_amount'],
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'suspended_sale_revalidated',
                source: $sale,
                after: $sale->only(['shift_id', 'cash_drawer_id', 'subtotal', 'discount_total', 'tax_total', 'total', 'payable_total']),
                branchId: (int) $sale->branch_id,
                storeId: (int) $sale->store_id,
                metadata: ['actor_id' => $cashier->id, 'price_revalidated' => true, 'stock_revalidated_at_finalization' => true],
            );

            $this->captureTenders($cashier, $sale, $tenders);

            return $this->finalize($sale, $cashier);
        });
    }

    /** @param array<int, array<string, mixed>> $tenders */
    private function captureTenders(User $cashier, Sale $sale, array $tenders): void
    {
        foreach ($tenders as $index => $tender) {
            $this->payments->execute(
                $cashier,
                $sale,
                $tender['method'],
                (string) ($tender['amount'] ?? '0.00'),
                'SALE:'.$sale->id.':TENDER:'.$index.':'.$tender['method']->code,
                isset($tender['tendered']) ? (string) $tender['tendered'] : null,
                isset($tender['evidence_reference']) ? (string) $tender['evidence_reference'] : null,
                isset($tender['evidence_attachment_id']) ? (string) $tender['evidence_attachment_id'] : null,
                $tender['gift_card'] ?? null,
            );
        }
    }

    /** @param array<int, array<string, mixed>> $requestedLines @return array<int, array<string, mixed>> */
    private function resolveLines(User $cashier, Store $store, array $requestedLines, ?Customer $customer = null): array
    {
        if ($requestedLines === []) {
            throw new InvalidArgumentException(__('Add at least one product to the cart.'));
        }

        $resolved = [];
        foreach ($requestedLines as $requested) {
            $product = isset($requested['product_id'])
                ? Product::query()->sellable()->find((int) $requested['product_id'])
                : Barcode::query()->active()->where('barcode', trim((string) ($requested['barcode'] ?? '')))->first()?->product;
            if (! $product instanceof Product || ! $product->isSellable()) {
                throw new InvalidArgumentException(__('Product was not found or is inactive.'));
            }
            $product->loadMissing('baseProductUnit.unit');
            $productUnit = filled($requested['product_unit_id'] ?? null)
                ? $product->productUnits()->with('unit')->find((int) $requested['product_unit_id'])
                : $product->baseProductUnit;
            if (filled($requested['product_unit_id'] ?? null) && ! $productUnit instanceof ProductUnit) {
                throw new InvalidArgumentException(__('The selected unit does not belong to the product.'));
            }
            if ($productUnit?->unit?->status !== null && $productUnit->unit->status !== 'active') {
                throw new InvalidArgumentException(__('The selected product unit is inactive.'));
            }
            $decimalPlaces = $product->fractional_quantity ? min(6, (int) ($productUnit?->unit?->decimal_places ?? 6)) : 0;
            $enteredQuantity = bcadd((string) ProductQuantity::normalize($requested['quantity'] ?? null, decimalPlaces: $decimalPlaces), '0', 6);
            $factor = $productUnit instanceof ProductUnit ? (string) $productUnit->conversion_factor : '1.000000';
            $quantity = $productUnit instanceof ProductUnit
                ? $this->quantityConverter->execute($product, $productUnit, $enteredQuantity)
                : $enteredQuantity;

            $price = $this->prices->resolve($product->id, $store->id, productUnitId: $productUnit?->id, customerId: $customer?->id);
            if (! $price instanceof PriceLine) {
                throw new InvalidArgumentException(__('Product has no positive selling price for this store.'));
            }

            $enteredReferencePrice = DecimalMoney::normalize((string) ($price->reference_amount ?? $price->amount), 4);
            $enteredUnitPrice = DecimalMoney::normalize((string) $price->amount, 4);
            $unitPrice = bcdiv($enteredUnitPrice, $factor, 4);
            $referencePrice = bcdiv($enteredReferencePrice, $factor, 4);
            $openPriceReason = null;
            $isOpenPrice = filled($requested['open_price_amount'] ?? null);
            $openPriceApprovalId = filled($requested['open_price_approval_id'] ?? null)
                ? (int) $requested['open_price_approval_id']
                : null;
            $openPriceApprovalRequired = false;
            $openPriceApproverId = null;
            if ($isOpenPrice) {
                abort_unless($cashier->can('pos_sales.open_price'), 403);
                if (! $price->open_price_allowed) {
                    throw new InvalidArgumentException(__('Open price is not enabled for this product price.'));
                }
                $enteredUnitPrice = DecimalMoney::normalize((string) $requested['open_price_amount'], 4);
                $unitPrice = bcdiv($enteredUnitPrice, $factor, 4);
                $openPriceReason = trim((string) ($requested['open_price_reason'] ?? ''));
                $this->openPrices->validateOrThrow(
                    referenceAmount: $enteredReferencePrice,
                    requestedAmount: $enteredUnitPrice,
                    minimum: $price->minimum_selling_price ?? $price->open_price_minimum,
                    maximum: $price->open_price_maximum === null ? null : (string) $price->open_price_maximum,
                    hasPermission: true,
                    reason: $openPriceReason,
                    allowBelowMinimum: $cashier->can('pos_sales.override_below_minimum'),
                );
                $approvalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT);
                $openPriceApprovalRequired = $this->openPrices->requiresApproval($enteredReferencePrice, $enteredUnitPrice, $approvalLimit);
                if ($openPriceApprovalRequired && $openPriceApprovalId === null) {
                    throw new InvalidArgumentException(__('Independent manager approval is required for this open-price deviation.'));
                }
                if ($openPriceApprovalId !== null) {
                    $openPriceApproverId = $this->assertOpenPriceApproval(
                        $cashier,
                        $store,
                        $product->id,
                        $price,
                        $enteredReferencePrice,
                        $enteredUnitPrice,
                        $openPriceReason,
                        $approvalLimit,
                        $openPriceApprovalId,
                        false,
                    );
                }
            }

            $discountAmount = filled($requested['discount_amount'] ?? null)
                ? DecimalMoney::round((string) $requested['discount_amount'])
                : '0.00';
            $discountType = bccomp($discountAmount, '0', 2) > 0 ? (string) ($requested['discount_type'] ?? '') : null;
            $discountReason = filled($requested['discount_reason'] ?? null) ? trim((string) $requested['discount_reason']) : null;
            $discountReplaces = filled($requested['discount_replaces'] ?? null) ? (string) $requested['discount_replaces'] : null;
            $discountApprovalId = filled($requested['discount_approval_id'] ?? null)
                ? (int) $requested['discount_approval_id']
                : null;
            $discountReplaced = false;
            $discountApprovalRequired = false;
            $discountApproverId = null;
            if ($discountType !== null) {
                abort_unless($cashier->can('pos_sales.apply_discount'), 403);
                $gross = DecimalMoney::round(bcmul($quantity, $unitPrice, 8));
                $discountApprovalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT);
                $discountApprovalRequired = $this->discounts->requiresApproval($discountAmount, $gross);
                if ($discountApprovalRequired && $discountApprovalId === null) {
                    throw new InvalidArgumentException(__('Independent manager approval is required before checkout.'));
                }
                if ($discountApprovalId !== null) {
                    $discountApproverId = $this->assertDiscountApproval(
                        $cashier,
                        $store,
                        (int) $product->id,
                        $price,
                        $gross,
                        $discountAmount,
                        $discountType,
                        $discountReason,
                        $discountReplaces,
                        (string) ($requested['discount_previous_amount'] ?? '0.00'),
                        $discountApprovalLimit,
                        $discountApprovalId,
                        false,
                    );
                }
                $payload = $this->discounts->buildLineDiscount(
                    actor: $cashier,
                    discountAmount: $discountAmount,
                    baseAmount: $gross,
                    newType: $discountType,
                    existingType: $discountReplaces,
                    reason: $discountReason,
                    approved: $discountApprovalRequired || $discountApprovalId !== null,
                );
                $discountAmount = DecimalMoney::round($payload['discount_amount']);
                $discountType = $payload['discount_type'];
                $discountReplaced = $payload['discount_replaced_by'] !== null;
            }

            $resolved[] = [
                'product' => $product,
                'product_unit_id' => $productUnit?->id,
                'entered_quantity' => $enteredQuantity,
                'conversion_factor_snapshot' => bcadd($factor, '0', 6),
                'entered_unit_price' => $enteredUnitPrice,
                'price_identity' => $this->prices->identity($price),
                'price_list_id' => $price->price_list_id,
                'customer_product_price_id' => $price->customer_product_price_id,
                'price_source' => $price->price_source,
                'customer_id' => $customer?->id,
                'minimum_price' => $price->minimum_selling_price === null ? null : DecimalMoney::normalize((string) $price->minimum_selling_price, 4),
                'resolved_entered_price' => DecimalMoney::normalize((string) $price->amount, 4),
                'below_minimum' => false,
                'minimum_override_reason' => null,
                'quantity' => DecimalMoney::normalize($quantity, 6),
                'unit_price' => $unitPrice,
                'reference_price' => $referencePrice,
                'entered_reference_price' => $enteredReferencePrice,
                'is_open_price' => $isOpenPrice,
                'open_price_minimum' => $isOpenPrice && $price->open_price_minimum !== null ? (string) $price->open_price_minimum : null,
                'open_price_maximum' => $isOpenPrice && $price->open_price_maximum !== null ? (string) $price->open_price_maximum : null,
                'open_price_reason' => $openPriceReason,
                'open_price_approval_id' => $openPriceApprovalId,
                'open_price_approval_required' => $openPriceApprovalRequired,
                'open_price_approval_limit' => $isOpenPrice ? PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT) : null,
                'open_price_approver_id' => $openPriceApproverId,
                'discount_amount' => $discountAmount,
                'discount_type' => $discountType,
                'discount_reason' => $discountReason,
                'discount_replaced' => $discountReplaced,
                'discount_previous_type' => $discountReplaced ? $discountReplaces : null,
                'discount_previous_amount' => $discountReplaced ? (string) ($requested['discount_previous_amount'] ?? '0.00') : null,
                'discount_approval_id' => $discountApprovalId,
                'discount_approval_required' => $discountApprovalRequired,
                'discount_approval_limit' => $discountType !== null ? PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT) : null,
                'discount_approver_id' => $discountApproverId,
            ];
        }

        return $resolved;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertPricesRemainCurrent(Store $store, array $lines): void
    {
        foreach ($lines as $line) {
            $current = $this->prices->resolve($line['product']->id, $store->id, productUnitId: $line['product_unit_id'], customerId: $line['customer_id'] ?? null);
            if ($current === null) {
                throw new InvalidArgumentException(__('A basket price changed before checkout. Review the basket and try again.'));
            }
            $this->prices->lockSource($current);
            $current = $this->prices->resolve($line['product']->id, $store->id, productUnitId: $line['product_unit_id'], customerId: $line['customer_id'] ?? null);
            if ($current === null || ! hash_equals($line['price_identity'], $this->prices->identity($current))) {
                throw new InvalidArgumentException(__('A basket price changed before checkout. Review the basket and try again.'));
            }
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertOpenPriceApprovalsRemainCurrent(User $cashier, Store $store, array $lines): void
    {
        foreach ($lines as $line) {
            if (! $line['is_open_price']) {
                continue;
            }

            $price = $this->prices->resolve($line['product']->id, $store->id, productUnitId: $line['product_unit_id'], customerId: $line['customer_id']);
            if (! $price instanceof PriceLine) {
                throw new InvalidArgumentException(__('The approved price is no longer available for this open-price line.'));
            }

            $approvalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT);
            $required = $this->openPrices->requiresApproval((string) $line['entered_reference_price'], (string) $line['entered_unit_price'], $approvalLimit);
            if ($required && $line['open_price_approval_id'] === null) {
                throw new InvalidArgumentException(__('Independent manager approval is required before checkout.'));
            }

            $this->assertOpenPriceApproval(
                $cashier,
                $store,
                (int) $line['product']->id,
                $price,
                (string) $line['entered_reference_price'],
                (string) $line['entered_unit_price'],
                (string) $line['open_price_reason'],
                $approvalLimit,
                $line['open_price_approval_id'],
                true,
            );
        }
    }

    private function assertOpenPriceApproval(
        User $cashier,
        Store $store,
        int $productId,
        PriceLine $price,
        string $reference,
        string $requested,
        string $reason,
        ?string $approvalLimit,
        ?int $approvalId,
        bool $lock,
    ): ?int {
        if ($approvalId === null) {
            return null;
        }

        $query = ApprovalRecord::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        $approval = $query->findOrFail($approvalId);

        if ($approval->source_type !== 'pos_open_price' || $approval->requested_action !== 'approve_open_price') {
            throw new InvalidArgumentException(__('The approval record is not a POS open-price approval.'));
        }
        if ($approval->approval_state !== ApprovalState::Approved) {
            throw new InvalidArgumentException(__('The open-price approval is not approved. Checkout remains blocked.'));
        }
        if ((int) $approval->requester_id !== (int) $cashier->id) {
            throw new InvalidArgumentException(__('The open-price approval belongs to a different cashier session.'));
        }
        if ($approval->approver_id === null || (int) $approval->approver_id === (int) $cashier->id) {
            throw new InvalidArgumentException(__('An independent manager must approve the open price.'));
        }
        if ((int) $approval->branch_id !== (int) $store->branch_id || (int) $approval->store_id !== (int) $store->id) {
            throw new InvalidArgumentException(__('The open-price approval scope does not match the selling store.'));
        }
        if ($approval->expires_at !== null && $approval->expires_at->isPast()) {
            throw new InvalidArgumentException(__('The open-price approval has expired. Request a fresh decision.'));
        }

        $sourceHash = $this->openPrices->fingerprint([
            'product_id' => $productId,
            'store_id' => (int) $store->id,
            'price_identity' => $this->prices->identity($price),
            'reference' => $reference,
            'minimum' => $price->minimum_selling_price ?? $price->open_price_minimum,
            'maximum' => $price->open_price_maximum,
            'requested_amount' => DecimalMoney::normalize($requested, 4),
            'reason' => trim($reason),
            'approval_limit' => $approvalLimit,
        ]);

        if ($approval->source_hash === null || ! hash_equals((string) $approval->source_hash, $sourceHash)) {
            throw new InvalidArgumentException(__('The open-price approval is stale. Request approval again for the current price policy.'));
        }

        return (int) $approval->approver_id;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertDiscountApprovalsRemainCurrent(User $cashier, Store $store, array &$lines): void
    {
        foreach ($lines as &$line) {
            if ($line['discount_type'] === null || bccomp((string) $line['discount_amount'], '0', 2) <= 0) {
                continue;
            }

            $gross = DecimalMoney::round(bcmul((string) $line['quantity'], (string) $line['unit_price'], 8));
            $approvalLimit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT);
            $required = $this->discounts->requiresApproval((string) $line['discount_amount'], $gross);
            if ($required && $line['discount_approval_id'] === null) {
                throw new InvalidArgumentException(__('Independent manager approval is required before checkout.'));
            }

            if ($line['discount_approval_id'] !== null) {
                $price = $this->prices->resolve($line['product']->id, $store->id, productUnitId: $line['product_unit_id'], customerId: $line['customer_id']);
                if (! $price instanceof PriceLine) {
                    throw new InvalidArgumentException(__('The approved price is no longer available for this discount line.'));
                }
                $this->prices->lockSource($price);
                $line['discount_approver_id'] = $this->assertDiscountApproval(
                    $cashier,
                    $store,
                    (int) $line['product']->id,
                    $price,
                    $gross,
                    (string) $line['discount_amount'],
                    (string) $line['discount_type'],
                    $line['discount_reason'],
                    $line['discount_previous_type'],
                    (string) ($line['discount_previous_amount'] ?? '0.00'),
                    $approvalLimit,
                    (int) $line['discount_approval_id'],
                    true,
                );
            }
        }
        unset($line);
    }

    private function assertDiscountApproval(
        User $cashier,
        Store $store,
        int $productId,
        PriceLine $price,
        string $gross,
        string $discountAmount,
        string $discountType,
        ?string $reason,
        ?string $existingType,
        string $existingAmount,
        ?string $approvalLimit,
        ?int $approvalId,
        bool $lock,
    ): ?int {
        if ($approvalId === null) {
            return null;
        }

        $query = ApprovalRecord::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        $approval = $query->findOrFail($approvalId);

        if ($approval->source_type !== 'pos_discount' || $approval->requested_action !== 'approve_discount') {
            throw new InvalidArgumentException(__('The approval record is not a POS discount approval.'));
        }
        if ($approval->approval_state !== ApprovalState::Approved) {
            throw new InvalidArgumentException(__('The discount approval is not approved. Checkout remains blocked.'));
        }
        if ((int) $approval->requester_id !== (int) $cashier->id) {
            throw new InvalidArgumentException(__('The discount approval belongs to a different cashier session.'));
        }
        if ($approval->approver_id === null || (int) $approval->approver_id === (int) $cashier->id) {
            throw new InvalidArgumentException(__('An independent manager must approve the discount.'));
        }
        if ((int) $approval->branch_id !== (int) $store->branch_id || (int) $approval->store_id !== (int) $store->id) {
            throw new InvalidArgumentException(__('The discount approval scope does not match the selling store.'));
        }
        if ($approval->expires_at !== null && $approval->expires_at->isPast()) {
            throw new InvalidArgumentException(__('The discount approval has expired. Request a fresh decision.'));
        }

        $sourceVersion = $this->prices->identity($price);
        if ($approval->source_version !== $sourceVersion) {
            throw new InvalidArgumentException(__('The discount approval is stale. Request approval again for the current price policy.'));
        }

        $sourceHash = $this->openPrices->fingerprint([
            'product_id' => $productId,
            'store_id' => (int) $store->id,
            'price_identity' => $this->prices->identity($price),
            'gross' => $gross,
            'discount_amount' => DecimalMoney::round($discountAmount),
            'discount_type' => $discountType,
            'reason' => trim((string) $reason),
            'existing_type' => $existingType,
            'existing_amount' => DecimalMoney::round($existingAmount),
            'approval_limit' => $approvalLimit,
        ]);

        if ($approval->source_hash === null || ! hash_equals((string) $approval->source_hash, $sourceHash)) {
            throw new InvalidArgumentException(__('The discount approval is stale. Request approval again for the current cart.'));
        }

        return (int) $approval->approver_id;
    }

    /** @param array<int, array<string, mixed>> $lines @param array<string, mixed> $totals */
    private function assertMinimumPrices(User $cashier, array &$lines, array $totals): void
    {
        foreach ($lines as $index => &$line) {
            if ($line['minimum_price'] === null) {
                continue;
            }
            $netUnit = bcdiv((string) $totals['lines'][$index]['net_amount'], (string) $line['entered_quantity'], 4);
            $line['net_entered_unit_price'] = $netUnit;
            if (bccomp($netUnit, (string) $line['minimum_price'], 4) >= 0) {
                continue;
            }

            $reason = trim((string) ($line['open_price_reason'] ?: $line['discount_reason']));
            if (! $cashier->can('pos_sales.override_below_minimum') || $reason === '') {
                throw new InvalidArgumentException(__('The net selling price is below the minimum allowed for this unit. An authorized override and reason are required.'));
            }
            $line['below_minimum'] = true;
            $line['minimum_override_reason'] = $reason;
        }
        unset($line);
    }

    private function resolveTax(User $cashier, bool $applicable): ?TaxSetting
    {
        if (! $applicable) {
            return null;
        }
        abort_unless($cashier->can('pos_sales.apply_tax'), 403);

        $setting = TaxSetting::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->first();

        if ($setting === null || $setting->rate === null) {
            throw new InvalidArgumentException(__('Tax was enabled, but one effective company default tax rule with an explicit rate is required.'));
        }

        return $setting;
    }

    /** @param array<int, array<string, mixed>> $tenders @return array<int, array<string, mixed>> */
    private function orderedTenders(array $tenders): array
    {
        if ($tenders === []) {
            return [];
        }

        $cashCount = 0;
        $methodIds = [];
        foreach ($tenders as &$tender) {
            if (! ($tender['method'] ?? null) instanceof PaymentMethod) {
                throw new InvalidArgumentException(__('Every tender requires a configured payment method.'));
            }
            $tender['method'] = PaymentMethod::query()->findOrFail($tender['method']->id);
            $methodId = (int) $tender['method']->id;
            if (isset($methodIds[$methodId])) {
                throw new InvalidArgumentException(__('Each payment method may only be used once per checkout.'));
            }
            $methodIds[$methodId] = true;
            $isGiftCard = (string) $tender['method']->type === 'gift_card';
            if ($isGiftCard !== (($tender['gift_card'] ?? null) instanceof GiftCard)) {
                throw new InvalidArgumentException($isGiftCard
                    ? __('A Gift Card tender requires a valid Gift Card.')
                    : __('A Gift Card may only be used with a Gift Card payment method.'));
            }
            if ($isGiftCard) {
                $tender['gift_card'] = GiftCard::query()->findOrFail($tender['gift_card']->id);
            }
            if ($this->isCashMethod($tender['method'])) {
                $cashCount++;
            }
        }
        unset($tender);

        if ($cashCount > 1) {
            throw new InvalidArgumentException(__('Only one cash tender may settle the residual.'));
        }

        usort($tenders, fn (array $left, array $right): int => (int) $this->isCashMethod($left['method']) <=> (int) $this->isCashMethod($right['method']));

        return array_values($tenders);
    }

    /**
     * Normalize and validate the complete tender allocation before any payment
     * is captured. Cash settles only the residual left by explicit non-cash
     * portions; excess cash is change. Eligible credit customers may leave an
     * outstanding balance, while cash customers still fail closed on underpayment.
     *
     * @param  array<int, array<string, mixed>>  $tenders
     * @return array<int, array<string, mixed>>
     */
    private function validatedTenderAllocation(array $tenders, string $payable, bool $allowOutstanding = false): array
    {
        $payable = DecimalMoney::round($payable);
        $nonCashTotal = '0.00';
        $cashIndex = null;

        foreach ($tenders as $index => &$tender) {
            if ($this->isCashMethod($tender['method'])) {
                $cashIndex = $index;

                continue;
            }
            $amount = DecimalMoney::round((string) ($tender['amount'] ?? ''), 2, __('A payment amount must be a valid number.'));
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw new InvalidArgumentException(__('A payment amount must be greater than zero.'));
            }
            $tender['amount'] = $amount;
            $nonCashTotal = bcadd($nonCashTotal, $amount, 2);
            if (bccomp($nonCashTotal, $payable, 2) > 0) {
                throw new InvalidArgumentException(__('Payment portions cannot exceed the order total.'));
            }
        }
        unset($tender);

        $residual = bcsub($payable, $nonCashTotal, 2);
        if ($cashIndex === null) {
            if (! $allowOutstanding && bccomp($residual, '0.00', 2) !== 0) {
                throw new InvalidArgumentException(__('Payment portions must settle the order total exactly.'));
            }

            return $tenders;
        }
        if (bccomp($residual, '0.00', 2) <= 0) {
            throw new InvalidArgumentException(__('Cash cannot be added after other payment portions have fully allocated the order total.'));
        }
        $tendered = DecimalMoney::round((string) ($tenders[$cashIndex]['tendered'] ?? ''), 2, __('The cash tendered must be a valid amount.'));
        if (bccomp($tendered, '0.00', 2) <= 0) {
            throw new InvalidArgumentException(__('The cash received must be greater than zero.'));
        }
        if (bccomp($tendered, $residual, 2) < 0) {
            if (! $allowOutstanding) {
                throw new InvalidArgumentException(__('The tendered cash cannot be less than the remaining amount.'));
            }
            $tenders[$cashIndex]['amount'] = $tendered;
            $tenders[$cashIndex]['tendered'] = $tendered;

            return $tenders;
        }
        $tenders[$cashIndex]['amount'] = $residual;
        $tenders[$cashIndex]['tendered'] = $tendered;

        return $tenders;
    }

    private function openShift(User $cashier, Store $store): PosShift
    {
        $shift = PosShift::query()->open()->where('store_id', $store->id)->where('cashier_id', $cashier->id)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('active_pos_shift_assignments')
                ->whereColumn('active_pos_shift_assignments.shift_id', 'pos_shifts.id'))
            ->with('cashDrawer')->orderByDesc('id')->first();
        if ($shift === null) {
            throw new RuntimeException(__('An active POS shift is required before starting a sale.'));
        }

        return $shift;
    }

    private function finalize(Sale $sale, User $cashier): Sale
    {
        $sale->loadMissing('lines', 'store', 'payments', 'customer');
        [$paid, $outstanding, $paymentStatus] = $this->settlement($sale);

        foreach ($sale->lines as $line) {
            $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $sale->store_id)->lockForUpdate()->first();
            if ($balance === null || bccomp((string) $balance->on_hand, (string) $line->quantity, 6) < 0) {
                throw new InvalidArgumentException(__('Insufficient stock for one or more sale lines.'));
            }
        }

        $number = app(AllocateDocumentNumber::class)->executeForBranch('retail_sale', (int) $sale->store->branch_id);
        $poster = app(PostInventoryMovement::class);
        foreach ($sale->lines as $line) {
            $movement = $poster->execute(
                (int) $line->product_id,
                (int) $sale->store_id,
                '-'.(string) $line->quantity,
                'sale',
                null,
                'SALE:'.$sale->id.':LINE:'.$line->id,
                Sale::class,
                $sale->id,
                $line->id,
            );
            $line->update(['stock_movement_id' => $movement->id, 'consumed_cost' => $movement->consumed_cost]);
        }

        $change = $this->payments->changeSoFar($sale);
        $before = $sale->only(['status', 'document_number', 'lock_version']);
        $sale->update([
            'status' => 'approved',
            'document_number' => $number,
            'approved_at' => now(),
            'paid_total' => $paid,
            'outstanding_amount' => $outstanding,
            'payment_status' => $paymentStatus,
            'change_total' => $change,
            'lock_version' => ((int) $sale->lock_version) + 1,
        ]);

        // Customer loyalty is part of the same approval transaction. If a
        // configured rule is missing or the ledger cannot be posted, the sale
        // and every stock/payment effect roll back together.
        app(EarnLoyaltyAction::class)->executeForSale($cashier, $sale->fresh(['customer', 'store']));

        if ($sale->suspendedSale !== null) {
            $sale->suspendedSale->update(['status' => 'resumed', 'resumed_at' => now()]);
        }

        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: 'finalize_sale',
            source: $sale,
            before: $before,
            after: $sale->only(['status', 'document_number', 'subtotal', 'discount_total', 'tax_total', 'total', 'cash_rounding_amount', 'payable_total', 'paid_total', 'change_total', 'lock_version']),
            branchId: (int) $sale->branch_id,
            storeId: (int) $sale->store_id,
            metadata: [
                'line_count' => $sale->lines->count(),
                'cashier_id' => $cashier->id,
                'stock_posted' => true,
                'payment_count' => $sale->payments()->count(),
                'payment_total' => $paid,
                'customer_id' => $sale->customer_id,
                'tax_setting_snapshot' => $sale->tax_setting_id,
                'open_price_line_ids' => $sale->lines->where('is_open_price', true)->pluck('id')->all(),
                'discount_line_ids' => $sale->lines->where('discount_amount', '>', 0)->pluck('id')->all(),
            ],
        );

        return $sale->fresh(['lines', 'store', 'cashier', 'payments.evidenceAttachment']);
    }

    /** @return array{0:numeric-string,1:numeric-string,2:string} */
    private function settlement(Sale $sale): array
    {
        $paid = $this->payments->paidSoFar($sale);
        $payable = (string) $sale->payable_total;
        if (bccomp($paid, $payable, 2) > 0) {
            throw new InvalidArgumentException(__('Captured payments exceed the payable amount for this sale.'));
        }
        $outstanding = bcsub($payable, $paid, 4);
        if (bccomp($outstanding, '0', 4) === 0) {
            return [$paid, $outstanding, 'paid'];
        }

        if ($sale->customer_id === null) {
            throw new InvalidArgumentException(__('An active credit customer is required for an unpaid sale balance.'));
        }
        $customer = Customer::query()->lockForUpdate()->findOrFail($sale->customer_id);
        if ($customer->status !== 'active' || ! in_array($customer->customer_type, ['credit', 'both'], true)) {
            throw new InvalidArgumentException(__('This customer is not enabled for credit sales.'));
        }
        if ($customer->credit_limit !== null) {
            $projected = bcadd(app(CustomerBalance::class)->for($customer), $outstanding, 4);
            if (bccomp($projected, (string) $customer->credit_limit, 4) > 0) {
                throw new InvalidArgumentException(__('This sale exceeds the customer credit limit.'));
            }
        }

        return [$paid, $outstanding, bccomp($paid, '0', 2) > 0 ? 'partial' : 'unpaid'];
    }

    /** @param array<int, array<string, mixed>> $tenders */
    private function hasCashTender(array $tenders): bool
    {
        return collect($tenders)->contains(fn (array $tender): bool => $this->isCashMethod($tender['method']));
    }

    private function isCashMethod(PaymentMethod $method): bool
    {
        return $method->isCash();
    }

    /**
     * @return array{shift: PosShift, lines: array<int, array<string, mixed>>, tax: ?TaxSetting, totals: array<string, mixed>}
     */
    private function revalidateSuspendedContext(User $cashier, Sale $sale, bool $lock): array
    {
        abort_unless($sale->status === 'suspended' && $sale->suspendedSale?->status === 'suspended', 422, __('This suspended sale is no longer available.'));
        $ownsSale = (int) $sale->cashier_id === (int) $cashier->id;
        abort_unless($ownsSale || $cashier->is_super_admin || $cashier->can('suspended_sales.view'), 403);

        $storeQuery = Store::query()->visibleTo($cashier)->whereKey($sale->store_id)->where('status', 'active')->where('type', 'selling')->with('company');
        $store = $lock ? $storeQuery->lockForUpdate()->firstOrFail() : $storeQuery->firstOrFail();
        $shiftQuery = PosShift::query()->with('cashDrawer')->whereKey($sale->shift_id);
        $shift = $lock ? $shiftQuery->lockForUpdate()->firstOrFail() : $shiftQuery->firstOrFail();
        /** @var ShiftState $shiftState */
        $shiftState = $shift->status;
        if (! $shiftState->acceptsActivity()) {
            throw new InvalidArgumentException(__('The original shift is closed or closing; this suspended sale cannot be resumed.'));
        }
        if ((int) $shift->cashier_id !== (int) $sale->cashier_id
            || (int) $shift->branch_id !== (int) $sale->branch_id
            || (int) $shift->store_id !== (int) $sale->store_id
            || (int) $shift->cash_drawer_id !== (int) $sale->cash_drawer_id) {
            throw new InvalidArgumentException(__('The active shift, branch, store, or drawer no longer matches this suspended sale.'));
        }
        if (! DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)
            ->where('cashier_id', $shift->cashier_id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists()) {
            throw new InvalidArgumentException(__('The original shift no longer has an active cashier and drawer assignment.'));
        }

        $currency = strtoupper(trim((string) $store->company?->currency_code));
        if ($currency === '' || $currency === 'TBD' || $currency !== strtoupper((string) $sale->currency_code) || $currency !== strtoupper((string) $shift->currency_code)) {
            throw new InvalidArgumentException(__('The suspended sale, shift, and selling store currencies do not match.'));
        }

        $requestedLines = $sale->lines->sortBy('line_number')->map(static fn (SaleLine $line): array => [
            'product_id' => (int) $line->product_id,
            'product_unit_id' => $line->product_unit_id,
            'quantity' => (string) $line->entered_quantity,
            'open_price_amount' => $line->is_open_price ? (string) $line->entered_unit_price : null,
            'open_price_reason' => $line->open_price_reason,
            'open_price_approval_id' => $line->open_price_approval_record_id,
            'discount_amount' => (string) $line->discount_amount,
            'discount_type' => $line->discount_type,
            'discount_reason' => $line->discount_reason,
            'discount_approval_id' => $line->discount_approval_record_id,
            'discount_previous_amount' => (string) $line->discount_amount,
        ])->values()->all();

        $sale->loadMissing('customer');
        $lines = $this->resolveLines($cashier, $store, $requestedLines, $sale->customer);
        $this->assertPricesRemainCurrent($store, $lines);
        $this->assertOpenPriceApprovalsRemainCurrent($cashier, $store, $lines);
        $this->assertDiscountApprovalsRemainCurrent($cashier, $store, $lines);
        $tax = $this->resolveTax($cashier, (bool) $sale->tax_applicable);
        $totals = $this->calculator->calculate(
            array_map(static fn (array $line): array => [
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
            ], $lines),
            '0.00',
            $tax === null ? ['applicable' => false] : ['applicable' => true, 'rate' => (string) $tax->rate, 'inclusive' => (bool) $tax->is_tax_inclusive],
        );
        $this->assertMinimumPrices($cashier, $lines, $totals);

        return compact('shift', 'lines', 'tax', 'totals');
    }

    /** @param array<int, array<string, mixed>> $lines @param array<int, array<string, mixed>> $tenders */
    private function fingerprint(Store $store, User $cashier, array $lines, ?TaxSetting $tax, array $tenders, bool $suspend, ?Customer $customer): string
    {
        $payload = [
            'store_id' => $store->id,
            'cashier_id' => $cashier->id,
            'customer_id' => $customer?->id,
            'suspend' => $suspend,
            'tax_setting_id' => $tax?->id,
            'lines' => array_map(static fn (array $line): array => [
                'product_id' => $line['product']->id,
                'product_unit_id' => $line['product_unit_id'],
                'entered_quantity' => $line['entered_quantity'],
                'conversion_factor_snapshot' => $line['conversion_factor_snapshot'],
                'price_identity' => $line['price_identity'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'discount_type' => $line['discount_type'],
                'discount_reason' => $line['discount_reason'],
                'discount_previous_type' => $line['discount_previous_type'],
                'discount_previous_amount' => $line['discount_previous_amount'],
                'open_price_reason' => $line['open_price_reason'],
            ], $lines),
            'tenders' => array_map(static fn (array $tender): array => [
                'method_id' => $tender['method']->id,
                'amount' => (string) ($tender['amount'] ?? ''),
                'tendered' => (string) ($tender['tendered'] ?? ''),
                'evidence_reference' => (string) ($tender['evidence_reference'] ?? ''),
                'evidence_attachment_id' => (string) ($tender['evidence_attachment_id'] ?? ''),
                'gift_card_id' => ($tender['gift_card'] ?? null)?->id,
            ], $tenders),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
