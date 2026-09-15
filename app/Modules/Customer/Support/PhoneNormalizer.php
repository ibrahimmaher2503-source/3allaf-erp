<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use Closure;
use InvalidArgumentException;

final class PhoneNormalizer
{
    /**
     * Return the canonical national representation used by Egyptian forms.
     *
     * Egyptian local and international forms are stored as digits only, with
     * the national leading zero restored (for example, +20 1012345678 becomes
     * 01012345678). Other international numbers retain the existing digits-only
     * behavior for backwards compatibility.
     */
    public static function normalize(string $phone): string
    {
        $input = self::westernDigits(trim($phone));
        if ($input === '' || preg_match('/[^0-9\s+().\/-]/u', $input)) {
            throw new InvalidArgumentException(__('Enter a valid Egyptian phone number, for example 01012345678 or +20 1012345678.'));
        }

        $digits = preg_replace('/[^0-9]+/', '', $input);
        if (! is_string($digits) || $digits === '') {
            throw new InvalidArgumentException(__('Enter a valid Egyptian phone number, for example 01012345678 or +20 1012345678.'));
        }

        $isEgyptianInternational = str_starts_with($input, '+20')
            || str_starts_with($digits, '0020')
            || (str_starts_with($digits, '20') && strlen($digits) === 12);
        if ($isEgyptianInternational) {
            $national = str_starts_with($digits, '0020') ? substr($digits, 4) : substr($digits, 2);
            $digits = '0'.$national;
        }

        if (str_starts_with($digits, '0')) {
            if (! self::isEgyptianNational($digits)) {
                throw new InvalidArgumentException(__('Enter a valid Egyptian phone number, for example 01012345678 or +20 1012345678.'));
            }

            return $digits;
        }

        if (! preg_match('/^[0-9]{7,20}$/', $digits)) {
            throw new InvalidArgumentException(__('Enter a valid phone number using 7 to 20 digits.'));
        }

        return $digits;
    }

    /** @return Closure(string, mixed, Closure): void */
    public static function validationRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            try {
                self::normalize((string) $value);
            } catch (InvalidArgumentException) {
                $fail(__('Enter a valid Egyptian phone number, for example 01012345678 or +20 1012345678.'));
            }
        };
    }

    private static function isEgyptianNational(string $digits): bool
    {
        return preg_match('/^01[0125][0-9]{8}$/', $digits) === 1
            || preg_match('/^0[2-9][0-9]{8}$/', $digits) === 1;
    }

    private static function westernDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
