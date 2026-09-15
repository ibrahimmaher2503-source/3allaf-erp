<?php

namespace App\Modules\Platform\Data;

use App\Modules\Platform\Enums\CorrectionType;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Source modules persist this reference with their own correction document.
 * This DTO deliberately does not create a cross-module correction table.
 */
readonly class CorrectionReferenceData
{
    public function __construct(
        public string $originalSourceType,
        public string $originalSourceId,
        public ?string $originalSourceVersion,
        public ?string $originalSourceHash,
        public CorrectionType $correctionType,
        public string $correctionSourceType,
        public string $correctionSourceId,
        public string $reason,
        public int $requestedBy,
        public ?int $approvedBy,
        public ?int $branchId,
        public ?int $storeId,
        public string $requestId,
        public string $idempotencyKey,
        public CarbonInterface $createdAt,
        public ?CarbonInterface $effectiveAt = null,
        public ?string $reversalOfCorrectionSourceType = null,
        public ?string $reversalOfCorrectionSourceId = null,
    ) {
        $errors = [];

        foreach ([
            'original_source_type' => $this->originalSourceType,
            'original_source_id' => $this->originalSourceId,
            'correction_source_type' => $this->correctionSourceType,
            'correction_source_id' => $this->correctionSourceId,
            'request_id' => $this->requestId,
            'idempotency_key' => $this->idempotencyKey,
        ] as $field => $value) {
            if (trim($value) === '') {
                $errors[$field] = __('This reference value is required.');
            }
        }

        if (trim($this->reason) === '') {
            $errors['reason'] = __('A correction reason is required.');
        }

        if ($this->originalSourceType === $this->correctionSourceType
            && $this->originalSourceId === $this->correctionSourceId) {
            $errors['correction_source_id'] = __('A correction cannot reference itself.');
        }

        if (($this->reversalOfCorrectionSourceType === null) !== ($this->reversalOfCorrectionSourceId === null)) {
            $errors['reversal_of_correction'] = __('Both reversal reference values are required together.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
