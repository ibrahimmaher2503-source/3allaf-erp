<?php

namespace App\Modules\Platform\Contracts;

/**
 * Integration boundary for a future module's transactional final numbering.
 * Implementations must allocate inside the correction transaction.
 */
interface CorrectionNumberAllocator
{
    /** @param array<string, scalar|null> $scope */
    public function allocate(string $documentType, array $scope, string $idempotencyKey): string;
}
