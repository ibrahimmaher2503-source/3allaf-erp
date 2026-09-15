<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveCityAction
{
    /** @param array<string, mixed> $data */
    public function execute(int $companyId, array $data, ?City $city = null): City
    {
        Gate::authorize('company_settings.edit');
        if ($city?->company_id === null && $city !== null) {
            throw new InvalidArgumentException(__('Shared reference cities cannot be edited; add a company city or deactivate usage through customer data.'));
        }
        if ($city !== null && (int) $city->company_id !== $companyId) {
            abort(404);
        }
        $governorate = Governorate::query()->active()->find((int) ($data['governorate_id'] ?? 0));
        if ($governorate === null) {
            throw new InvalidArgumentException(__('Select an active governorate.'));
        }
        $nameAr = City::normalizeName((string) ($data['name_ar'] ?? ''));
        $nameEn = City::normalizeName((string) ($data['name_en'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $duplicate = City::query()->visibleToCompany($companyId)
            ->where('governorate_id', $governorate->id)
            ->when($city !== null, fn ($query) => $query->whereKeyNot($city->id))
            ->where(fn ($query) => $query->where('code', $code)->orWhere('name_ar_normalized', $nameAr)->orWhere('name_en_normalized', $nameEn))
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException(__('A city/locality with the same code or normalized name already exists in this governorate.'));
        }

        return DB::transaction(function () use ($companyId, $data, $city, $governorate, $code): City {
            $before = $city?->only(['code', 'name_ar', 'name_en', 'sort_order', 'status']);
            $attributes = [
                'governorate_id' => $governorate->id, 'company_id' => $companyId,
                'code' => $code,
                'name_ar' => trim((string) $data['name_ar']), 'name_en' => trim((string) $data['name_en']),
                'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
                'status' => in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? $data['status'] ?? 'active' : 'active',
                'updated_by' => Auth::id(),
            ];
            if ($city === null) {
                $city = City::query()->create($attributes + ['created_by' => Auth::id(), 'lock_version' => 1]);
            } else {
                $city->update($attributes + ['lock_version' => $city->lock_version + 1]);
            }
            app(RecordAuditEvent::class)->execute('master_data', $before ? 'city_updated' : 'city_created', $city, $before, $city->fresh()->only(['code', 'name_ar', 'name_en', 'sort_order', 'status']), metadata: ['company_id' => $companyId]);

            return $city->fresh('governorate');
        });
    }
}
