<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Reporting\Jobs\GenerateReportExportJob;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Queries\CentralExportSnapshot;
use App\Modules\Reporting\Queries\ReportSnapshot;
use App\Modules\Reporting\Queries\InventoryReport;
use App\Modules\Reporting\Queries\SalesReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateExportJobAction
{
    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters, string $format = 'xlsx'): ExportJob
    {
        $format = strtolower(trim($format));
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422, __('Only CSV, PDF and Excel exports are supported.'));
        Gate::forUser($user)->authorize('dashboard_reports.export_'.($format === 'csv' ? 'xlsx' : $format));

        unset($filters['page'], $filters['per_page']);
        $locale = app()->getLocale();
        $locale = in_array($locale, config('app.supported_locales', ['ar', 'en', 'ar-EG']), true) ? $locale : (string) config('app.fallback_locale', 'en');
        $dataset = filled($filters['dataset'] ?? null) ? (string) $filters['dataset'] : null;
        $salesDataset = $dataset !== null && in_array($dataset, SalesReport::EXPORT_KEYS, true);
        $inventoryDataset = $dataset !== null && in_array($dataset, InventoryReport::EXPORT_KEYS, true);
        $snapshot = $inventoryDataset
            ? app(InventoryReport::class)->export($user, $dataset, $filters)
            : ($salesDataset
            ? app(SalesReport::class)->export($user, $dataset, $filters)
            : ($dataset ? app(CentralExportSnapshot::class)->execute($user, $dataset, $filters) : app(ReportSnapshot::class)->execute($user, $filters, true)));
        $fingerprint = $inventoryDataset
            ? app(InventoryReport::class)->fingerprint($snapshot)
            : ($salesDataset
            ? app(SalesReport::class)->fingerprint($snapshot)
            : ($dataset ? app(CentralExportSnapshot::class)->fingerprint($snapshot) : app(ReportSnapshot::class)->fingerprint($snapshot)));
        $requestHash = hash('sha256', json_encode([$user->id, $dataset, $format, $locale, $snapshot['filters']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $created = false;
        $job = DB::transaction(function () use ($user, $snapshot, $dataset, $format, $locale, $fingerprint, $requestHash, &$created): ExportJob {
            $existing = ExportJob::query()->where('requested_by', $user->id)->where('request_hash', $requestHash)->whereIn('status', ['queued', 'running'])->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $created = true;

            return ExportJob::query()->create([
                'report_key' => $dataset ?? ($snapshot['filters']['module'] ?? 'dashboard'),
                'format' => $format,
                'status' => 'queued',
                'requested_by' => $user->id,
                'branch_id' => $snapshot['filters']['branch_id'],
                'store_id' => $snapshot['filters']['store_id'],
                'filters' => [...$snapshot['filters'], 'locale' => $locale],
                'snapshot_hash' => $fingerprint,
                'request_hash' => $requestHash,
                'expires_at' => now()->addDays(7),
            ]);
        });

        if ($created) {
            app(RecordAuditEvent::class)->execute(
                category: 'reporting',
                event: 'export_requested',
                source: $job,
                after: ['status' => 'queued', 'format' => $format],
                branchId: $job->branch_id,
                storeId: $job->store_id,
                metadata: ['filters' => $job->filters, 'expires_at' => $job->expires_at?->toIso8601String()],
            );
        }

        if ($created) {
            GenerateReportExportJob::dispatch($job->id)->onQueue('reports');
        }

        return $job->fresh();
    }
}
