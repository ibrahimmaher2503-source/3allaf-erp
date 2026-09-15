<?php

namespace App\Modules\Platform\Guards;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Data\CorrectionReferenceData;
use Illuminate\Validation\ValidationException;

final class AssertCorrectionScope
{
    public function execute(User $actor, ImmutableSourceContract $source, CorrectionReferenceData $reference): void
    {
        if ($source->sourceBranchId() !== $reference->branchId
            || $source->sourceStoreId() !== $reference->storeId) {
            throw ValidationException::withMessages([
                'scope' => __('The correction scope must match the original source scope.'),
            ]);
        }

        if ($reference->branchId !== null && ! $actor->canAccessBranch($reference->branchId)) {
            throw ValidationException::withMessages(['scope' => __('You cannot correct a record outside your branch scope.')]);
        }

        if ($reference->storeId !== null && ! $actor->canAccessStore($reference->storeId)) {
            throw ValidationException::withMessages(['scope' => __('You cannot correct a record outside your store scope.')]);
        }
    }
}
