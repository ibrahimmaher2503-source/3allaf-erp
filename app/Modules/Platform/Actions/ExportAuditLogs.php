<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportAuditLogs
{
    /** @param array<string, string|null> $filters */
    public function execute(array $filters): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        Gate::forUser($user)->authorize('export', AuditLog::class);
        $maximum = filter_var(config('audit.export_max_rows'), FILTER_VALIDATE_INT);
        if ($maximum === false || $maximum < 1) {
            throw ValidationException::withMessages(['export' => __('Audit export is blocked until an approved row limit is configured.')]);
        }

        $query = $this->applyFilters(AuditLog::query()->visibleTo($user), $filters)->oldest('id');
        $logs = $query->limit($maximum + 1)->get();
        if ($logs->count() > $maximum) {
            throw ValidationException::withMessages(['export' => __('The audit export exceeds the configured limit. Narrow the filters and try again.')]);
        }

        $redactor = app(AuditLogValueRedactor::class);
        $rows = $logs->map(fn (AuditLog $log): array => [
            $log->event_id,
            $log->created_at?->toIso8601String(),
            $log->category,
            $log->event,
            $log->actor_name,
            $log->source_type,
            $log->source_id,
            $log->branch_id,
            $log->store_id,
            $log->reason_code,
            $log->reason_text,
            json_encode($redactor->redactForViewer($log->before_values, $user), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($redactor->redactForViewer($log->after_values, $user), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($log->changed_fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $log->request_id,
        ]);

        app(RecordAuditEvent::class)->execute(
            'audit',
            'audit_log_exported',
            AuditLog::class,
            metadata: ['row_count' => $rows->count(), 'filters' => array_filter($filters, fn ($value) => filled($value))],
        );

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['event_id', 'created_at', 'category', 'event', 'actor', 'source_type', 'source_id', 'branch_id', 'store_id', 'reason_code', 'reason_text', 'before', 'after', 'changed_fields', 'request_id']);
            foreach ($rows as $row) {
                fputcsv($output, array_map($this->escapeFormula(...), $row));
            }
            fclose($output);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, string|null> $filters */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when(($filters['mode'] ?? 'all') === 'print', fn (Builder $query) => $query->where('event', 'like', '%print%'))
            ->when(($filters['mode'] ?? 'all') === 'override', fn (Builder $query) => $query->where(function (Builder $mode): void {
                $mode->where('event', 'like', '%override%')->orWhereJsonContains('metadata->override', true);
            }))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(fn (Builder $nested) => $nested->where('event', 'like', $term)->orWhere('source_type', 'like', $term)->orWhere('source_id', 'like', $term)->orWhere('request_id', 'like', $term)->orWhere('actor_name', 'like', $term));
            })
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(filled($filters['event'] ?? null), fn (Builder $query) => $query->where('event', $filters['event']))
            ->when(filled($filters['actor_id'] ?? null), fn (Builder $query) => $query->where('actor_id', (int) $filters['actor_id']))
            ->when(filled($filters['branch_id'] ?? null), fn (Builder $query) => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(filled($filters['store_id'] ?? null), fn (Builder $query) => $query->where('store_id', (int) $filters['store_id']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));
    }

    private function escapeFormula(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
