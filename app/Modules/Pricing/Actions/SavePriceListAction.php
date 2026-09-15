<?php

namespace App\Modules\Pricing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SavePriceListAction
{
    public function execute(array $data, ?PriceList $list = null): PriceList
    {
        Gate::authorize('pricing_lists.manage');
        return DB::transaction(function () use ($data, $list): PriceList {
            $company = Company::query()->where('status', 'active')->lockForUpdate()->firstOrFail();
            $list = $list?->newQuery()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($list->id);
            if ($list?->isBase()) throw ValidationException::withMessages(['list' => __('Base Price List 0 cannot be edited, disabled, renumbered, or deleted.')]);
            $number = $list?->list_number ?? ((int) PriceList::query()->where('company_id', $company->id)->lockForUpdate()->max('list_number') + 1);
            $attributes = ['company_id' => $company->id, 'list_number' => $number, 'code' => 'PL-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'name_ar' => trim($data['name_ar']), 'name_en' => trim($data['name_en']), 'percentage_increase' => $data['percentage_increase'], 'status' => $data['status'], 'effective_from' => $data['effective_from'] ?: null, 'effective_to' => $data['effective_to'] ?: null, 'notes' => $data['notes'] ?: null, 'updated_by' => auth()->id()];
            $before = $list?->toArray();
            if ($list) $list->update($attributes); else { $attributes['created_by'] = auth()->id(); $list = PriceList::query()->create($attributes); }
            app(RecordAuditEvent::class)->execute('pricing', $before ? 'update_price_list' : 'create_price_list', $list, $before, $list->fresh()->toArray());
            return $list;
        });
    }
}
