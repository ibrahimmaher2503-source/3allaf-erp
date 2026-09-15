<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierCommunicationDestination;
use Illuminate\Validation\ValidationException;

final class ResolveSupplierOrderRecipientAction
{
    public function execute(Supplier $supplier): SupplierCommunicationDestination
    {
        $recipient = $supplier->communicationDestinations()
            ->where('purpose', 'purchase_order')
            ->where('status', 'active')
            ->where('is_primary', true)
            ->first();

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'recipient' => __('A designated purchase-order recipient is required for this supplier.'),
            ]);
        }

        return $recipient;
    }
}
