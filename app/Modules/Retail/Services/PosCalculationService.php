<?php

declare(strict_types=1);

namespace App\Modules\Retail\Services;

use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use InvalidArgumentException;

/**
 * The single source of truth for POS arithmetic (DEC-066, `docs/48` §3).
 *
 * The same figures must appear on screen, on the thermal receipt, on the A4
 * invoice, and in reports, so no caller may recompute any of this locally.
 *
 * Canonical order:
 *   1. line gross    = quantity x unit price
 *   2. line discount (one type only, POS-05)
 *   3. line net      = gross - discount            <- rounded
 *   4. invoice discount allocated pro-rata
 *   5. taxable base  = sum of line net after allocation
 *   6. tax           only when enabled for this invoice (POS-04)
 *   7. invoice total = taxable base + tax          <- rounded
 *   8. cash rounding applied to the payable amount only
 *
 * Rounding happens at steps 3 and 7 only. Intermediate values are never
 * re-rounded (POSF-01).
 */
final class PosCalculationService
{
    private const MONEY_SCALE = 2;

    /** Unit prices carry more precision than money totals. */
    private const RATE_SCALE = 4;

    private const QUANTITY_SCALE = 6;

    /**
     * @param  array<int, array{quantity: numeric-string, unit_price: numeric-string, discount_amount?: numeric-string}>  $lines
     * @param  array{applicable: bool, rate?: numeric-string|null, inclusive?: bool}  $tax
     * @return array{
     *     lines: array<int, array{gross_amount: numeric-string, discount_amount: numeric-string, allocated_invoice_discount: numeric-string, net_amount: numeric-string}>,
     *     subtotal: numeric-string,
     *     line_discount_total: numeric-string,
     *     invoice_discount: numeric-string,
     *     discount_total: numeric-string,
     *     taxable_base: numeric-string,
     *     tax_total: numeric-string,
     *     total: numeric-string
     * }
     */
    public function calculate(array $lines, string $invoiceDiscount = '0.00', array $tax = ['applicable' => false]): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException(__('Add at least one product to the cart.'));
        }

        // Steps 1-3.
        $computed = [];
        $subtotalGross = '0.00';
        $lineDiscountTotal = '0.00';
        foreach ($lines as $line) {
            $gross = $this->money(bcmul($line['quantity'], $line['unit_price'], self::RATE_SCALE));
            $discount = $this->money($line['discount_amount'] ?? '0.00');

            if (bccomp($discount, '0', self::MONEY_SCALE) < 0) {
                throw new InvalidArgumentException(__('A discount cannot be negative.'));
            }
            if (bccomp($discount, $gross, self::MONEY_SCALE) > 0) {
                throw new InvalidArgumentException(__('A line discount cannot exceed the line value.'));
            }

            $computed[] = [
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'net_after_line_discount' => bcsub($gross, $discount, self::MONEY_SCALE),
            ];
            $subtotalGross = bcadd($subtotalGross, $gross, self::MONEY_SCALE);
            $lineDiscountTotal = bcadd($lineDiscountTotal, $discount, self::MONEY_SCALE);
        }

        $netAfterLineDiscounts = bcsub($subtotalGross, $lineDiscountTotal, self::MONEY_SCALE);

        // Step 4 - allocate the invoice discount pro-rata to lines.
        $invoiceDiscount = $this->money($invoiceDiscount);
        if (bccomp($invoiceDiscount, '0', self::MONEY_SCALE) < 0) {
            throw new InvalidArgumentException(__('A discount cannot be negative.'));
        }
        if (bccomp($invoiceDiscount, $netAfterLineDiscounts, self::MONEY_SCALE) > 0) {
            throw new InvalidArgumentException(__('The invoice discount cannot exceed the net value of the sale.'));
        }

        $allocations = $this->allocateProRata($invoiceDiscount, array_column($computed, 'net_after_line_discount'));

        // Step 5.
        $taxableBase = '0.00';
        $resultLines = [];
        foreach ($computed as $index => $line) {
            $net = bcsub($line['net_after_line_discount'], $allocations[$index], self::MONEY_SCALE);
            $taxableBase = bcadd($taxableBase, $net, self::MONEY_SCALE);
            $resultLines[] = [
                'gross_amount' => $line['gross_amount'],
                'discount_amount' => $line['discount_amount'],
                'allocated_invoice_discount' => $allocations[$index],
                'net_amount' => $net,
            ];
        }

        // Steps 6-7.
        $taxTotal = $this->tax($taxableBase, $tax);
        $total = $tax['applicable'] && ($tax['inclusive'] ?? false)
            ? $taxableBase
            : bcadd($taxableBase, $taxTotal, self::MONEY_SCALE);

        return [
            'lines' => $resultLines,
            'subtotal' => $subtotalGross,
            'line_discount_total' => $lineDiscountTotal,
            'invoice_discount' => $invoiceDiscount,
            'discount_total' => bcadd($lineDiscountTotal, $invoiceDiscount, self::MONEY_SCALE),
            'taxable_base' => $taxableBase,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    /**
     * @param  numeric-string  $taxableBase
     * @param  array{applicable: bool, rate?: numeric-string|null, inclusive?: bool}  $tax
     * @return numeric-string
     */
    private function tax(string $taxableBase, array $tax): string
    {
        if (! $tax['applicable']) {
            return '0.00';
        }

        $rate = $tax['rate'] ?? null;
        if ($rate === null || trim((string) $rate) === '') {
            // POS-04 lets an authorised user enable tax per invoice, but the
            // rate itself is a BLK-008 owner value. Guessing it would fabricate
            // a financial figure.
            throw new InvalidArgumentException(__('Tax was enabled for this sale but no effective tax rate is configured.'));
        }

        if (bccomp((string) $rate, '0', self::MONEY_SCALE) < 0) {
            throw new InvalidArgumentException(__('The tax rate cannot be negative.'));
        }

        if ($tax['inclusive'] ?? false) {
            // base already contains the tax: tax = base - base / (1 + rate)
            $divisor = bcadd('1', bcdiv((string) $rate, '100', self::RATE_SCALE + 2), self::RATE_SCALE + 2);
            $exclusive = bcdiv($taxableBase, $divisor, self::RATE_SCALE);

            return $this->money(bcsub($taxableBase, $exclusive, self::RATE_SCALE));
        }

        return $this->money(bcdiv(bcmul($taxableBase, (string) $rate, self::RATE_SCALE), '100', self::RATE_SCALE));
    }

    /**
     * Allocate an amount across weights so the parts sum exactly to the whole.
     *
     * Largest-remainder: distribute the floor of each share, then hand the
     * rounding pennies to the largest remainders. Naive per-line rounding
     * leaves the allocation off by a cent and breaks the receipt total.
     *
     * @param  numeric-string  $amount
     * @param  array<int, numeric-string>  $weights
     * @return array<int, numeric-string>
     */
    private function allocateProRata(string $amount, array $weights): array
    {
        $count = count($weights);
        if (bccomp($amount, '0', self::MONEY_SCALE) === 0) {
            return array_fill(0, $count, '0.00');
        }

        $totalWeight = '0.00';
        foreach ($weights as $weight) {
            $totalWeight = bcadd($totalWeight, $weight, self::MONEY_SCALE);
        }

        if (bccomp($totalWeight, '0', self::MONEY_SCALE) === 0) {
            throw new InvalidArgumentException(__('An invoice discount cannot be allocated across a sale with no net value.'));
        }

        $allocated = [];
        $running = '0.00';
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $exact = bcdiv(bcmul($amount, $weight, self::RATE_SCALE + 2), $totalWeight, self::RATE_SCALE + 2);
            $floor = bcadd($exact, '0', self::MONEY_SCALE); // bcadd truncates toward zero
            $allocated[$index] = $floor;
            $remainders[$index] = bcsub($exact, $floor, self::RATE_SCALE + 2);
            $running = bcadd($running, $floor, self::MONEY_SCALE);
        }

        $shortfall = bcsub($amount, $running, self::MONEY_SCALE);
        if (bccomp($shortfall, '0', self::MONEY_SCALE) > 0) {
            arsort($remainders);
            $penny = '0.01';
            foreach (array_keys($remainders) as $index) {
                if (bccomp($shortfall, '0', self::MONEY_SCALE) <= 0) {
                    break;
                }
                $allocated[$index] = bcadd($allocated[$index], $penny, self::MONEY_SCALE);
                $shortfall = bcsub($shortfall, $penny, self::MONEY_SCALE);
            }
        }

        ksort($allocated);

        return $allocated;
    }

    /**
     * Step 8 — cash rounding adjusts what is collected, never the invoice total.
     *
     * Returns the rounding adjustment. Positive means the customer pays more
     * than the invoice total; negative means less. The difference is posted as
     * its own line so the drawer reconciles and the receipt stays internally
     * consistent (`docs/48` §3).
     *
     * @param  numeric-string  $total
     * @return numeric-string
     */
    public function cashRoundingAdjustment(string $total): string
    {
        return $this->cashRoundingAdjustmentForDenomination($total, $this->cashRoundingDenomination());
    }

    /**
     * Pure decimal arithmetic seam. Settlement callers must use
     * cashRoundingAdjustment(), which obtains the approved denomination.
     *
     * @param  numeric-string  $total
     * @param  numeric-string  $denomination
     * @return numeric-string
     */
    public function cashRoundingAdjustmentForDenomination(string $total, string $denomination): string
    {
        if (bccomp($denomination, '0', self::MONEY_SCALE) <= 0) {
            throw new PosFinancialConfigurationException($this->cashRoundingConfigurationMessage());
        }

        $units = bcdiv($total, $denomination, 0);
        $lower = bcmul($units, $denomination, self::MONEY_SCALE);
        if (bccomp($lower, $total, self::MONEY_SCALE) === 0) {
            return '0.00';
        }

        $upper = bcadd($lower, $denomination, self::MONEY_SCALE);
        $distanceDown = bcsub($total, $lower, self::MONEY_SCALE);
        $distanceUp = bcsub($upper, $total, self::MONEY_SCALE);

        // Ties round up, matching the "customer pays the round figure" convention.
        return bccomp($distanceUp, $distanceDown, self::MONEY_SCALE) <= 0
            ? bcsub($upper, $total, self::MONEY_SCALE)
            : bcsub($lower, $total, self::MONEY_SCALE);
    }

    /**
     * True when a cash tender for this total needs a denomination that the
     * owner has not configured yet.
     *
     * @param  numeric-string  $total
     */
    public function cashRoundingIsUnresolved(string $total): bool
    {
        try {
            $this->cashRoundingDenomination();

            return false;
        } catch (PosFinancialConfigurationException) {
            return true;
        }
    }

    /** @return numeric-string */
    public function cashRoundingDenomination(): string
    {
        try {
            $denomination = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION);
        } catch (InvalidArgumentException $exception) {
            throw new PosFinancialConfigurationException($this->cashRoundingConfigurationMessage(), previous: $exception);
        }

        if ($denomination === null || bccomp($denomination, '0', self::RATE_SCALE) <= 0) {
            throw new PosFinancialConfigurationException($this->cashRoundingConfigurationMessage());
        }

        return $denomination;
    }

    private function cashRoundingConfigurationMessage(): string
    {
        return __('Cash payment is unavailable because POS cash rounding is missing or invalid. Ask an administrator to set the cash rounding denomination to 5.');
    }

    /**
     * Validate then round to the money scale.
     *
     * Rounding (not truncation) is required at `docs/48` §3 steps 3 and 7, so
     * this cannot use `bcadd`-style scaling the way quantity normalisation does.
     *
     * @return numeric-string
     */
    private function money(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('A monetary amount must be a valid number.'));
        }

        return DecimalMoney::round($value, self::MONEY_SCALE);
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public function normalizeQuantity(string $value): string
    {
        return bcadd($value, '0', self::QUANTITY_SCALE);
    }
}
