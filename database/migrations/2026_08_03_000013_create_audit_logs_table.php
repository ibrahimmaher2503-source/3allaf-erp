<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('category')->index();
            $table->string('event')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('source_type')->nullable()->index();
            $table->string('source_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'store_id', 'created_at']);
            $table->index(['source_type', 'source_id', 'created_at']);
        });

        if (! Schema::hasTable('settings_audit_logs')) {
            return;
        }

        DB::table('settings_audit_logs')->orderBy('id')->each(function (object $legacy): void {
            $changes = json_decode((string) ($legacy->changes ?? '{}'), true);
            $changes = is_array($changes) ? $changes : [];
            $before = $changes['before'] ?? null;
            $after = $changes['after'] ?? $changes;

            DB::table('audit_logs')->insert([
                'event_id' => (string) Str::uuid(),
                'category' => $legacy->setting_type === 'user_authorization' ? 'authorization' : 'master_data',
                'event' => $legacy->action,
                'actor_id' => $legacy->user_id,
                'actor_name' => $legacy->user_name,
                'source_type' => 'legacy_settings:'.$legacy->setting_type,
                'source_id' => $legacy->setting_id === null ? null : (string) $legacy->setting_id,
                'before_values' => $before === null ? null : json_encode($before),
                'after_values' => empty($after) ? null : json_encode($after),
                'changed_fields' => empty($after) ? null : json_encode(array_keys($after)),
                'metadata' => json_encode([
                    'legacy_table' => 'settings_audit_logs',
                    'legacy_id' => $legacy->id,
                ]),
                'request_id' => $legacy->correlation_id,
                'created_at' => $legacy->created_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
