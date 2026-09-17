<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class AssignCustomerPriceListAction
{
    public function execute(User $actor, Customer $customer, ?PriceList $list): Customer
    {
        Gate::forUser($actor)->authorize('pricing_lists.assign');

        return DB::transaction(function () use ($actor, $customer, $list): Customer {
            $locked = Customer::query()->with('createdStore')->lockForUpdate()->findOrFail($customer->id);
            $companyId = (int) $locked->createdStore?->company_id;
            if ($companyId <= 0 || ($list !== null && ((int) $list->company_id !== $companyId || ! $list->isEffective()))) {
                throw new InvalidArgumentException(__('Select an active price list belonging to the customer company.'));
            }
            $before = ['price_list_id' => $locked->price_list_id];
            $locked->mutateMaster(['price_list_id' => $list?->id, 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('pricing', 'assign_customer_price_list', $locked, $before, ['price_list_id' => $list?->id], reasonText: __('Customer pricing level changed.'));

            return $locked->fresh('priceList');
        });
    }
}
