<?php

namespace App\Modules\Platform\Data;

use Carbon\CarbonInterface;

/**
 * Source-owned context for a protected attachment. Source policies remain outside Platform.
 */
readonly class AttachmentSourceReference
{
    public function __construct(
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public ?int $branchId = null,
        public ?int $storeId = null,
        public string $visibility = 'private',
        public ?CarbonInterface $retentionUntil = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    public function isLinked(): bool
    {
        return $this->sourceType !== null && $this->sourceType !== ''
            && $this->sourceId !== null && $this->sourceId !== '';
    }
}
