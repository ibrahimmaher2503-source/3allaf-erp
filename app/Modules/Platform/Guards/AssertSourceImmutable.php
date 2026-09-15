<?php

namespace App\Modules\Platform\Guards;

use App\Modules\Platform\Contracts\ImmutableSourceContract;
use Illuminate\Validation\ValidationException;

final class AssertSourceImmutable
{
    /** @var array<int, string> */
    private const IMMUTABLE_STATES = [
        'approved',
        'posted',
        'final',
        'closed',
        'reversed',
        'cancelled',
        'rejected',
        'expired',
        'superseded',
    ];

    public function execute(ImmutableSourceContract $source): void
    {
        if (! in_array($source->sourceState(), self::IMMUTABLE_STATES, true)) {
            throw ValidationException::withMessages([
                'source_state' => __('The source is not an approved or terminal record eligible for correction.'),
            ]);
        }
    }
}
