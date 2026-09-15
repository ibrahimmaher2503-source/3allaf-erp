<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Retail\Models\RetailReturn;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PosReturnReceiptProfile
{
    /** @return array{printer: ?PrinterConfiguration, format: string, paper_size: string, language: string, browser_fallback: bool, fallback_message: string} */
    public function resolve(User $actor, RetailReturn $return): array
    {
        Gate::forUser($actor)->authorize('returns.print');
        $return->loadMissing('store.company');
        if ($return->status !== 'completed' || $return->store === null || ! RetailReturn::query()->visibleTo($actor)->whereKey($return->id)->exists()) {
            throw ValidationException::withMessages(['refund' => __('Only a completed refund in your authorized scope can be printed.')]);
        }

        $locale = app()->getLocale();
        $printer = PrinterConfiguration::query()
            ->visibleTo($actor)
            ->where('status', 'active')
            ->where(function ($query) use ($return): void {
                $query->where(function ($exact) use ($return): void {
                    $exact->where('branch_id', $return->branch_id)->where('store_id', $return->store_id);
                })->orWhere(function ($branch) use ($return): void {
                    $branch->where('branch_id', $return->branch_id)->whereNull('store_id');
                })->orWhere(function ($global): void {
                    $global->whereNull('branch_id')->whereNull('store_id');
                });
            })
            ->whereHas('printTemplate', function ($template) use ($return, $locale): void {
                $template->where('company_id', $return->store->company_id)
                    ->where('document_type', 'sales_return')
                    ->where('status', 'active')
                    ->whereColumn('print_templates.paper_size', 'printer_configurations.paper_size')
                    ->whereIn('language', [$locale, 'bilingual']);
            })
            ->with('printTemplate')
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 WHEN branch_id = ? AND store_id IS NULL THEN 1 ELSE 2 END', [$return->store_id, $return->branch_id])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $paper = (string) ($printer?->paper_size ?: '80mm');

        return [
            'printer' => $printer,
            'format' => in_array($paper, ['58mm', '80mm'], true) || $printer?->printer_type === 'thermal' ? 'thermal' : 'a4',
            'paper_size' => $paper,
            'language' => (string) ($printer?->printTemplate?->language ?: $locale),
            'browser_fallback' => true,
            'fallback_message' => $printer === null
                ? __('No compatible refund printer is assigned to this outlet. Use the secure browser print dialog.')
                : __('Direct printing is unavailable in this browser. The configured refund document is ready in the secure browser print dialog.'),
        ];
    }
}
