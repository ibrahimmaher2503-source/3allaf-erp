<?php

namespace App\Modules\Pricing\Actions;

use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;

final class EnsureBasePriceList
{
    public function execute(Company|int $company): PriceList
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        return PriceList::query()->firstOrCreate(['company_id' => $companyId, 'list_number' => 0], ['code' => 'PL-0000', 'name_ar' => 'قائمة المستهلك الأساسية', 'name_en' => 'Base Consumer Price List', 'percentage_increase' => 0, 'status' => 'active']);
    }
}
