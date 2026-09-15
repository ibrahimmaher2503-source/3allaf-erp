<?php

namespace App\Modules\Platform\Contracts;

/**
 * Contract implemented by a real module document when it opts into
 * approved-record immutability and correction-by-reference.
 */
interface ImmutableSourceContract
{
    public function sourceType(): string;

    public function sourceId(): string;

    public function sourceState(): string;

    public function sourceVersion(): ?string;

    public function sourceHash(): ?string;

    public function sourceBranchId(): ?int;

    public function sourceStoreId(): ?int;
}
