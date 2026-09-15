<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use InvalidArgumentException;

/**
 * Exact decimal normalization for POS amounts.
 *
 * Financial values never pass through binary floating point. Rounding is
 * half-away-from-zero and is used only at the two boundaries approved by
 * POSF-01: line net and invoice total.
 */
final class DecimalMoney
{
    /** @return numeric-string */
    public static function round(string $value, int $scale = 2, ?string $message = null): string
    {
        $value = trim($value);
        if ($scale < 0 || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException($message ?? __('A monetary amount must be a valid number.'));
        }

        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $kept = $scale === 0 ? '' : substr($fraction, 0, $scale);
        $normalized = ltrim($whole, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        if ($scale > 0) {
            $normalized .= '.'.$kept;
        }

        if ((int) $fraction[$scale] >= 5) {
            $increment = $scale === 0 ? '1' : '0.'.str_repeat('0', $scale - 1).'1';
            $normalized = bcadd($normalized, $increment, $scale);
        } else {
            $normalized = bcadd($normalized, '0', $scale);
        }

        if ($negative && bccomp($normalized, '0', $scale) !== 0) {
            return '-'.$normalized;
        }

        return $normalized;
    }

    /** @return numeric-string */
    public static function normalize(string $value, int $scale = 4, ?string $message = null): string
    {
        $value = trim($value);
        if ($scale < 0 || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException($message ?? __('A decimal value must be a valid number.'));
        }

        return bcadd($value, '0', $scale);
    }
}
