<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\CashControl\Actions\RecordCashTransactionAction;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerReceipt;
use App\Modules\Customer\Models\CustomerReceiptAllocation;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordCustomerReceiptAction
{
    /** @param array<int|string, numeric-string> $allocations */
    public function execute(User $actor, Customer $customer, PaymentMethod $method, string $amount, array $allocations, string $idempotencyKey, ?string $date = null, ?string $reference = null, ?string $notes = null, ?string $evidenceReference = null, ?int $cashAccountId = null): CustomerReceipt
    {
        Gate::forUser($actor)->authorize('customers.edit');
        $amount = $this->positiveMoney($amount);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A customer receipt idempotency key is required.'));
        }
        $normalized = [];
        foreach ($allocations as $saleId => $allocated) {
            $normalized[(int) $saleId] = $this->positiveMoney((string) $allocated);
        }
        ksort($normalized);
        if ($normalized === [] || bccomp($this->sum($normalized), $amount, 4) !== 0) {
            throw new InvalidArgumentException(__('Receipt allocations must equal the receipt amount.'));
        }
        $payload = ['customer_id' => (int) $customer->id, 'method_id' => (int) $method->id, 'amount' => $amount, 'allocations' => $normalized, 'date' => $date ?: now()->toDateString(), 'reference' => trim((string) $reference), 'notes' => trim((string) $notes), 'evidence_reference' => trim((string) $evidenceReference), 'cash_account_id' => $cashAccountId];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $customer, $method, $amount, $normalized, $idempotencyKey, $payload, $payloadHash): CustomerReceipt {
                $customer = Customer::query()->visibleTo($actor)->lockForUpdate()->findOrFail($customer->id);
                $existing = CustomerReceipt::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replay($existing, $payloadHash);
                }
                if ($customer->status !== 'active') {
                    throw new InvalidArgumentException(__('Receipts can only be recorded for an active customer.'));
                }
                $method = PaymentMethod::query()->whereKey($method->id)->where('status', 'active')->firstOrFail();
                if ((bool) $method->requires_evidence && $payload['evidence_reference'] === '') {
                    throw new InvalidArgumentException(__('This payment method requires an evidence reference.'));
                }
                $cashAccount = null;
                if ($payload['cash_account_id'] !== null) {
                    $companyId = $customer->createdStore()->value('company_id');
                    $cashAccount = CashAccount::query()->where('company_id', $companyId)->where('status', 'active')->lockForUpdate()->findOrFail($payload['cash_account_id']);
                }

                $sales = Sale::query()->visibleTo($actor)->whereIn('id', array_keys($normalized))->lockForUpdate()->get()->keyBy('id');
                if ($sales->count() !== count($normalized)) {
                    throw new InvalidArgumentException(__('One or more receipt invoices do not exist.'));
                }
                $balance = app(CustomerBalance::class);
                foreach ($normalized as $saleId => $allocated) {
                    $sale = $sales->get($saleId);
                    if ((int) $sale->customer_id !== (int) $customer->id || $sale->status !== 'approved') {
                        throw new InvalidArgumentException(__('Receipts may only be allocated to this customer approved sales.'));
                    }
                    if (bccomp($allocated, $balance->outstandingForSale($sale), 4) > 0) {
                        throw new InvalidArgumentException(__('A receipt allocation exceeds the sale outstanding amount.'));
                    }
                }

                $receipt = CustomerReceipt::query()->create(['customer_id' => $customer->id, 'payment_method_id' => $method->id, 'cash_account_id' => $payload['cash_account_id'], 'receipt_date' => $payload['date'], 'amount' => $amount, 'reference' => $payload['reference'] ?: null, 'evidence_reference' => $payload['evidence_reference'] ?: null, 'notes' => $payload['notes'] ?: null, 'status' => 'approved', 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash]);
                foreach ($normalized as $saleId => $allocated) {
                    CustomerReceiptAllocation::query()->create(['customer_receipt_id' => $receipt->id, 'sale_id' => $saleId, 'amount' => $allocated]);
                }
                if ($cashAccount !== null) {
                    app(RecordCashTransactionAction::class)->execute($actor, $cashAccount, $amount, 'customer_receipt', __('Customer receipt'), 'customer-receipt:'.$receipt->id.':cash', $payload['date'], CustomerReceipt::class, $receipt->id, $payload['reference']);
                }
                app(RecordAuditEvent::class)->execute('customer_value', 'customer_receipt_recorded', $receipt, after: ['customer_id' => $customer->id, 'amount' => $amount, 'allocation_count' => count($normalized)], branchId: $customer->created_branch_id, storeId: $customer->created_store_id, metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey]);

                return $receipt->load('allocations');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = CustomerReceipt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->replay($existing, $payloadHash);
            }
            throw $exception;
        }
    }

    private function replay(CustomerReceipt $receipt, string $hash): CustomerReceipt
    {
        if (! hash_equals((string) $receipt->payload_hash, $hash)) {
            throw new InvalidArgumentException(__('This customer receipt idempotency key was already used with different data.'));
        }

        return $receipt->load('allocations');
    }

    /** @return numeric-string */
    private function positiveMoney(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value) || bccomp($value, '0', 4) <= 0) {
            throw new InvalidArgumentException(__('Customer receipt amounts must be positive decimals.'));
        }

        return bcadd($value, '0', 4);
    }

    /** @param array<int, numeric-string> $amounts @return numeric-string */
    private function sum(array $amounts): string
    {
        $total = '0.0000';
        foreach ($amounts as $amount) {
            $total = bcadd($total, $amount, 4);
        }

        return $total;
    }
}
