<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Jobs;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Queries\CentralExportSnapshot;
use App\Modules\Reporting\Queries\ReportSnapshot;
use App\Modules\Reporting\Queries\InventoryReport;
use App\Modules\Reporting\Queries\SalesReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;
use RuntimeException;
use Throwable;

final class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $exportJobId) {}

    public function handle(ReportSnapshot $reports, CentralExportSnapshot $central, SalesReport $salesReports, InventoryReport $inventoryReports): void
    {
        $job = ExportJob::query()->find($this->exportJobId);
        if ($job === null || in_array($job->status, ['ready', 'failed'], true)) {
            return;
        }

        $user = User::query()->find($job->requested_by);
        if ($user === null) {
            throw new RuntimeException('The export requester no longer exists.');
        }
        Auth::setUser($user);
        $locale = (string) ($job->filters['locale'] ?? config('app.fallback_locale', 'en'));
        app()->setLocale(in_array($locale, config('app.supported_locales', ['ar', 'en', 'ar-EG']), true) ? $locale : (string) config('app.fallback_locale', 'en'));

        Gate::forUser($user)->authorize('dashboard_reports.export_'.($job->format === 'csv' ? 'xlsx' : $job->format));
        $isCentral = in_array($job->report_key, CentralExportSnapshot::DATASETS, true);
        $isSalesReport = in_array($job->report_key, SalesReport::EXPORT_KEYS, true);
        $isInventoryReport = in_array($job->report_key, InventoryReport::EXPORT_KEYS, true);
        $snapshot = $isInventoryReport
            ? $inventoryReports->export($user, $job->report_key, $job->filters ?? [])
            : ($isSalesReport
            ? $salesReports->export($user, $job->report_key, $job->filters ?? [])
            : ($isCentral ? $central->execute($user, $job->report_key, $job->filters ?? []) : $reports->execute($user, $job->filters ?? [], true)));
        $currentSnapshotHash = $isInventoryReport
            ? $inventoryReports->fingerprint($snapshot)
            : ($isSalesReport
            ? $salesReports->fingerprint($snapshot)
            : ($isCentral ? $central->fingerprint($snapshot) : $reports->fingerprint($snapshot)));
        if ($job->snapshot_hash === null || ! hash_equals($job->snapshot_hash, $currentSnapshotHash)) {
            throw new RuntimeException('The report changed before export generation. Refresh the report and request it again. Expected snapshot '.$job->snapshot_hash.'; current snapshot '.$currentSnapshotHash.'.');
        }

        $job->update(['status' => 'running', 'started_at' => now(), 'error_message' => null]);
        if ($isCentral) {
            $snapshot = $this->asReportSnapshot($snapshot);
        }
        $bytes = match ($job->format) {
            'pdf' => $this->pdf($snapshot), 'csv' => $this->csv($snapshot), default => $this->xlsx($snapshot)
        };
        $extension = $job->format;
        $relative = 'exports/'.$job->public_id.'.'.$extension;
        Storage::disk('local')->put($relative, $bytes);
        $detailRowCount = collect($snapshot['detail_sections'] ?? [])->sum(fn (array $section): int => count($section['rows'] ?? []));
        $rowCount = $detailRowCount + count($snapshot['kpis']) + count($snapshot['sources']) + 5;
        $job->update([
            'status' => 'ready',
            'storage_disk' => 'local',
            'storage_path' => $relative,
            'row_count' => $rowCount,
            'completed_at' => now(),
        ]);

        app(RecordAuditEvent::class)->execute(
            category: 'reporting',
            event: 'export_ready',
            source: $job,
            after: ['status' => 'ready', 'format' => $job->format, 'row_count' => $rowCount],
            branchId: $job->branch_id,
            storeId: $job->store_id,
            metadata: ['filters' => $job->filters, 'expires_at' => $job->expires_at?->toIso8601String()],
        );
    }

    public function failed(Throwable $exception): void
    {
        $job = ExportJob::query()->find($this->exportJobId);
        if ($job === null || $job->status === 'ready') {
            return;
        }

        $user = User::query()->find($job->requested_by);
        if ($user !== null) {
            Auth::setUser($user);
        }
        $job->update(['status' => 'failed', 'error_message' => 'Export generation failed. Refresh the report and try again.', 'completed_at' => now()]);
        app(RecordAuditEvent::class)->execute(
            category: 'reporting',
            event: 'export_failed',
            source: $job,
            after: ['status' => 'failed'],
            branchId: $job->branch_id,
            storeId: $job->store_id,
            metadata: ['filters' => $job->filters, 'exception' => $exception::class],
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function xlsx(array $snapshot): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'toyjoy-export-');
        if ($temporary === false) {
            throw new RuntimeException('Could not allocate a temporary export file.');
        }
        $path = $temporary.'.xlsx';
        @rename($temporary, $path);
        $writer = WriterFactory::createFromFile($path);
        $writer->setCreator('3allaf | علاف');
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['3allaf dashboard export', 'Generated at', now()->toIso8601String()]));
        $writer->addRow(Row::fromValues(['Filter', 'Value']));
        foreach ($snapshot['filters'] as $key => $value) {
            $writer->addRow(Row::fromValues([$this->safe((string) $key), is_scalar($value) || $value === null ? $this->safe((string) ($value ?? '')) : json_encode($value)]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Metric', 'Value']));
        foreach ($snapshot['kpis'] as $key => $value) {
            $writer->addRow(Row::fromValues([$this->safe((string) $key), is_scalar($value) ? $value : json_encode($value)]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Source reconciliation', 'Value']));
        foreach ($snapshot['sources'] as $key => $value) {
            $writer->addRow(Row::fromValues([$this->safe((string) $key), is_scalar($value) ? $value : json_encode($value)]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Export detail', 'Value']));
        foreach ($snapshot['export'] ?? [] as $key => $value) {
            $writer->addRow(Row::fromValues([$this->safe((string) $key), is_bool($value) ? ($value ? 'true' : 'false') : $value]));
        }
        if (($snapshot['sources']['payment_method_summary'] ?? []) !== []) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Payment method', 'Collected']));
            foreach ($snapshot['sources']['payment_method_summary'] as $payment) {
                $writer->addRow(Row::fromValues([$this->safe((string) ($payment['method_code'] ?? '')), (float) ($payment['amount'] ?? 0)]));
            }
        }
        $writer->addRow(Row::fromValues([]));
        $salesLabel = (string) ($snapshot['sources']['sales_label'] ?? 'Approved sales');
        $writer->addRow(Row::fromValues([$this->safe($salesLabel).' document', 'Store', 'Cashier', 'Gross', 'Discount', 'Tax', 'Total', 'Status date']));
        foreach ($snapshot['sales'] as $sale) {
            $writer->addRow(Row::fromValues([
                $this->safe((string) $sale->document_number),
                $this->safe((string) ($sale->store?->code ?? '')),
                $this->safe((string) ($sale->cashier?->name ?? '')),
                (float) $sale->subtotal,
                (float) $sale->discount_total,
                (float) $sale->tax_total,
                (float) $sale->total,
                ($sale->approved_at ?? $sale->created_at)?->toIso8601String(),
            ]));
        }
        $writer->addRow(Row::fromValues(['Asset code', 'Name', 'Status', 'Condition']));
        foreach ($snapshot['assets'] as $asset) {
            $writer->addRow(Row::fromValues([$this->safe((string) $asset->code), $this->safe((string) $asset->name_en), $this->safe((string) $asset->status), $this->safe((string) $asset->condition)]));
        }
        foreach ($snapshot['detail_sections'] ?? [] as $section) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$this->safe((string) $section['title'])]));
            $writer->addRow(Row::fromValues(array_map(fn (mixed $label): string => $this->safe((string) $label), array_values($section['columns']))));
            foreach ($section['rows'] as $detailRow) {
                $writer->addRow(Row::fromValues(array_map(function (mixed $value): mixed {
                    if (is_string($value)) {
                        return $this->safe($value);
                    }

                    return is_scalar($value) || $value === null ? $value : $this->safe((string) json_encode($value));
                }, array_values($detailRow))));
            }
        }
        $writer->close();
        $bytes = file_get_contents($path);
        @unlink($path);
        if ($bytes === false) {
            throw new RuntimeException('Could not read the generated Excel artifact.');
        }

        return $bytes;
    }

    /** @param array<string, mixed> $snapshot */
    private function pdf(array $snapshot): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->setChroot(base_path());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pages.exports.report-pdf', ['report' => $snapshot])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param array<string,mixed> $snapshot */
    private function csv(array $snapshot): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Could not allocate CSV export stream.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($snapshot['detail_sections'] ?? [] as $section) {
            fputcsv($stream, array_map(fn ($v) => $this->safe((string) $v), array_values($section['columns'] ?? [])), ',', '"', '');
            foreach ($section['rows'] ?? [] as $row) {
                fputcsv($stream, array_map(fn ($v) => is_string($v) ? $this->safe($v) : $v, array_values($row)), ',', '"', '');
            }
        }
        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);
        if ($bytes === false) {
            throw new RuntimeException('Could not read CSV export stream.');
        }

        return $bytes;
    }

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    private function asReportSnapshot(array $snapshot): array
    {
        $rows = array_map(fn (array $row): array => array_combine($snapshot['columns'], $row), $snapshot['rows']);

        return ['filters' => $snapshot['filters'], 'modules' => [], 'fresh_at' => $snapshot['fresh_at'], 'kpis' => ['row_count' => $snapshot['row_count']], 'sources' => [], 'sales' => [], 'assets' => [], 'export' => ['dataset' => $snapshot['dataset'], 'row_count' => $snapshot['row_count']], 'detail_sections' => [['title' => __($snapshot['dataset']), 'columns' => array_combine($snapshot['columns'], $snapshot['columns']), 'rows' => $rows]]];
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
