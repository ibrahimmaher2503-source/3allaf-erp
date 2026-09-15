<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\Gate;

final class PosReceiptProfile
{
    /** @return array{printer: ?PrinterConfiguration, format: string, paper_size: string, language: string, browser_fallback: bool, fallback_message: string} */
    public function resolve(User $actor, Sale $sale): array
    {
        Gate::forUser($actor)->authorize('pos_sales.print');
        $sale->loadMissing('store.company');
        abort_unless($sale->status === 'approved' && $sale->store !== null && Sale::query()->visibleTo($actor)->whereKey($sale->id)->exists(), 404);

        $locale = app()->getLocale();
        $printer = PrinterConfiguration::query()
            ->visibleTo($actor)
            ->where('status', 'active')
            ->where(function ($query) use ($sale): void {
                $query->where(function ($exact) use ($sale): void {
                    $exact->where('branch_id', $sale->branch_id)->where('store_id', $sale->store_id);
                })->orWhere(function ($branch) use ($sale): void {
                    $branch->where('branch_id', $sale->branch_id)->whereNull('store_id');
                })->orWhere(function ($global): void {
                    $global->whereNull('branch_id')->whereNull('store_id');
                });
            })
            ->whereHas('printTemplate', function ($template) use ($sale, $locale): void {
                $template->where('company_id', $sale->store->company_id)
                    ->where('document_type', 'sales_invoice')
                    ->where('status', 'active')
                    ->whereColumn('print_templates.paper_size', 'printer_configurations.paper_size')
                    ->whereIn('language', [$locale, 'bilingual']);
            })
            ->with('printTemplate')
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 WHEN branch_id = ? AND store_id IS NULL THEN 1 ELSE 2 END', [$sale->store_id, $sale->branch_id])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $paper = (string) ($printer?->paper_size ?: '80mm');
        $format = in_array($paper, ['58mm', '80mm'], true) || $printer?->printer_type === 'thermal' ? 'thermal' : 'a4';
        $browserFallback = true;

        return [
            'printer' => $printer,
            'format' => $format,
            'paper_size' => $paper,
            'language' => (string) ($printer?->printTemplate?->language ?: $locale),
            'browser_fallback' => $browserFallback,
            'fallback_message' => $printer === null
                ? __('No compatible receipt printer is assigned to this outlet. Use the secure browser print dialog.')
                : __('Direct printing is unavailable in this browser. The configured receipt is ready in the secure browser print dialog.'),
        ];
    }
}
