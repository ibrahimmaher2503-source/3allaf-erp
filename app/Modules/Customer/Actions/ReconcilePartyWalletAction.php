<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Support\PartyWalletBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReconcilePartyWalletAction
{
    public function execute(User $actor, Customer $customer): string
    {
        return DB::transaction(function () use ($actor, $customer): string {
            $entries = PartyWalletLedger::query()->visibleTo($actor)->where('customer_id', $customer->id)->orderBy('id')->lockForUpdate()->get();
            $running = '0.0000';
            foreach ($entries as $entry) {
                if (bccomp((string) $entry->balance_before, $running, 4) !== 0) {
                    throw new InvalidArgumentException(__('Party Wallet balance-before does not reconcile at ledger entry :id.', ['id' => $entry->id]));
                }
                $expected = bcadd($running, (string) $entry->amount, 4);
                if (bccomp((string) $entry->balance_after, $expected, 4) !== 0) {
                    throw new InvalidArgumentException(__('Party Wallet balance-after does not reconcile at ledger entry :id.', ['id' => $entry->id]));
                }
                $running = $expected;
            }

            $derived = app(PartyWalletBalance::class)->forCustomer($customer, $actor);
            if (bccomp($derived, $running, 4) !== 0) {
                throw new InvalidArgumentException(__('Party Wallet aggregate balance does not reconcile with its ledger sequence.'));
            }

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'party_wallet_reconciled',
                source: $customer,
                before: ['balance' => $running],
                after: ['balance' => $derived, 'entry_count' => $entries->count()],
                branchId: $entries->last()?->branch_id,
                storeId: $entries->last()?->store_id,
                metadata: ['wallet' => 'party', 'entry_count' => $entries->count(), 'scope_limited' => ! $actor->is_super_admin],
            );

            return $derived;
        }, 5);
    }
}
