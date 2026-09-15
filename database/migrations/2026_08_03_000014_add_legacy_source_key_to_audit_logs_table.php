<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('legacy_source_key')->nullable()->unique()->after('event_id');
        });

        DB::table('audit_logs')
            ->whereNull('legacy_source_key')
            ->orderBy('id')
            ->each(function (object $auditLog): void {
                $metadata = json_decode((string) ($auditLog->metadata ?? '{}'), true);

                if (($metadata['legacy_table'] ?? null) !== 'settings_audit_logs' || ! isset($metadata['legacy_id'])) {
                    return;
                }

                DB::table('audit_logs')->where('id', $auditLog->id)->update([
                    'legacy_source_key' => 'settings_audit_logs:'.$metadata['legacy_id'],
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique(['legacy_source_key']);
            $table->dropColumn('legacy_source_key');
        });
    }
};
