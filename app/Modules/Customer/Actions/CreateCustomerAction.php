<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Support\CustomerIdentity;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Customer\Support\ResidenceGeography;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreateCustomerAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Store $store, array $data): Customer
    {
        Gate::forUser($actor)->authorize('customers.create');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);

        CustomerPolicy::phoneNormalization();
        [$phone, $secondaryPhone] = CustomerIdentity::normalizedPhones((string) ($data['phone'] ?? ''), $data['secondary_phone'] ?? null);
        [$governorateId, $cityId] = ResidenceGeography::validate((int) $store->company_id, $data['governorate_id'] ?? null, $data['city_id'] ?? null);
        [$firstNameAr, $lastNameAr, $firstNameEn, $lastNameEn, $nameAr, $nameEn] = self::normaliseNames($data);
        $email = filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null;
        if ($firstNameAr === '' || $lastNameAr === '') {
            throw new InvalidArgumentException(__('Customer Arabic first and last names are required.'));
        }
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A customer idempotency key is required.'));
        }
        $consents = $data['consents'] ?? [];
        if (! is_array($consents) || $consents === []) {
            throw new InvalidArgumentException(__('At least one customer consent record is required.'));
        }

        $customerGroupId = filled($data['customer_group_id'] ?? null) ? (int) $data['customer_group_id'] : null;

        try {
            return DB::transaction(function () use ($actor, $store, $data, $phone, $secondaryPhone, $governorateId, $cityId, $firstNameAr, $lastNameAr, $firstNameEn, $lastNameEn, $nameAr, $nameEn, $email, $idempotencyKey, $consents, $customerGroupId): Customer {
                $existingByKey = Customer::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existingByKey !== null) {
                    if ($existingByKey->phone_normalized !== $phone || $existingByKey->name_ar !== $nameAr || $existingByKey->name_en !== $nameEn) {
                        throw new InvalidArgumentException(__('This customer idempotency key was already used with a different payload.'));
                    }

                    return $existingByKey;
                }

                CustomerIdentity::assertAvailable($phone, $secondaryPhone);

                if ($email !== null && Customer::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw new InvalidArgumentException(__('A customer already exists for this email address. Review the existing profile instead of creating a duplicate.'));
                }

                $customerGroup = $customerGroupId === null
                    ? null
                    : CustomerGroup::query()->forCompany((int) $store->company_id)->active()->lockForUpdate()->find($customerGroupId);
                if ($customerGroupId !== null && $customerGroup === null) {
                    throw new InvalidArgumentException(__('The selected customer group is not available in this company.'));
                }

                $customer = Customer::query()->create([
                    'phone_normalized' => $phone,
                    'phone_display' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                    'first_name_ar' => $firstNameAr,
                    'last_name_ar' => $lastNameAr,
                    'first_name_en' => $firstNameEn !== '' ? $firstNameEn : null,
                    'last_name_en' => $lastNameEn !== '' ? $lastNameEn : null,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'email' => $email,
                    'secondary_phone' => $secondaryPhone,
                    'secondary_phone_normalized' => $secondaryPhone,
                    'address_ar' => filled($data['address_ar'] ?? null) ? trim((string) $data['address_ar']) : null,
                    'address_en' => filled($data['address_en'] ?? null) ? trim((string) $data['address_en']) : null,
                    'status' => 'active',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_branch_id' => $store->branch_id,
                    'created_store_id' => $store->id,
                    'customer_group_id' => $customerGroup?->id,
                    'customer_type' => $this->customerType($data['customer_type'] ?? null),
                    'credit_limit' => $this->creditLimit($data['credit_limit'] ?? null),
                    'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                    'governorate_id' => $governorateId,
                    'city_id' => $cityId,
                    'idempotency_key' => $idempotencyKey,
                    'lock_version' => 1,
                ]);

                CustomerScope::query()->create([
                    'customer_id' => $customer->id,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'created_by' => $actor->id,
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value',
                    event: 'customer_created',
                    source: $customer,
                    after: $customer->only(['public_id', 'phone_normalized', 'name_ar', 'name_en', 'email', 'customer_group_id', 'status', 'lock_version']),
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    metadata: ['idempotency_key' => $idempotencyKey, 'purpose_scoped' => true, 'actor_id' => $actor->id],
                );

                $consentAction = app(RecordCustomerConsentAction::class);
                foreach (array_values($consents) as $index => $consent) {
                    if (! is_array($consent)) {
                        throw new InvalidArgumentException(__('Each customer consent value must be an object.'));
                    }
                    $consentAction->execute(
                        $actor,
                        $customer,
                        $store,
                        (string) ($consent['purpose'] ?? ''),
                        (string) ($consent['status'] ?? 'granted'),
                        (string) ($consent['source'] ?? 'profile_create'),
                        $idempotencyKey.':CONSENT:'.$index,
                    );
                }

                return $customer->fresh(['scopes', 'consents']);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Customer::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && $existing->phone_normalized === $phone && $existing->name_ar === $nameAr && $existing->name_en === $nameEn) {
                return $existing;
            }

            $duplicate = $phone === null ? null : Customer::query()->where('phone_normalized', $phone)->first();
            if ($duplicate !== null) {
                throw new InvalidArgumentException(__('A customer already exists for this phone number. Review the existing profile instead of creating a duplicate.'));
            }

            throw $exception;
        }
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
    private static function normaliseNames(array $data): array
    {
        $legacyAr = trim((string) ($data['name_ar'] ?? ''));
        $legacyEn = trim((string) ($data['name_en'] ?? ''));
        $firstAr = trim((string) ($data['first_name_ar'] ?? ''));
        $lastAr = trim((string) ($data['last_name_ar'] ?? ''));
        if ($firstAr === '' && $lastAr === '' && $legacyAr !== '') {
            [$firstAr, $lastAr] = array_pad(preg_split('/\s+/u', $legacyAr, 2) ?: [$legacyAr], 2, '');
        }
        $firstEn = trim((string) ($data['first_name_en'] ?? ''));
        $lastEn = trim((string) ($data['last_name_en'] ?? ''));
        if ($firstEn === '' && $lastEn === '' && $legacyEn !== '') {
            [$firstEn, $lastEn] = array_pad(preg_split('/\s+/u', $legacyEn, 2) ?: [$legacyEn], 2, '');
        }
        $nameAr = trim($firstAr.' '.$lastAr);
        $englishFull = trim($firstEn.' '.$lastEn);

        return [$firstAr, $lastAr, $firstEn, $lastEn, $nameAr, $englishFull !== '' ? $englishFull : $nameAr];
    }
}
