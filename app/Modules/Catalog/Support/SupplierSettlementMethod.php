<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

final class SupplierSettlementMethod
{
    public const VALUES = ['cash', 'cheques', 'installments', 'trust_deposits', 'other'];

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'cash' => __('Cash'),
            'cheques' => __('Cheques'),
            'installments' => __('Installments'),
            'trust_deposits' => __('Trust Deposits'),
            'other' => __('Other'),
        ];
    }

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }
}
