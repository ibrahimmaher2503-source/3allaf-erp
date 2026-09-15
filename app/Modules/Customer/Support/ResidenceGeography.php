<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use InvalidArgumentException;

final class ResidenceGeography
{
    /** @return array{0:int,1:int} */
    public static function validate(int $companyId, mixed $governorateId, mixed $cityId): array
    {
        if (! filled($governorateId) || ! filled($cityId)) {
            throw new InvalidArgumentException(__('Governorate and city/locality are required for residence data.'));
        }
        $governorate = Governorate::query()->active()->find((int) $governorateId);
        $city = City::query()->visibleToCompany($companyId)->active()->find((int) $cityId);
        if ($governorate === null || $city === null || (int) $city->governorate_id !== (int) $governorate->id) {
            throw new InvalidArgumentException(__('The selected city/locality is not active in the selected governorate.'));
        }

        return [(int) $governorate->id, (int) $city->id];
    }
}
