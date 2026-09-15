<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

/**
 * Canonical payment classification shared by capture and reconciliation.
 *
 * Method codes are labels and may be changed by configuration. Financial
 * behavior is determined only by the immutable method-type snapshot.
 */
final class PaymentMethodSemantics
{
    public const TYPE_CASH = 'cash';

    public static function isCashType(?string $methodType): bool
    {
        return strtolower(trim((string) $methodType)) === self::TYPE_CASH;
    }
}
