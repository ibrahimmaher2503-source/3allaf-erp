<?php

namespace App\Modules\Platform\Guards;

use App\Modules\Platform\Contracts\ImmutableSourceContract;
use Illuminate\Validation\ValidationException;

final class AssertSourceEditable
{
    public function execute(ImmutableSourceContract $source): void
    {
        if ($source->sourceState() !== 'draft') {
            throw ValidationException::withMessages([
                'source_state' => __('Only draft records may be edited.'),
            ]);
        }
    }
}
