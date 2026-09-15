<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

final class AllocatePurchaseInvoiceNumberAction
{
    public function execute(?int $branchId = null): string
    {
        return $branchId === null
            ? app(AllocateDocumentNumber::class)->execute('purchase_invoice')
            : app(AllocateDocumentNumber::class)->executeForBranch('purchase_invoice', $branchId);
    }
}
