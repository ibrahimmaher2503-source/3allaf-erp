<?php

namespace App\Modules\Platform\Models\Concerns;

use App\Modules\Platform\Models\Store;
use BackedEnum;
use LogicException;

trait GuardsApprovedDocument
{
    private bool $namedDocumentMutation = false;

    /** @var list<string> */
    private static array $immutableDocumentStates = [
        'approved', 'posted', 'final', 'dispatched', 'in_transit', 'partially_received', 'received', 'difference_review', 'reconciled',
        'closed', 'reversed', 'cancelled', 'rejected', 'expired', 'superseded',
    ];

    public static function bootGuardsApprovedDocument(): void
    {
        static::updating(function ($document): void {
            $originalState = $document->normalizeDocumentState($document->getOriginal($document->documentStateColumn()));
            if (in_array($originalState, self::$immutableDocumentStates, true) && ! $document->namedDocumentMutation) {
                throw new LogicException('Approved or terminal documents may only change through a named correction or lifecycle action.');
            }
        });

        static::deleting(function ($document): void {
            if (in_array($document->sourceState(), self::$immutableDocumentStates, true)) {
                throw new LogicException('Approved or terminal documents are immutable and cannot be deleted.');
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    public function mutateApprovedDocument(array $attributes): void
    {
        $this->namedDocumentMutation = true;
        try {
            $this->fill($attributes)->save();
        } finally {
            $this->namedDocumentMutation = false;
        }
    }

    public function sourceType(): string
    {
        return static::class;
    }

    public function sourceId(): string
    {
        return (string) $this->getKey();
    }

    public function sourceState(): string
    {
        return $this->normalizeDocumentState($this->getAttribute($this->documentStateColumn()));
    }

    public function sourceVersion(): ?string
    {
        $version = $this->getAttribute('lock_version');

        return $version === null ? null : (string) $version;
    }

    public function sourceHash(): ?string
    {
        $hash = $this->getAttribute('source_hash');

        if ($hash !== null) {
            return (string) $hash;
        }

        $attributes = $this->getAttributes();
        foreach ([
            $this->documentStateColumn(), 'lock_version', 'updated_at', 'submitted_at', 'submitted_by',
            'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason',
            'cancelled_at', 'cancelled_by', 'cancel_reason', 'cancellation_reason', 'closed_at',
            'closed_by', 'reversed_at', 'reversed_by', 'reversal_reason', 'superseded_at',
            'dispatched_at', 'dispatched_by', 'received_at', 'received_by', 'reconciled_at',
        ] as $lifecycleField) {
            unset($attributes[$lifecycleField]);
        }
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function sourceBranchId(): ?int
    {
        $branchId = $this->getAttribute('branch_id');
        if ($branchId !== null) {
            return (int) $branchId;
        }

        $storeId = $this->sourceStoreId();
        $storeBranchId = $storeId === null ? null : Store::query()->whereKey($storeId)->value('branch_id');

        return $storeBranchId === null ? null : (int) $storeBranchId;
    }

    public function sourceStoreId(): ?int
    {
        $storeId = $this->getAttribute('store_id');

        return $storeId === null ? null : (int) $storeId;
    }

    protected function documentStateColumn(): string
    {
        return 'status';
    }

    private function normalizeDocumentState(mixed $state): string
    {
        return strtolower((string) ($state instanceof BackedEnum ? $state->value : $state));
    }
}
