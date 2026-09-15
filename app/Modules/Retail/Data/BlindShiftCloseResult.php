<?php

declare(strict_types=1);

namespace App\Modules\Retail\Data;

/** Cashier-safe acknowledgement. Expected and variance figures never cross this boundary. */
final readonly class BlindShiftCloseResult
{
    public function __construct(
        public int $shiftId,
        public int $submissionId,
        public int $attempt,
        public string $shiftState,
    ) {}
}
