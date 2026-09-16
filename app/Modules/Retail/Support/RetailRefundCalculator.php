<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturnLine;
use App\Modules\Retail\Models\RetailReturnSettlement;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class RetailRefundCalculator
{
    private const RESERVED_STATUSES = ['draft', 'inspection', 'submitted', 'approved', 'completed'];

    /**
     * @param array<int, array<string, mixed>> $requested
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, string>}
     */
    public function prepare(User $actor, Sale $sale, array $requested, ?GiftReceipt $receipt = null, ?int $excludingReturnId = null): array
    {
        $sale->loadMissing(['lines', 'store.company']);
        $allowed = $receipt?->lines->keyBy('sale_line_id');
        $seen = [];
        $lines = [];
        $grossTotal = '0.00';
        $discountTotal = '0.00';
        $netTotal = '0.00';
        $taxTotal = '0.00';

        foreach (array_values($requested) as $index => $input) {
            $saleLine = $sale->lines->firstWhere('id', (int) ($input['sale_line_id'] ?? 0));
            if (! $saleLine instanceof SaleLine || ($allowed !== null && ! $allowed->has($saleLine->id)) || isset($seen[$saleLine->id])) {
                throw ValidationException::withMessages(['lines.'.$index => __('A refund item is duplicated or is not eligible from this sale.')]);
            }
            $seen[$saleLine->id] = true;
            $quantity = trim((string) ($input['quantity'] ?? '0'));
            if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0) {
                throw ValidationException::withMessages(['lines.'.$index.'.quantity' => __('Refund quantities must be positive with at most six decimal places.')]);
            }

            $remaining = $this->remainingQuantity($saleLine, $excludingReturnId);
            if (bccomp($quantity, $remaining, 6) > 0) {
                throw ValidationException::withMessages(['lines.'.$index.'.quantity' => __('The refund quantity exceeds the remaining refundable quantity.')]);
            }

            $condition = (string) ($input['condition'] ?? 'sellable');
            $disposition = (string) ($input['disposition'] ?? ($condition === 'sellable' ? 'restock' : 'quarantine'));
            if (! in_array($condition, ['sellable', 'non_sellable', 'damaged', 'manager_review'], true)
                || ! in_array($disposition, ['restock', 'quarantine'], true)) {
                throw ValidationException::withMessages(['lines.'.$index => __('The item condition or inventory disposition is invalid.')]);
            }
            $requiresDamaged = $disposition === 'quarantine' || in_array($condition, ['non_sellable', 'damaged'], true);
            $storeId = isset($input['return_store_id']) && (int) $input['return_store_id'] > 0
                ? (int) $input['return_store_id']
                : ($requiresDamaged
                    ? (int) ($this->returnStores($actor, $sale)->firstWhere('type', 'damaged')?->id ?? 0)
                    : (int) $sale->store_id);
            $returnStore = $this->returnStore($actor, $sale, $storeId, $condition, $disposition, $index);

            $gross = $this->portion((string) $saleLine->gross_amount, $quantity, (string) $saleLine->quantity, bccomp($quantity, $remaining, 6) === 0 ? $this->remainingLineValue($saleLine, 'gross_value', (string) $saleLine->gross_amount, $excludingReturnId) : null);
            $discount = $this->portion((string) $saleLine->discount_amount, $quantity, (string) $saleLine->quantity, bccomp($quantity, $remaining, 6) === 0 ? $this->remainingLineValue($saleLine, 'discount_value', (string) $saleLine->discount_amount, $excludingReturnId) : null);
            $net = bcsub($gross, $discount, 2);
            $lineTaxEntitlement = $this->lineTaxEntitlement($sale, $saleLine);
            $taxRemaining = $this->remainingLineValue($saleLine, 'tax_value', $lineTaxEntitlement, $excludingReturnId);
            $tax = bccomp($quantity, $remaining, 6) === 0
                ? $taxRemaining
                : $this->portion($lineTaxEntitlement, $quantity, (string) $saleLine->quantity, null);
            if (bccomp($tax, $taxRemaining, 2) > 0) {
                $tax = $taxRemaining;
            }

            $unitValue = bcdiv((string) $saleLine->net_amount, (string) $saleLine->quantity, 4);
            $lines[] = [
                'sale_line_id' => $saleLine->id,
                'product_id' => $saleLine->product_id,
                'return_store_id' => $returnStore->id,
                'line_number' => $saleLine->line_number,
                'quantity' => $quantity,
                'unit_value' => $unitValue,
                'gross_value' => $gross,
                'discount_value' => $discount,
                'eligible_value' => $net,
                'tax_value' => $tax,
                'condition' => $condition,
                'disposition' => $disposition,
                'inspection_notes' => filled($input['inspection_notes'] ?? null) ? trim((string) $input['inspection_notes']) : null,
            ];
            $grossTotal = bcadd($grossTotal, $gross, 2);
            $discountTotal = bcadd($discountTotal, $discount, 2);
            $netTotal = bcadd($netTotal, $net, 2);
            $taxTotal = bcadd($taxTotal, $tax, 2);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Select at least one refundable item.')]);
        }

        $roundingRemaining = bcsub((string) $sale->cash_rounding_amount, $this->reservedReturnTotal($sale, 'rounding_refund', $excludingReturnId), 2);
        $remainingBase = bcsub(bcadd((string) $sale->total, (string) $sale->cash_rounding_amount, 2), $this->reservedReturnTotal($sale, 'settlement_value', $excludingReturnId), 2);
        $beforeRounding = bcadd($netTotal, $taxTotal, 2);
        $rounding = bccomp($beforeRounding, bcsub($remainingBase, $roundingRemaining, 2), 2) === 0
            ? $roundingRemaining
            : $this->proportionalRounding((string) $sale->cash_rounding_amount, $beforeRounding, (string) $sale->total, $roundingRemaining);
        $settlement = bcadd($beforeRounding, $rounding, 2);
        if (bccomp($settlement, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['lines' => __('The selected items do not produce a refundable amount.')]);
        }

        return ['lines' => $lines, 'totals' => [
            'subtotal_refund' => $grossTotal,
            'discount_refund' => $discountTotal,
            'net_refund' => $netTotal,
            'tax_refund' => $taxTotal,
            'rounding_refund' => $rounding,
            'settlement_value' => $settlement,
        ]];
    }

    public function remainingQuantity(SaleLine $line, ?int $excludingReturnId = null): string
    {
        $reserved = RetailReturnLine::query()
            ->where('sale_line_id', $line->id)
            ->when($excludingReturnId !== null, fn ($query) => $query->where('retail_return_id', '!=', $excludingReturnId))
            ->whereHas('retailReturn', fn ($query) => $query->whereIn('status', self::RESERVED_STATUSES))
            ->sum('quantity');

        return bcsub((string) $line->quantity, (string) $reserved, 6);
    }

    /** @return array<int, array{original_payment_id: int, method_id: int, amount: string, safe_reference: string}> */
    public function defaultAllocations(Sale $sale, string $amount): array
    {
        $sale->loadMissing('payments.paymentMethod');
        $outstanding = DecimalMoney::round($amount, 2, __('The refund amount is invalid.'));
        $result = [];
        foreach ($sale->payments->sortBy('id') as $payment) {
            if (bccomp($outstanding, '0.00', 2) <= 0) {
                break;
            }
            $already = (string) RetailReturnSettlement::query()
                ->where('original_payment_id', $payment->id)
                ->where('direction', 'refund')
                ->sum('amount');
            $available = bcsub((string) $payment->amount, $already, 2);
            if (bccomp($available, '0.00', 2) <= 0) {
                continue;
            }
            $allocated = bccomp($available, $outstanding, 2) < 0 ? $available : $outstanding;
            $result[] = [
                'original_payment_id' => (int) $payment->id,
                'method_id' => (int) $payment->payment_method_id,
                'amount' => $allocated,
                'safe_reference' => '',
            ];
            $outstanding = bcsub($outstanding, $allocated, 2);
        }
        if (bccomp($outstanding, '0.00', 2) !== 0) {
            throw ValidationException::withMessages(['refund_method' => __('The original payment allocations do not contain enough refundable value.')]);
        }

        return $result;
    }

    /** @return Collection<int, Store> */
    public function returnStores(User $actor, Sale $sale): Collection
    {
        $sale->loadMissing('store');

        return Store::query()->visibleTo($actor)
            ->where('company_id', $sale->store?->company_id)
            ->where('branch_id', $sale->branch_id)
            ->where('status', 'active')
            ->orderByRaw("CASE WHEN id = ? THEN 0 WHEN type = 'damaged' THEN 2 ELSE 1 END", [$sale->store_id])
            ->orderBy('code')
            ->limit(50)
            ->get();
    }

    private function returnStore(User $actor, Sale $sale, int $storeId, string $condition, string $disposition, int $index): Store
    {
        $sale->loadMissing('store');
        $store = Store::query()->visibleTo($actor)
            ->whereKey($storeId)
            ->where('company_id', $sale->store?->company_id)
            ->where('branch_id', $sale->branch_id)
            ->where('status', 'active')
            ->first();
        if ($store === null) {
            throw ValidationException::withMessages(['lines.'.$index.'.return_store_id' => __('The selected inventory return location is not authorized for this sale.')]);
        }
        if (($disposition === 'quarantine' || in_array($condition, ['non_sellable', 'damaged'], true)) && $store->type !== 'damaged') {
            throw ValidationException::withMessages(['lines.'.$index.'.return_store_id' => __('Damaged or non-saleable items must use an active damaged inventory location.')]);
        }

        return $store;
    }

    private function lineTaxEntitlement(Sale $sale, SaleLine $line): string
    {
        if (bccomp((string) $sale->tax_total, '0.00', 2) === 0) {
            return '0.00';
        }
        $netTotal = $sale->lines->reduce(fn (string $sum, SaleLine $item): string => bcadd($sum, (string) $item->net_amount, 2), '0.00');
        if (bccomp($netTotal, '0.00', 2) <= 0) {
            return '0.00';
        }
        $ordered = $sale->lines->sortBy('line_number')->values();
        if ((int) $ordered->last()?->id === (int) $line->id) {
            $prior = '0.00';
            foreach ($ordered->slice(0, -1) as $item) {
                $prior = bcadd($prior, DecimalMoney::round(bcdiv(bcmul((string) $sale->tax_total, (string) $item->net_amount, 6), $netTotal, 6), 2, __('The refund tax allocation is invalid.')), 2);
            }

            return bcsub((string) $sale->tax_total, $prior, 2);
        }

        return DecimalMoney::round(bcdiv(bcmul((string) $sale->tax_total, (string) $line->net_amount, 6), $netTotal, 6), 2, __('The refund tax allocation is invalid.'));
    }

    private function remainingLineValue(SaleLine $line, string $column, string $entitlement, ?int $excludingReturnId = null): string
    {
        $reserved = (string) RetailReturnLine::query()
            ->where('sale_line_id', $line->id)
            ->when($excludingReturnId !== null, fn ($query) => $query->where('retail_return_id', '!=', $excludingReturnId))
            ->whereHas('retailReturn', fn ($query) => $query->whereIn('status', self::RESERVED_STATUSES))
            ->sum($column);

        return bcsub($entitlement, $reserved, 2);
    }

    private function reservedReturnTotal(Sale $sale, string $column, ?int $excludingReturnId = null): string
    {
        return (string) $sale->retailReturns()->when($excludingReturnId !== null, fn ($query) => $query->where('id', '!=', $excludingReturnId))->whereIn('status', self::RESERVED_STATUSES)->sum($column);
    }

    private function portion(string $total, string $quantity, string $sourceQuantity, ?string $finalRemaining): string
    {
        if ($finalRemaining !== null) {
            return $finalRemaining;
        }

        return DecimalMoney::round(bcdiv(bcmul($total, $quantity, 6), $sourceQuantity, 6), 2, __('The refund value allocation is invalid.'));
    }

    private function proportionalRounding(string $rounding, string $refundBase, string $saleTotal, string $remaining): string
    {
        if (bccomp($rounding, '0.00', 2) === 0 || bccomp($saleTotal, '0.00', 2) <= 0) {
            return '0.00';
        }
        $portion = DecimalMoney::round(bcdiv(bcmul($rounding, $refundBase, 6), $saleTotal, 6), 2, __('The cash rounding refund allocation is invalid.'));
        if (bccomp($rounding, '0.00', 2) > 0 && bccomp($portion, $remaining, 2) > 0) {
            return $remaining;
        }
        if (bccomp($rounding, '0.00', 2) < 0 && bccomp($portion, $remaining, 2) < 0) {
            return $remaining;
        }

        return $portion;
    }
}
