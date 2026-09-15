<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEY = 'pos.cash_rounding_denomination';

    private const VALUE = '5.0000';

    private const NOTE = 'Hotfix14: owner-approved POS cash rounding denomination of 5 EGP.';

    /** Append the owner-approved value without rewriting setting history. */
    public function up(): void
    {
        if (! Schema::hasTable('pos_financial_setting_versions')) {
            throw new RuntimeException('The POS financial setting versions table is unavailable.');
        }

        DB::transaction(function (): void {
            $latest = DB::table('pos_financial_setting_versions')
                ->where('key', self::KEY)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            $latestValue = is_string($latest?->value) ? trim($latest->value) : null;
            if ($latestValue !== null
                && preg_match('/^\d+(?:\.\d+)?$/', $latestValue) === 1
                && bccomp($latestValue, self::VALUE, 4) === 0) {
                return;
            }

            DB::table('pos_financial_setting_versions')->insert([
                'key' => self::KEY,
                'value' => self::VALUE,
                'value_type' => 'decimal',
                'version' => ((int) ($latest?->version ?? 0)) + 1,
                'created_by' => null,
                'notes' => self::NOTE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Setting versions are append-only. Rolling application code back to
     * Hotfix13 remains compatible with the approved value, so do not erase it.
     */
    public function down(): void {}
};
