<?php

declare(strict_types=1);

namespace App\Modules\CashControl\Support;

use App\Modules\CashControl\Models\CashAccount;

final class CashAccountBalance
{
    /** @return numeric-string */
    public function for(CashAccount $account): string
    {
        return bcadd((string) $account->transactions()->sum('amount'), '0', 4);
    }
}
