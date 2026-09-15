<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Support\DecimalMoney;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Saves an explicit versioned Local/Dev POS financial configuration value. */
final class SavePosFinancialSettingAction
{
    public function execute(string $key, ?string $value, ?string $notes = null): PosFinancialSettingVersion
    {
        Gate::authorize('company_settings.edit');

        if (! array_key_exists($key, PosFinancialSettingRegistry::all())) {
            throw ValidationException::withMessages(['key' => __('The POS financial setting is not supported.')]);
        }

        $value = $value === null ? null : trim($value);
        if ($value !== null && $value !== '') {
            try {
                $value = DecimalMoney::normalize($value, 4);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['value' => __('A decimal value must be a valid number.')]);
            }

            if (bccomp($value, '0', 4) <= 0) {
                throw ValidationException::withMessages(['value' => __('The configured value must be greater than zero.')]);
            }
            if ($key === PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION
                && bccomp($value, DecimalMoney::round($value, 2), 4) !== 0) {
                throw ValidationException::withMessages(['value' => __('The cash rounding denomination cannot use more than two decimal places.')]);
            }
        } else {
            $value = null;
        }

        return DB::transaction(function () use ($key, $value, $notes): PosFinancialSettingVersion {
            $latest = PosFinancialSettingVersion::query()->where('key', $key)->lockForUpdate()->orderByDesc('version')->first();
            $nextVersion = ((int) ($latest?->getAttribute('version') ?? 0)) + 1;

            $setting = PosFinancialSettingVersion::query()->create([
                'key' => $key,
                'value' => $value,
                'version' => $nextVersion,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'created_by' => auth()->id(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'pos_financial_setting_version_created',
                source: $setting,
                before: $latest?->only(['key', 'value', 'version']),
                after: $setting->only(['key', 'value', 'version']),
                reasonText: $setting->notes,
                metadata: ['configuration_scope' => 'local_dev'],
            );

            return $setting;
        });
    }
}
