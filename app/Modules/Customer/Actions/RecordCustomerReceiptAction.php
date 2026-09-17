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
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use App\Support\OldestOutstandingAllocator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordCustomerReceiptAction
{
    /** @return array<int, numeric-string> */
    public function proposeAllocations(User $actor, Customer $customer, Store $collectionStore, string $currencyCode, string $amount): array
    {
        Gate::forUser($actor)->authorize('customers.edit');
        $amount = $this->positiveMoney($amount);
        $customer = Customer::query()->visibleTo($actor)->where('status', 'active')->findOrFail($customer->id);
        $collectionStore = Store::query()->with('company')->visibleTo($actor)->where('status', 'active')->findOrFail($collectionStore->id);
        $currencyCode = strtoupper(trim($currencyCode));

        if ((int) $customer->createdStore()->value('company_id') !== (int) $collectionStore->company_id
            || strtoupper((string) $collectionStore->company?->currency_code) !== $currencyCode) {
            throw new InvalidArgumentException(__('Receipt proposals must use the customer company and currency.'));
        }

        $invoices = app(CustomerBalance::class)
            ->outstandingInvoices($customer, $actor, $collectionStore->company, $currencyCode)
            ->map(fn (Sale $sale): array => ['id' => (int) $sale->id, 'outstanding' => (string) $sale->current_outstanding]);

        return app(OldestOutstandingAllocator::class)->allocate($invoices, $amount);
    }

    /** @param array<int|string, numeric-string> $allocations */
    public function execute(User $actor, Customer $customer, PaymentMethod $method, string $amount, array $allocations, string $idempotencyKey, ?string $date = null, ?string $reference = null, ?string $notes = null, ?string $evidenceReference = null, ?int $cashAccountId = null, ?Store $collectionStore = null, ?string $currencyCode = null): CustomerReceipt
    {
        Gate::forUser($actor)->authorize('customers.edit');
        $amount = $this->positiveMoney($amount);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A customer receipt idempotency key is required.'));
        }
        $normalized = [];
        foreach ($allocations as $saleId => $allocated) {
            if (trim((string) $allocated) === '' || bccomp((string) $allocated, '0', 4) === 0) {
                continue;
            }
            $saleId = (int) $saleId;
            if ($saleId <= 0 || isset($normalized[$saleId])) {
                throw new InvalidArgumentException(__('Receipt allocations must reference distinct sales.'));
            }
            $normalized[$saleId] = $this->positiveMoney((string) $allocated);
        }
        ksort($normalized);
        if (bccomp($this->sum($normalized), $amount, 4) > 0) {
            throw new InvalidArgumentException(__('Receipt allocations cannot exceed the receipt amount.'));
        }
        $collectionStore ??= Store::query()->visibleTo($actor)->where('status', 'active')->find($customer->created_store_id);
        if ($collectionStore === null) {
            throw new InvalidArgumentException(__('A visible active collection store is required.'));
        }
        $currencyCode = strtoupper(trim((string) ($currencyCode ?: $collectionStore->company()->value('currency_code') ?: 'EGP')));
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new InvalidArgumentException(__('Customer receipt currency code is invalid.'));
        }
        $payload = ['customer_id' => (int) $customer->id, 'store_id' => (int) $collectionStore->id, 'currency_code' => $currencyCode, 'method_id' => (int) $method->id, 'amount' => $amount, 'allocations' => $normalized, 'date' => $date ?: now()->toDateString(), 'reference' => trim((string) $reference), 'notes' => trim((string) $notes), 'evidence_reference' => trim((string) $evidenceReference), 'cash_account_id' => $cashAccountId];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $customer, $collectionStore, $method, $amount, $normalized, $idempotencyKey, $payload, $payloadHash): CustomerReceipt {
                $customer = Customer::query()->visibleTo($actor)->lockForUpdate()->findOrFail($customer->id);
                $collectionStore = Store::query()->with('company')->visibleTo($actor)->where('status', 'active')->lockForUpdate()->findOrFail($collectionStore->id);
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
                $customerCompanyId = (int) $customer->createdStore()->value('company_id');
                if ($customerCompanyId !== (int) $collectionStore->company_id) {
                    throw new InvalidArgumentException(__('Customer receipts must be collected within the customer company.'));
                }
                if (strtoupper((string) $collectionStore->company?->currency_code) !== $payload['currency_code']) {
                    throw new InvalidArgumentException(__('Receipt currency must match the collection company.'));
                }
                if ($method->isCash() && $payload['cash_account_id'] === null) {
                    throw new InvalidArgumentException(__('Cash customer receipts require a cash account.'));
                }
                if (! $method->isCash() && $payload['cash_account_id'] !== null) {
                    throw new InvalidArgumentException(__('Non-cash customer receipts cannot use a cash account.'));
                }
                $cashAccount = $payload['cash_account_id'] === null ? null : CashAccount::query()
                    ->where('company_id', $collectionStore->company_id)
                    ->where('type', 'cash')
                    ->where('currency_code', $payload['currency_code'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->findOrFail($payload['cash_account_id']);

                $sales = Sale::query()->visibleTo($actor)->with('store')->whereIn('id', array_keys($normalized))->lockForUpdate()->get()->keyBy('id');
                if ($sales->count() !== count($normalized)) {
                    throw new InvalidArgumentException(__('One or more receipt invoices do not exist.'));
                }
                $balance = app(CustomerBalance::class);
                foreach ($normalized as $saleId => $allocated) {
                    $sale = $sales->get($saleId);
                    if ((int) $sale->customer_id !== (int) $customer->id || $sale->status !== 'approved') {
                        throw new InvalidArgumentException(__('Receipts may only be allocated to this customer approved sales.'));
                    }
                    if ((string) $sale->currency_code !== $payload['currency_code'] || (int) $sale->store?->company_id !== (int) $collectionStore->company_id) {
                        throw new InvalidArgumentException(__('Receipt allocations must use one currency and one company.'));
                    }
                    if (bccomp($allocated, $balance->outstandingForSale($sale), 4) > 0) {
                        throw new InvalidArgumentException(__('A receipt allocation exceeds the sale outstanding amount.'));
                    }
                }

                $receipt = CustomerReceipt::query()->create(['customer_id' => $customer->id, 'store_id' => $collectionStore->id, 'currency_code' => $payload['currency_code'], 'payment_method_id' => $method->id, 'cash_account_id' => $payload['cash_account_id'], 'receipt_date' => $payload['date'], 'amount' => $amount, 'reference' => $payload['reference'] ?: null, 'evidence_reference' => $payload['evidence_reference'] ?: null, 'notes' => $payload['notes'] ?: null, 'status' => 'approved', 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash]);
                foreach ($normalized as $saleId => $allocated) {
                    CustomerReceiptAllocation::query()->create(['customer_receipt_id' => $receipt->id, 'sale_id' => $saleId, 'amount' => $allocated]);
                }
                if ($cashAccount !== null) {
                    app(RecordCashTransactionAction::class)->execute($actor, $cashAccount, $amount, 'customer_receipt', __('Customer receipt'), 'customer-receipt:'.$receipt->id.':cash', $payload['date'], CustomerReceipt::class, $receipt->id, $payload['reference']);
                }
                app(RecordAuditEvent::class)->execute('customer_value', 'customer_receipt_recorded', $receipt, after: ['customer_id' => $customer->id, 'store_id' => $collectionStore->id, 'currency_code' => $payload['currency_code'], 'amount' => $amount, 'allocation_count' => count($normalized), 'unallocated_amount' => bcsub($amount, $this->sum($normalized), 4)], branchId: $collectionStore->branch_id, storeId: $collectionStore->id, metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey]);

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
