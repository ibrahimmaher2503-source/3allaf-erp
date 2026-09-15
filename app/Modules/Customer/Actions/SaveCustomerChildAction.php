<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerChild;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveCustomerChildAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Customer $customer, Store $store, array $data, ?CustomerChild $child = null, string $source = 'profile'): CustomerChild
    {
        Gate::forUser($actor)->authorize($source === 'profile_create' ? 'customers.create' : 'customers.sensitive');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);
        abort_unless($customer->status === 'active', 404);

        $allowedPurposes = CustomerPolicy::childPurposes()['value'];
        $purpose = trim((string) ($data['purpose'] ?? ''))
            ?: ($child?->purpose ?: (string) ($allowedPurposes[0] ?? ''));
        if (! in_array($purpose, $allowedPurposes, true)) {
            throw new InvalidArgumentException(__('This child-data purpose is not allowed by the configured policy.'));
        }
        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        $nameEn = filled($data['name_en'] ?? null) ? trim((string) $data['name_en']) : null;
        if ($nameAr === '') {
            throw new InvalidArgumentException(__('Child Arabic name is required.'));
        }
        $consent = CustomerPolicy::consentSnapshot();

        return DB::transaction(function () use ($actor, $customer, $store, $data, $child, $purpose, $nameAr, $nameEn, $consent): CustomerChild {
            $before = $child?->only(['name_ar', 'name_en', 'birth_date', 'purpose', 'status']);
            if ($child !== null) {
                $locked = CustomerChild::query()->where('customer_id', $customer->id)->lockForUpdate()->findOrFail($child->id);
                $locked->mutateProfile([
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'birth_date' => $data['birth_date'] ?? null,
                    'purpose' => $purpose,
                    'consent_status' => 'granted',
                    'consent_wording_version' => $consent['version'],
                    'consent_wording_text' => $consent['text'],
                    'updated_by' => $actor->id,
                    'lock_version' => ((int) $locked->lock_version) + 1,
                ]);
                $saved = $locked->fresh();
            } else {
                $saved = CustomerChild::query()->create([
                    'customer_id' => $customer->id,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'birth_date' => $data['birth_date'] ?? null,
                    'purpose' => $purpose,
                    'consent_status' => 'granted',
                    'consent_wording_version' => $consent['version'],
                    'consent_wording_text' => $consent['text'],
                    'status' => 'active',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_branch_id' => $store->branch_id,
                    'created_store_id' => $store->id,
                    'lock_version' => 1,
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: $child === null ? 'customer_child_created' : 'customer_child_updated',
                source: $saved,
                before: $before,
                after: $saved->only(['customer_id', 'name_ar', 'name_en', 'birth_date', 'purpose', 'consent_status', 'lock_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'purpose_scoped' => true],
            );

            return $saved;
        });
    }

    public function deactivate(User $actor, Customer $customer, Store $store, CustomerChild $child): CustomerChild
    {
        Gate::forUser($actor)->authorize('customers.sensitive');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless((int) $child->customer_id === (int) $customer->id, 404);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);

        return DB::transaction(function () use ($actor, $customer, $store, $child): CustomerChild {
            $locked = CustomerChild::query()->where('customer_id', $customer->id)->lockForUpdate()->findOrFail($child->id);
            $before = $locked->only(['status', 'lock_version']);
            $locked->mutateProfile(['status' => 'inactive', 'updated_by' => $actor->id, 'lock_version' => ((int) $locked->lock_version) + 1]);
            $saved = $locked->fresh();

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'customer_child_deactivated',
                source: $saved,
                before: $before,
                after: $saved->only(['customer_id', 'status', 'lock_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'purpose_scoped' => true],
            );

            return $saved;
        });
    }
}
