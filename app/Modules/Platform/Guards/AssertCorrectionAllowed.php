<?php

namespace App\Modules\Platform\Guards;

use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Data\CorrectionReferenceData;
use App\Modules\Platform\Enums\CorrectionType;
use Illuminate\Validation\ValidationException;

final class AssertCorrectionAllowed
{
    /** @param array<int, CorrectionType|string> $allowedTypes */
    public function execute(ImmutableSourceContract $source, CorrectionReferenceData $reference, array $allowedTypes): void
    {
        app(AssertSourceImmutable::class)->execute($source);

        $allowed = array_map(
            static fn (CorrectionType|string $type): string => $type instanceof CorrectionType ? $type->value : $type,
            $allowedTypes,
        );

        if (! in_array($reference->correctionType->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'correction_type' => __('This correction type is not permitted for the source module.'),
            ]);
        }
    }
}
