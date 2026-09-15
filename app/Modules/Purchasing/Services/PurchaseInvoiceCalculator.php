<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Services\ConvertProductQuantity;
use App\Support\ProductQuantity;
use InvalidArgumentException;

final class PurchaseInvoiceCalculator
{
    public function __construct(private readonly ConvertProductQuantity $converter) {}

    /**
     * @param  array<string, mixed>  $line
     * @return array{quantity: numeric-string, unit_cost: numeric-string, discount_type: null|string, discount_value: numeric-string, discount_amount: numeric-string, tax_rate: numeric-string, tax_code: null|string, tax_amount: numeric-string, subtotal: numeric-string, line_total: numeric-string}
     */
    public function calculateLine(array $line): array
    {
        $product = $line['product'] ?? null;
        $productUnit = $line['product_unit'] ?? null;
        if ($productUnit !== null && (! $product instanceof Product || ! $productUnit instanceof ProductUnit)) {
            throw new InvalidArgumentException(__('Product unit context is invalid.'));
        }
        $decimalPlaces = $product instanceof Product && $product->fractional_quantity
            ? min(6, (int) ($productUnit?->unit?->decimal_places ?? 6))
            : 0;
        $enteredQuantity = bcadd((string) ProductQuantity::normalize($line['entered_quantity'] ?? $line['quantity'] ?? null, decimalPlaces: $decimalPlaces), '0', 6);
        $factor = $productUnit instanceof ProductUnit ? (string) $productUnit->conversion_factor : '1.000000';
        $quantity = $productUnit instanceof ProductUnit ? $this->converter->execute($product, $productUnit, $enteredQuantity) : $enteredQuantity;
        $enteredUnitPrice = $this->decimal($line['entered_unit_price'] ?? $line['unit_cost'] ?? null, 4, 'Unit cost');
        $unitCost = bcdiv($enteredUnitPrice, $factor, 4);
        $discountType = ($line['discount_type'] ?? '') !== '' ? ($line['discount_type'] ?? null) : null;
        $discountValue = $this->decimal($line['discount_value'] ?? 0, 4, 'Discount');
        $taxRate = $this->decimal($line['tax_rate'] ?? 0, 4, 'Tax rate');

        if (bccomp($quantity, '0', 6) <= 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
        }
        if (bccomp($unitCost, '0', 4) < 0) {
            throw new InvalidArgumentException(__('Unit cost cannot be negative.'));
        }
        if ($discountType !== null && ! in_array($discountType, ['percentage', 'amount'], true)) {
            throw new InvalidArgumentException(__('Discount type is not supported.'));
        }
        if (bccomp($discountValue, '0', 4) < 0) {
            throw new InvalidArgumentException(__('Discount cannot be negative.'));
        }
        if (bccomp($taxRate, '0', 4) !== 0) {
            throw new InvalidArgumentException(__('Purchase tax is disabled in Phase 1; tax rate must be zero.'));
        }

        $gross = bcmul($quantity, $unitCost, 8);
        $discount = $discountType === 'percentage'
            ? bcdiv(bcmul($gross, $discountValue, 8), '100', 8)
            : $discountValue;

        if (bccomp($discount, $gross, 8) > 0) {
            throw new InvalidArgumentException(__('Discount cannot exceed the line value.'));
        }

        $subtotal = bcsub($gross, $discount, 8);
        $tax = bcdiv(bcmul($subtotal, $taxRate, 8), '100', 8);
        $lineTotal = bcadd($subtotal, $tax, 8);

        return [
            'product_unit_id' => $productUnit?->id,
            'entered_quantity' => $enteredQuantity,
            'conversion_factor_snapshot' => bcadd($factor, '0', 6),
            'entered_unit_price' => $enteredUnitPrice,
            'quantity' => bcadd($quantity, '0', 6),
            'unit_cost' => bcadd($unitCost, '0', 4),
            'discount_type' => $discountType,
            'discount_value' => bcadd($discountValue, '0', 4),
            'discount_amount' => bcadd($discount, '0', 4),
            'tax_rate' => bcadd($taxRate, '0', 4),
            'tax_code' => (($line['tax_code'] ?? '') !== '') ? ($line['tax_code'] ?? null) : null,
            'tax_amount' => bcadd($tax, '0', 4),
            'subtotal' => bcadd($subtotal, '0', 4),
            'line_total' => bcadd($lineTotal, '0', 4),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{subtotal: numeric-string, discountAmount: numeric-string, taxAmount: numeric-string, totalAmount: numeric-string, calculated: list<array{quantity: numeric-string, unit_cost: numeric-string, discount_type: null|string, discount_value: numeric-string, discount_amount: numeric-string, tax_rate: numeric-string, tax_code: null|string, tax_amount: numeric-string, subtotal: numeric-string, line_total: numeric-string}>}
     */
    public function calculateDocument(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException(__('A purchase invoice must contain at least one line item.'));
        }

        $subtotal = '0.0000';
        $discountAmount = '0.0000';
        $taxAmount = '0.0000';
        $totalAmount = '0.0000';
        $calculated = [];

        foreach ($lines as $line) {
            $result = $this->calculateLine($line);
            $subtotal = bcadd($subtotal, (string) $result['subtotal'], 4);
            $discountAmount = bcadd($discountAmount, (string) $result['discount_amount'], 4);
            $taxAmount = bcadd($taxAmount, (string) $result['tax_amount'], 4);
            $totalAmount = bcadd($totalAmount, (string) $result['line_total'], 4);
            $calculated[] = $result;
        }

        return compact('subtotal', 'discountAmount', 'taxAmount', 'totalAmount', 'calculated');
    }

    /**
     * @param  array<int, array{id:int, subtotal:mixed, quantity:mixed}>  $lines
     * @return array<int, array{id:int, allocated_charge_amount:numeric-string, inventory_unit_cost:numeric-string}>
     */
    public function allocateLandedCost(array $lines, string $totalCharges): array
    {
        $totalCharges = $this->decimal($totalCharges, 4, 'Purchase charges');
        $goodsSubtotal = '0.0000';
        foreach ($lines as $line) {
            $goodsSubtotal = bcadd($goodsSubtotal, $this->decimal($line['subtotal'], 4, 'Line subtotal'), 4);
            if (bccomp($this->decimal($line['quantity'], 6, 'Quantity'), '0', 6) <= 0) {
                throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
            }
        }
        if ($lines === [] || bccomp($goodsSubtotal, '0', 4) <= 0) {
            throw new InvalidArgumentException(__('Landed cost requires purchase lines with a positive goods subtotal.'));
        }

        $allocatedTotal = '0.0000';
        $lastIndex = array_key_last($lines);
        $result = [];
        foreach ($lines as $index => $line) {
            $subtotal = $this->decimal($line['subtotal'], 4, 'Line subtotal');
            $allocation = $index === $lastIndex
                ? bcsub($totalCharges, $allocatedTotal, 4)
                : bcdiv(bcmul($subtotal, $totalCharges, 8), $goodsSubtotal, 4);
            $allocatedTotal = bcadd($allocatedTotal, $allocation, 4);
            $result[] = [
                'id' => (int) $line['id'],
                'allocated_charge_amount' => $allocation,
                'inventory_unit_cost' => bcdiv(bcadd($subtotal, $allocation, 4), $this->decimal($line['quantity'], 6, 'Quantity'), 6),
            ];
        }

        return $result;
    }

    /** @return numeric-string */
    private function decimal(mixed $value, int $scale, string $label): string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__(':label must be a valid non-negative number.', ['label' => $label]));
        }

        $numericValue = $value;

        // @phpstan-ignore argument.type
        return bcadd($numericValue, '0', $scale);
    }
}
