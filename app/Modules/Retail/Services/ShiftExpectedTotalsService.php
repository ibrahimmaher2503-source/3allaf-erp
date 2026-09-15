<?php

declare(strict_types=1);

namespace App\Modules\Retail\Services;

use App\Modules\Platform\Support\PaymentMethodSemantics;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Models\RetailReturnSettlement;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;

/**
 * Derives expected shift totals from immutable linked activity (`docs/32` §9).
 *
 * Expected values are never stored as editable columns and are never returned
 * to a cashier session before submission — `docs/32` §10 and `docs/48` §8 both
 * treat that as a leak, including via a hidden field or a JSON payload.
 * Callers must therefore gate access on the manager/reviewer permission or on
 * the submission having already happened.
 */
final class ShiftExpectedTotalsService
{
    /**
     * @return array{
     *     opening_float: numeric-string,
     *     cash_sales: numeric-string,
     *     cash_movements: numeric-string,
     *     expected_cash: numeric-string,
     *     expected_by_method: array<string, numeric-string>,
     *     expected_total: numeric-string
     * }
     */
    public function derive(PosShift $shift): array
    {
        $openingFloat = $this->money((string) $shift->getAttribute('opening_cash'));

        // Only approved sales contribute. A draft or suspended sale has not
        // settled and must not appear in the drawer expectation.
        $paymentRows = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.shift_id', $shift->getKey())
            ->where('sales.status', 'approved')
            ->groupBy('sale_payments.method_code', 'sale_payments.method_type', 'sales.currency_code')
            ->select('sale_payments.method_code', 'sale_payments.method_type', 'sales.currency_code', DB::raw('SUM(sale_payments.amount) as applied'))
            ->get();

        $cashSales = '0.00';
        $expectedByMethod = [];
        foreach ($paymentRows as $row) {
            /** @var string $methodCode */
            $methodCode = $row->getAttribute('method_code');
            $methodType = (string) $row->getAttribute('method_type');
            if ((string) $row->getAttribute('currency_code') !== (string) $shift->getAttribute('currency_code')) {
                throw new \InvalidArgumentException(__('A sale payment currency does not match the shift currency.'));
            }
            $applied = $this->money((string) $row->getAttribute('applied'));

            if (PaymentMethodSemantics::isCashType($methodType)) {
                // Change leaves the drawer, so the drawer keeps only the
                // applied amount. `amount` is already net of change by
                // construction in CapturePaymentAction.
                $cashSales = bcadd($cashSales, $applied, 2);

                continue;
            }

            $expectedByMethod[$methodCode] = bcadd($expectedByMethod[$methodCode] ?? '0.00', $applied, 2);
        }

        // Completed refunds are immutable settlement rows linked to the source
        // sale, so they reduce the same shift/method expectation as the
        // original payment without mutating that payment.
        $refundRows = RetailReturnSettlement::query()
            ->join('retail_returns', 'retail_returns.id', '=', 'retail_return_settlements.retail_return_id')
            ->join('sales', 'sales.id', '=', 'retail_returns.source_sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'retail_return_settlements.payment_method_id')
            ->where(function ($query) use ($shift): void {
                $query->where('retail_returns.shift_id', $shift->getKey())
                    ->orWhere(function ($legacy) use ($shift): void {
                        $legacy->whereNull('retail_returns.shift_id')->where('sales.shift_id', $shift->getKey());
                    });
            })
            ->where('retail_returns.status', 'completed')
            ->where('retail_return_settlements.direction', 'refund')
            ->groupBy('payment_methods.code', 'payment_methods.type')
            ->select('payment_methods.code as method_code', 'payment_methods.type as method_type', DB::raw('SUM(retail_return_settlements.amount) as refunded'))
            ->get();
        foreach ($refundRows as $row) {
            $refunded = $this->money((string) $row->getAttribute('refunded'));
            if (PaymentMethodSemantics::isCashType((string) $row->getAttribute('method_type'))) {
                $cashSales = bcsub($cashSales, $refunded, 2);
            } else {
                $methodCode = (string) $row->getAttribute('method_code');
                $expectedByMethod[$methodCode] = bcsub($expectedByMethod[$methodCode] ?? '0.00', $refunded, 2);
            }
        }

        $movementTotal = $this->money((string) CashMovement::query()->where('shift_id', $shift->getKey())->sum('amount'));

        $expectedCash = bcadd(bcadd($openingFloat, $cashSales, 2), $movementTotal, 2);

        $expectedTotal = $expectedCash;
        foreach ($expectedByMethod as $amount) {
            $expectedTotal = bcadd($expectedTotal, $amount, 2);
        }

        return [
            'opening_float' => $openingFloat,
            'cash_sales' => $cashSales,
            'cash_movements' => $movementTotal,
            'expected_cash' => $expectedCash,
            'expected_by_method' => $expectedByMethod,
            'expected_total' => $expectedTotal,
        ];
    }

    /**
     * Variance = actual − expected (`docs/32` §12). A positive figure is an
     * overage, a negative figure a shortage; this sign convention is used
     * identically for cash, per method, and in total.
     *
     * @param  array<string, string>  $actualByMethod
     * @param  array{expected_cash: numeric-string, expected_by_method: array<string, numeric-string>}  $expected
     * @return array{
     *     cash_variance: numeric-string,
     *     method_variance: array<string, numeric-string>,
     *     total_variance: numeric-string
     * }
     */
    public function variance(string $actualCash, array $actualByMethod, array $expected): array
    {
        $cashVariance = bcsub($this->money($actualCash), $expected['expected_cash'], 2);

        // Union of both key sets: a method that was expected but not counted,
        // and a method counted but not expected, are both real variances.
        $methodCodes = array_unique(array_merge(
            array_keys($expected['expected_by_method']),
            array_keys($actualByMethod),
        ));

        $methodVariance = [];
        $total = $cashVariance;
        foreach ($methodCodes as $code) {
            $actual = $this->money((string) ($actualByMethod[$code] ?? '0.00'));
            $expectedAmount = $expected['expected_by_method'][$code] ?? '0.00';
            $delta = bcsub($actual, $expectedAmount, 2);
            $methodVariance[$code] = $delta;
            $total = bcadd($total, $delta, 2);
        }

        return [
            'cash_variance' => $cashVariance,
            'method_variance' => $methodVariance,
            'total_variance' => $total,
        ];
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            $value = '0';
        }

        return DecimalMoney::round($value, 2, __('A shift total must be a valid monetary amount.'));
    }
}
