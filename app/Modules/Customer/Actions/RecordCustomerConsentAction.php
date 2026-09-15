<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordCustomerConsentAction
{
    public function execute(
        User $actor,
        Customer $customer,
        Store $store,
        string $purpose,
        string $status,
        string $source,
        string $idempotencyKey,
    ): CustomerConsent {
        Gate::forUser($actor)->authorize($source === 'profile_create' || $source === 'pos' ? 'customers.create' : 'customers.sensitive');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless($customer->status === 'active', 404);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);

        $purpose = trim($purpose);
        $status = trim($status);
        if (! in_array($status, ['granted', 'withdrawn', 'denied'], true)) {
            throw new InvalidArgumentException(__('Consent status is invalid.'));
        }

        $purposes = CustomerPolicy::allowedPurposes('customer.consent.purpose')['value'];
        if (! in_array($purpose, $purposes, true)) {
            throw new InvalidArgumentException(__('This consent purpose is not allowed by the configured policy.'));
        }

        $snapshot = CustomerPolicy::consentSnapshot();

        try {
            return DB::transaction(function () use ($actor, $customer, $store, $purpose, $status, $source, $idempotencyKey, $snapshot): CustomerConsent {
                $existing = CustomerConsent::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    $same = (int) $existing->customer_id === (int) $customer->id
                        && $existing->purpose === $purpose
                        && $existing->status === $status;
                    if (! $same) {
                        throw new InvalidArgumentException(__('This consent idempotency key was already used with a different payload.'));
                    }

                    return $existing;
                }

                $previous = CustomerConsent::query()
                    ->where('customer_id', $customer->id)
                    ->where('purpose', $purpose)
                    ->latest('id')
                    ->first();

                $consent = CustomerConsent::query()->create([
                    'customer_id' => $customer->id,
                    'purpose' => $purpose,
                    'status' => $status,
                    'captured_at' => now(),
                    'captured_by' => $actor->id,
                    'source' => $source,
                    'wording_version' => $snapshot['version'],
                    'wording_text' => $snapshot['text'],
                    'retention_until' => $snapshot['retention_until'],
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value',
                    event: 'customer_consent_captured',
                    source: $consent,
                    before: $previous?->only(['purpose', 'status', 'wording_version']),
                    after: $consent->only(['customer_id', 'purpose', 'status', 'wording_version', 'retention_until']),
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    reasonText: $status === 'withdrawn' ? __('Customer consent withdrawn.') : null,
                    metadata: ['idempotency_key' => $idempotencyKey, 'source' => $source, 'actor_id' => $actor->id],
                );

                return $consent;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = CustomerConsent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && (int) $existing->customer_id === (int) $customer->id && $existing->purpose === $purpose && $existing->status === $status) {
                return $existing;
            }

            throw $exception;
        }
    }
}
