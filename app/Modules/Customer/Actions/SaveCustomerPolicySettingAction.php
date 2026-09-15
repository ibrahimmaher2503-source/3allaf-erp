<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Support\CustomerPolicySettingRegistry;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveCustomerPolicySettingAction
{
    public function execute(string $key, ?string $value, ?string $notes): CustomerPolicySettingVersion
    {
        return $this->executeMany([$key => $value], $notes)[0];
    }

    /**
     * @param array<string, ?string> $settings
     * @return list<CustomerPolicySettingVersion>
     */
    public function executeMany(array $settings, ?string $notes): array
    {
        Gate::authorize('company_settings.edit');

        if ($settings === []) {
            throw ValidationException::withMessages(['settings' => 'At least one customer policy setting is required.']);
        }

        $normalizedNotes = $notes !== null ? trim($notes) : null;
        $normalizedSettings = [];
        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, CustomerPolicySettingRegistry::all())) {
                throw ValidationException::withMessages(['key' => 'The selected customer policy setting is not allowed.']);
            }

            $normalizedSettings[$key] = $value !== null ? trim($value) : null;
        }

        return DB::transaction(function () use ($normalizedSettings, $normalizedNotes): array {
            $saved = [];
            foreach ($normalizedSettings as $key => $value) {
                $latest = CustomerPolicySettingVersion::query()
                    ->where('key', $key)
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                $setting = CustomerPolicySettingVersion::query()->create([
                    'key' => $key,
                    'value' => $value !== '' ? $value : null,
                    'value_type' => 'text',
                    'version' => ($latest->version ?? 0) + 1,
                    'created_by' => Auth::id(),
                    'notes' => $normalizedNotes !== '' ? $normalizedNotes : null,
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'customer_policy_settings',
                    event: 'create_customer_policy_setting_version',
                    source: $setting,
                    before: $latest?->only(['key', 'value', 'value_type', 'version', 'notes']),
                    after: $setting->only(['key', 'value', 'value_type', 'version', 'notes']),
                    metadata: ['approval_state' => 'owner_approval_required', 'setting_key' => $key],
                );

                $saved[] = $setting;
            }

            return $saved;
        });
    }
}
