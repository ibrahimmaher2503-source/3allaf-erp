<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use InvalidArgumentException;

final class ProductBarcodePolicy
{
    public function international(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\d{8}$|^\d{12,14}$/', $value)) throw new InvalidArgumentException(__('International barcodes must be a valid GTIN-8, UPC-12, EAN-13, or GTIN-14 number.'));
        $digits = array_map('intval', str_split($value)); $check = array_pop($digits); $sum = 0; $weight = 3;
        for ($index = count($digits) - 1; $index >= 0; $index--) { $sum += $digits[$index] * $weight; $weight = $weight === 3 ? 1 : 3; }
        if ((10 - ($sum % 10)) % 10 !== $check) throw new InvalidArgumentException(__('The international barcode check digit is invalid.'));
        return $value;
    }

    public function supplierDigits(string $stableCode): string
    {
        $digits = preg_replace('/\D/', '', strtoupper(trim($stableCode))) ?? '';
        if (strlen($digits) < 4) throw new InvalidArgumentException(__('The supplier stable code must contain at least four numeric digits before a local barcode can be generated.'));
        return substr($digits, 0, 4);
    }

    public function local(string $supplierDigits, int $serial): string
    {
        if (! preg_match('/^\d{4}$/', $supplierDigits) || $serial < 1 || $serial > 999999) throw new InvalidArgumentException(__('The local barcode sequence input is invalid.'));
        return '0'.$supplierDigits.str_pad((string) $serial, 6, '0', STR_PAD_LEFT);
    }
}
