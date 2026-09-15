<?php

namespace App\Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillLegacySettingsAuditLogs
{
    /**
     * Copy the retired settings history once per stable legacy row identifier.
     */
    public function execute(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('settings_audit_logs')) {
            return 0;
        }

        $redactor = app(AuditLogValueRedactor::class);
        $inserted = 0;

        DB::table('settings_audit_logs')->orderBy('id')->each(function (object $legacy) use ($redactor, &$inserted): void {
            $legacySourceKey = 'settings_audit_logs:'.$legacy->id;

            if (DB::table('audit_logs')->where('legacy_source_key', $legacySourceKey)->exists()) {
                return;
            }

            $changes = json_decode((string) ($legacy->changes ?? '{}'), true);
            $changes = is_array($changes) ? $changes : [];
            $before = $changes['before'] ?? null;
            $after = $changes['after'] ?? $changes;
            $safeBefore = is_array($before) ? $redactor->redact($before) : null;
            $safeAfter = is_array($after) ? $redactor->redact($after) : null;

            DB::table('audit_logs')->insert([
                'event_id' => (string) Str::uuid(),
                'legacy_source_key' => $legacySourceKey,
                'category' => $legacy->setting_type === 'user_authorization' ? 'authorization' : 'master_data',
                'event' => $legacy->action,
                'actor_id' => $legacy->user_id,
                'actor_name' => $legacy->user_name,
                'source_type' => 'legacy_settings:'.$legacy->setting_type,
                'source_id' => $legacy->setting_id === null ? null : (string) $legacy->setting_id,
                'before_values' => $safeBefore === null ? null : json_encode($safeBefore),
                'after_values' => empty($safeAfter) ? null : json_encode($safeAfter),
                'changed_fields' => empty($safeAfter) ? null : json_encode(array_keys($safeAfter)),
                'metadata' => json_encode([
                    'legacy_table' => 'settings_audit_logs',
                    'legacy_id' => $legacy->id,
                ]),
                'request_id' => $legacy->correlation_id,
                'created_at' => $legacy->created_at,
            ]);

            $inserted++;
        });

        return $inserted;
    }
}
