<?php

namespace App\Modules\Platform\Data;

use Carbon\CarbonInterface;

/**
 * Source modules provide their own validated source reference and required request permission.
 */
readonly class ApprovalRequestData
{
    /** @param array<string, mixed>|null $limitContext */
    public function __construct(
        public string $sourceType,
        public string $sourceId,
        public ?string $sourceVersion,
        public string $requestedAction,
        public string $requestPermission,
        public ?int $branchId = null,
        public ?int $storeId = null,
        public ?string $reasonCode = null,
        public ?string $reasonText = null,
        public ?array $limitContext = null,
        public ?string $sourceHash = null,
        public ?string $idempotencyKey = null,
        public ?CarbonInterface $expiresAt = null,
        public ?string $decisionPermission = null,
    ) {}

    public function approvalPermission(): string
    {
        return $this->decisionPermission ?: $this->sourceType.'.approve';
    }

    public function pendingKey(): string
    {
        return hash('sha256', implode('|', [$this->sourceType, $this->sourceId, $this->requestedAction]));
    }
}
