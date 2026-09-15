<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use InvalidArgumentException;

final class PaymentReferenceGuard
{
    public static function normalize(?string $reference): ?string
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }

        if (mb_strlen($reference) > 190 || preg_match('/\b(?:cvv|cvc|pan|card[ _-]?number)\b/i', $reference)) {
            throw new InvalidArgumentException(__('Enter only a safe transaction reference. Card numbers and security codes are prohibited.'));
        }

        $digits = preg_replace('/\D+/', '', $reference) ?? '';
        if (strlen($digits) >= 13 && strlen($digits) <= 19 && self::passesLuhn($digits)) {
            throw new InvalidArgumentException(__('Enter only a safe transaction reference. Card numbers and security codes are prohibited.'));
        }

        return $reference;
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $alternate = false;
        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum > 0 && $sum % 10 === 0;
    }
}
