<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use InvalidArgumentException;

/**
 * Policy-neutral source boundary for referenced retail corrections.
 * Settlement, stock disposition, and refund rules remain module/owner inputs.
 */
final class RetailCorrectionSourceResolver
{
    public function resolve(string $sourceType, int $sourceId): ImmutableSourceContract
    {
        $model = match ($sourceType) {
            'sale' => Sale::class,
            'sale_payment' => SalePayment::class,
            'shift' => PosShift::class,
            'cash_movement' => CashMovement::class,
            default => throw new InvalidArgumentException(__('Unsupported retail correction source type.')),
        };

        /** @var ImmutableSourceContract $source */
        $source = $model::query()->findOrFail($sourceId);

        return $source;
    }
}
