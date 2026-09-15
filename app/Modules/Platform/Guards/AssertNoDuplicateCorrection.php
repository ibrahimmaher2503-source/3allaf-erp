<?php

namespace App\Modules\Platform\Guards;

use App\Modules\Platform\Data\CorrectionReferenceData;
use Illuminate\Validation\ValidationException;

final class AssertNoDuplicateCorrection
{
    /** @param callable(CorrectionReferenceData): bool $exists */
    public function execute(CorrectionReferenceData $reference, callable $exists): void
    {
        if ($exists($reference)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('A correction for this source and idempotency key already exists.'),
            ]);
        }
    }
}
