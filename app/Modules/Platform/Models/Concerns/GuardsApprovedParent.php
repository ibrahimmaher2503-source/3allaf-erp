<?php

namespace App\Modules\Platform\Models\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait GuardsApprovedParent
{
    private bool $namedParentLineMutation = false;

    public static function bootGuardsApprovedParent(): void
    {
        static::updating(function ($line): void {
            if ($line->parentIsImmutable() && ! $line->namedParentLineMutation) {
                throw new LogicException('Lines of approved or terminal documents may only change through a named lifecycle or correction action.');
            }
        });
        static::deleting(function ($line): void {
            if ($line->parentIsImmutable()) {
                throw new LogicException('Lines of approved or terminal documents cannot be deleted.');
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    public function mutateApprovedParentLine(array $attributes): void
    {
        $this->namedParentLineMutation = true;
        try {
            $this->fill($attributes)->save();
        } finally {
            $this->namedParentLineMutation = false;
        }
    }

    abstract protected function approvedParent(): ?Model;

    private function parentIsImmutable(): bool
    {
        $parent = $this->approvedParent();
        if ($parent === null) {
            return false;
        }
        $state = $parent->getAttribute('status') ?? $parent->getAttribute('state');
        $state = strtolower((string) ($state instanceof BackedEnum ? $state->value : $state));

        return in_array($state, ['approved', 'posted', 'final', 'dispatched', 'in_transit', 'partially_received', 'received', 'difference_review', 'reconciled', 'closed', 'reversed', 'cancelled', 'rejected', 'expired', 'superseded'], true);
    }
}
