<?php

namespace App\Modules\Platform\Data;

readonly class ValidatedAttachment
{
    /** @param array<string, int> $dimensions */
    public function __construct(
        public string $originalFilename,
        public string $extension,
        public string $declaredMimeType,
        public string $detectedMimeType,
        public int $sizeBytes,
        public string $sha256,
        public array $dimensions = [],
    ) {}
}
