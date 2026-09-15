<?php

namespace App\Modules\Platform\Guards;

use App\Modules\Platform\Contracts\ImmutableSourceContract;
use Illuminate\Validation\ValidationException;

final class AssertSourceVersionCurrent
{
    public function execute(ImmutableSourceContract $source, ?string $expectedVersion, ?string $expectedHash): void
    {
        if (! $this->matches($source->sourceVersion(), $expectedVersion)
            || ! $this->matches($source->sourceHash(), $expectedHash)) {
            throw ValidationException::withMessages([
                'source_version' => __('The source is stale. Reload it before creating a correction.'),
            ]);
        }
    }

    private function matches(?string $current, ?string $expected): bool
    {
        return $current === $expected || ($current === null && $expected === null);
    }
}
