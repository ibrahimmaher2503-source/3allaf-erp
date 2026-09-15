<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AuthorizedCompanyContext
{
    /** @return Collection<int, Company> */
    public function companies(User $actor): Collection
    {
        return Company::query()
            ->whereIn('id', Store::query()->visibleTo($actor)->where('status', 'active')->select('company_id'))
            ->where('status', 'active')->orderBy('code')->get();
    }

    public function resolve(User $actor, mixed $requested = null): Company
    {
        $companies = $this->companies($actor);
        if ($companies->isEmpty()) {
            throw ValidationException::withMessages(['company_id' => __('No active company is available in your authorized scope.')]);
        }
        $companyId = filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($companyId !== false) {
            $company = $companies->firstWhere('id', $companyId);
            if ($company === null) {
                throw ValidationException::withMessages(['company_id' => __('The selected company is outside your authorized scope.')]);
            }

            return $company;
        }
        $workCompanyId = app(WorkContext::class)->selected($actor)?->company_id;
        if ($workCompanyId !== null && ($company = $companies->firstWhere('id', (int) $workCompanyId)) !== null) {
            return $company;
        }
        if ($companies->count() !== 1) {
            throw ValidationException::withMessages(['company_id' => __('Select one authorized company before continuing.')]);
        }

        return $companies->first();
    }
}
