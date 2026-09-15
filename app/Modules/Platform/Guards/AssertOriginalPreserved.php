<?php

namespace App\Modules\Platform\Guards;

use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Data\CorrectionReferenceData;
use Illuminate\Validation\ValidationException;

final class AssertOriginalPreserved
{
    public function execute(ImmutableSourceContract $source, CorrectionReferenceData $reference): void
    {
        if ($source->sourceId() !== $reference->originalSourceId
            || $source->sourceType() !== $reference->originalSourceType
            || $source->sourceHash() !== $reference->originalSourceHash) {
            throw ValidationException::withMessages([
                'source' => __('The original approved source changed during correction and was not preserved.'),
            ]);
        }
    }
}
