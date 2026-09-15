<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

class AllocatePurchaseOrderNumberAction
{
    /**
     * Allocate a concurrency-safe PO number using DocumentSequence or fallback demo sequence.
     */
    public function execute(?int $branchId = null): string
    {
        return $branchId === null
            ? app(AllocateDocumentNumber::class)->execute('purchase_order')
            : app(AllocateDocumentNumber::class)->executeForBranch('purchase_order', $branchId);
    }
}
