<?php

declare(strict_types=1);

namespace App\Modules\Platform\Enums;

enum TaxTreatment: string
{
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case OutOfScope = 'out_of_scope';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $treatment): string => $treatment->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('Standard'),
            self::ZeroRated => __('Zero Rated'),
            self::Exempt => __('Exempt'),
            self::OutOfScope => __('Out of Scope'),
        };
    }
}
