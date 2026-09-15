<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Collection;

final class SetupContinuation
{
    /** @param Collection<int, array<string, mixed>> $steps @return array<string, mixed>|null */
    public function next(Collection $steps): ?array
    {
        $authorized = $steps->filter(static fn (array $step): bool => (bool) ($step['can_access'] ?? false))->values();

        return $authorized->first(static fn (array $step): bool => ! $step['complete'] && ($step['decision'] ?? null) === null)
            ?? $authorized->first(static fn (array $step): bool => $step['required'] && ! $step['complete']);
    }
}
