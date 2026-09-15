<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\PartyWalletLedger;

final class PartyWalletBalance
{
    public function forCustomer(Customer|int $customer, ?User $viewer = null): string
    {
        $customerId = $customer instanceof Customer ? (int) $customer->id : $customer;
        $query = PartyWalletLedger::query()->where('customer_id', $customerId);
        if ($viewer !== null) {
            $query->visibleTo($viewer);
        }

        return bcadd((string) ($query->sum('amount') ?: '0'), '0', 4);
    }
}
