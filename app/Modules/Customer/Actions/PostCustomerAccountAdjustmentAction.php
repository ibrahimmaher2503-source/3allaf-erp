<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAccountAdjustment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PostCustomerAccountAdjustmentAction
{
    public function execute(User $actor, Customer $customer, string $amount, string $reason, string $idempotencyKey, ?string $date = null, ?Sale $sale = null, ?string $reference = null): CustomerAccountAdjustment
    {
        Gate::forUser($actor)->authorize('customers.sensitive');
        $amount = trim($amount);
        $reason = trim($reason);
        $idempotencyKey = trim($idempotencyKey);
        if (! preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $amount) || bccomp($amount, '0', 4) === 0) {
            throw new InvalidArgumentException(__('A customer adjustment must be a non-zero signed decimal.'));
        }
        if ($reason === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException(__('Adjustment reason and idempotency key are required.'));
        }
        $amount = bcadd($amount, '0', 4);
        $payload = ['customer_id' => (int) $customer->id, 'sale_id' => $sale?->id, 'amount' => $amount, 'reason' => $reason, 'date' => $date ?: now()->toDateString(), 'reference' => trim((string) $reference)];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            return DB::transaction(function () use ($actor, $customer, $sale, $amount, $reason, $idempotencyKey, $payload, $hash): CustomerAccountAdjustment {
                $customer = Customer::query()->visibleTo($actor)->lockForUpdate()->findOrFail($customer->id);
                $sale = $sale === null ? null : Sale::query()->visibleTo($actor)->lockForUpdate()->findOrFail($sale->id);
                $existing = CustomerAccountAdjustment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replay($existing, $hash);
                }
                if ($customer->status !== 'active') {
                    throw new InvalidArgumentException(__('Adjustments can only be posted for an active customer.'));
                }
                if ($sale !== null && ((int) $sale->customer_id !== (int) $customer->id || $sale->status !== 'approved')) {
                    throw new InvalidArgumentException(__('The adjustment sale must be an approved sale for this customer.'));
                }
                $adjustment = CustomerAccountAdjustment::query()->create(['customer_id' => $customer->id, 'sale_id' => $sale?->id, 'adjustment_date' => $payload['date'], 'amount' => $amount, 'reason' => $reason, 'reference' => $payload['reference'] ?: null, 'status' => 'approved', 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash]);
                app(RecordAuditEvent::class)->execute('customer_value', 'customer_account_adjusted', $adjustment, after: ['customer_id' => $customer->id, 'amount' => $amount], branchId: $customer->created_branch_id, storeId: $customer->created_store_id, reasonText: $reason, metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey]);

                return $adjustment;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = CustomerAccountAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->replay($existing, $hash);
            }
            throw $exception;
        }
    }

    private function replay(CustomerAccountAdjustment $adjustment, string $hash): CustomerAccountAdjustment
    {
        if (! hash_equals((string) $adjustment->payload_hash, $hash)) {
            throw new InvalidArgumentException(__('This customer adjustment idempotency key was already used with different data.'));
        }

        return $adjustment;
    }
}
