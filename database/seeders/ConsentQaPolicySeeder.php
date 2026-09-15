<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use LogicException;

/** Local/disposable QA only; never call from DatabaseSeeder or Production. */
final class ConsentQaPolicySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! Auth::check()) {
            throw new LogicException('Consent QA policy fixture requires an authenticated local/testing actor.');
        }

        $policies = [
            'customer.phone_normalization' => 'digits_only',
            'customer.consent.purpose' => '["service_delivery"]',
            'customer.consent.wording' => '{"version":"QA-CONSENT-V1","text":"QA-only consent wording for disposable local verification."}',
            'customer.consent.retention' => '{"days":30}',
            'customer.children.purpose_scope' => '{"purpose":"child_profile","consent_required":true}',
        ];

        foreach ($policies as $key => $value) {
            if (! CustomerPolicySettingVersion::query()->where('key', $key)->exists()) {
                app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'QA-only disposable consent fixture; not owner/legal approval.');
            }
        }
    }
}
