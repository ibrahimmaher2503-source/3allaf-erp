<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Support\CustomerIdentity;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\ResidenceGeography;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateCustomerAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Customer $customer, Store $store, array $data): Customer
    {
        Gate::forUser($actor)->authorize('customers.edit');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);
        abort_unless($customer->status === 'active', 404);

        CustomerPolicy::phoneNormalization();
        $phoneDisplay = trim((string) ($data['phone'] ?? $customer->phone_display));
        [$phone, $secondaryPhone] = CustomerIdentity::normalizedPhones($phoneDisplay, $data['secondary_phone'] ?? $customer->secondary_phone_normalized ?? $customer->secondary_phone);
        $residenceFieldsSubmitted = array_key_exists('governorate_id', $data) || array_key_exists('city_id', $data);
        $residenceChanging = $residenceFieldsSubmitted
            && (filled($data['governorate_id'] ?? null) || filled($data['city_id'] ?? null) || $customer->governorate_id !== null || $customer->city_id !== null);
        [$governorateId, $cityId] = $residenceChanging
            ? ResidenceGeography::validate((int) $store->company_id, $data['governorate_id'] ?? null, $data['city_id'] ?? null)
            : [$customer->governorate_id, $customer->city_id];
        [$firstNameAr, $lastNameAr, $firstNameEn, $lastNameEn, $nameAr, $nameEn] = self::normaliseNames($data, $customer);
        if ($firstNameAr === '' || $lastNameAr === '') {
            throw new InvalidArgumentException(__('Customer Arabic first and last names are required.'));
        }
        $sensitiveFields = ['email', 'address_ar', 'address_en'];
        $sensitiveChangeRequested = collect($sensitiveFields)->contains(fn (string $field): bool => array_key_exists($field, $data));
        abort_unless(! $sensitiveChangeRequested || $actor->can('customers.sensitive'), 403);

        $customerGroupId = array_key_exists('customer_group_id', $data)
            ? (filled($data['customer_group_id']) ? (int) $data['customer_group_id'] : null)
            : (($customer->customer_group_id !== null) ? (int) $customer->customer_group_id : null);

        return DB::transaction(function () use ($actor, $customer, $store, $data, $phone, $phoneDisplay, $secondaryPhone, $governorateId, $cityId, $firstNameAr, $lastNameAr, $firstNameEn, $lastNameEn, $nameAr, $nameEn, $customerGroupId): Customer {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($locked->status !== 'active') {
                throw new InvalidArgumentException(__('This customer profile is no longer editable.'));
            }
            CustomerIdentity::assertAvailable($phone, $secondaryPhone, (int) $locked->id);

            $email = array_key_exists('email', $data) && filled($data['email']) ? strtolower(trim((string) $data['email'])) : null;
            if ($email !== null && Customer::query()->where('id', '<>', $locked->id)->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw new InvalidArgumentException(__('A customer already exists for this email address. Review the existing profile instead of creating a duplicate.'));
            }

            $customerGroup = $customerGroupId === null
                ? null
                : CustomerGroup::query()->forCompany((int) $store->company_id)->active()->lockForUpdate()->find($customerGroupId);
            if ($customerGroupId !== null && $customerGroup === null) {
                throw new InvalidArgumentException(__('The selected customer group is not available in this company.'));
            }

            $before = $locked->only(['phone_display', 'phone_normalized', 'name_ar', 'name_en', 'email', 'secondary_phone', 'address_ar', 'address_en', 'customer_group_id', 'lock_version']);
            $locked->mutateMaster([
                'phone_normalized' => $phone,
                'phone_display' => $phoneDisplay,
                'first_name_ar' => $firstNameAr,
                'last_name_ar' => $lastNameAr,
                'first_name_en' => $firstNameEn !== '' ? $firstNameEn : null,
                'last_name_en' => $lastNameEn !== '' ? $lastNameEn : null,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'email' => array_key_exists('email', $data) ? (filled($data['email']) ? strtolower(trim((string) $data['email'])) : null) : $locked->email,
                'secondary_phone' => $secondaryPhone,
                'secondary_phone_normalized' => $secondaryPhone,
                'address_ar' => array_key_exists('address_ar', $data) ? (filled($data['address_ar']) ? trim((string) $data['address_ar']) : null) : $locked->address_ar,
                'address_en' => array_key_exists('address_en', $data) ? (filled($data['address_en']) ? trim((string) $data['address_en']) : null) : $locked->address_en,
                'customer_group_id' => $customerGroup?->id,
                'customer_type' => array_key_exists('customer_type', $data) ? $this->customerType($data['customer_type']) : $locked->customer_type,
                'credit_limit' => array_key_exists('credit_limit', $data) ? $this->creditLimit($data['credit_limit']) : $locked->credit_limit,
                'notes' => array_key_exists('notes', $data) ? (filled($data['notes']) ? trim((string) $data['notes']) : null) : $locked->notes,
                'governorate_id' => $governorateId,
                'city_id' => $cityId,
                'updated_by' => $actor->id,
                'lock_version' => ((int) $locked->lock_version) + 1,
            ]);

            $saved = $locked->fresh();
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'customer_updated',
                source: $saved,
                before: $before,
                after: $saved->only(['phone_display', 'phone_normalized', 'name_ar', 'name_en', 'email', 'secondary_phone', 'address_ar', 'address_en', 'customer_group_id', 'lock_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'phone_duplicate_checked' => true],
            );

            return $saved;
        });
    }

    private function customerType(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $value = (string) $value;
        if (! in_array($value, ['cash', 'credit', 'both'], true)) {
            throw new InvalidArgumentException(__('The selected customer credit type is not supported.'));
        }

        return $value;
    }

    private function creditLimit(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $value = trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException(__('The customer credit limit must be a non-negative decimal.'));
        }

        return bcadd($value, '0', 4);
    }

    /** @param array<string, mixed> $data @return array{0:string,1:string,2:string,3:string,4:string,5:string} */
    private static function normaliseNames(array $data, Customer $customer): array
    {
        $firstAr = trim((string) ($data['first_name_ar'] ?? $customer->first_name_ar ?? ''));
        $lastAr = trim((string) ($data['last_name_ar'] ?? $customer->last_name_ar ?? ''));
        $legacyAr = trim((string) ($data['name_ar'] ?? $customer->name_ar ?? ''));
        if ($firstAr === '' && $lastAr === '' && $legacyAr !== '') {
            [$firstAr, $lastAr] = array_pad(preg_split('/\s+/u', $legacyAr, 2) ?: [$legacyAr], 2, '');
        }
        $firstEn = trim((string) ($data['first_name_en'] ?? $customer->first_name_en ?? ''));
        $lastEn = trim((string) ($data['last_name_en'] ?? $customer->last_name_en ?? ''));
        $legacyEn = trim((string) ($data['name_en'] ?? $customer->name_en ?? ''));
        if ($firstEn === '' && $lastEn === '' && $legacyEn !== '') {
            [$firstEn, $lastEn] = array_pad(preg_split('/\s+/u', $legacyEn, 2) ?: [$legacyEn], 2, '');
        }
        $nameAr = trim($firstAr.' '.$lastAr);
        $englishFull = trim($firstEn.' '.$lastEn);

        return [$firstAr, $lastAr, $firstEn, $lastEn, $nameAr, $englishFull !== '' ? $englishFull : $nameAr];
    }
}
