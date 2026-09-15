<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final class ProductQuantity
{
    private const MAX_DECIMAL_PLACES = 6;

    public static function format(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        $raw = trim((string) $value);
        if (! preg_match('/^[+-]?\d+(?:\.\d{1,6})?$/', $raw)) {
            throw new UnexpectedValueException(__('Invalid product quantity data. Please review the source document.'));
        }
        $negative = str_starts_with($raw, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($raw, '+-'), 2), 2, '');
        $whole = ltrim($whole, '0');
        $fraction = rtrim($fraction, '0');
        if ($whole === '') {
            $whole = '0';
        }
        if ($whole === '0' && $fraction === '') {
            return '0';
        }

        return ($negative ? '-' : '').$whole.($fraction === '' ? '' : '.'.$fraction);
    }

    public static function normalize(mixed $value, string $field = 'quantity', bool $allowZero = false, int $decimalPlaces = 0): int|string
    {
        self::assertDecimalPlaces($decimalPlaces);
        $raw = trim((string) $value);
        if (! self::matchesPrecision($raw, false, $decimalPlaces) || (! $allowZero && bccomp($raw, '0', $decimalPlaces) <= 0) || ($allowZero && bccomp($raw, '0', $decimalPlaces) < 0)) {
            throw ValidationException::withMessages([$field => $decimalPlaces === 0 ? __('Product quantities must be whole numbers.') : __('Product quantity has an invalid decimal precision.')]);
        }
        if (bccomp($raw, (string) PHP_INT_MAX, 0) > 0) {
            throw ValidationException::withMessages([$field => __('Product quantity exceeds the allowed limit.')]);
        }

        return $decimalPlaces === 0 ? (int) $raw : bcadd($raw, '0', $decimalPlaces);
    }

    public static function normalizeSigned(mixed $value, string $field = 'quantity', bool $allowZero = false, int $decimalPlaces = 0): int|string
    {
        self::assertDecimalPlaces($decimalPlaces);
        $raw = trim((string) $value);
        if (! self::matchesPrecision($raw, true, $decimalPlaces) || (! $allowZero && bccomp($raw, '0', $decimalPlaces) === 0)) {
            throw ValidationException::withMessages([$field => $decimalPlaces === 0 ? __('Product quantities must be whole numbers.') : __('Product quantity has an invalid decimal precision.')]);
        }
        if (bccomp($raw, (string) PHP_INT_MAX, 0) > 0 || bccomp($raw, (string) PHP_INT_MIN, 0) < 0) {
            throw ValidationException::withMessages([$field => __('Product quantity exceeds the allowed limit.')]);
        }

        return $decimalPlaces === 0 ? (int) $raw : bcadd($raw, '0', $decimalPlaces);
    }

    /** @return array<int|string, string> */
    public static function rule(bool $allowZero = false): array
    {
        return ['integer', $allowZero ? 'min:0' : 'min:1'];
    }

    private static function assertDecimalPlaces(int $decimalPlaces): void
    {
        if ($decimalPlaces < 0 || $decimalPlaces > self::MAX_DECIMAL_PLACES) {
            throw new \InvalidArgumentException('Product quantity precision must be between zero and six decimal places.');
        }
    }

    private static function matchesPrecision(string $value, bool $signed, int $decimalPlaces): bool
    {
        if (! preg_match($signed ? '/^-?\d+(?:\.\d{1,6})?$/' : '/^\d+(?:\.\d{1,6})?$/', $value)) {
            return false;
        }
        $fraction = explode('.', $value, 2)[1] ?? '';

        return strlen(rtrim($fraction, '0')) <= $decimalPlaces;
    }
}
