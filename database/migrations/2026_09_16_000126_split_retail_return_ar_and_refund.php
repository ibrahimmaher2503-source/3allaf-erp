<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_returns', function (Blueprint $table): void {
            $table->decimal('ar_reduction_value', 14, 2)->default(0)->after('settlement_value');
            $table->decimal('actual_refund_value', 14, 2)->default(0)->after('ar_reduction_value');
        });

        // Historical completed returns already posted their full settlement; no AR split can be inferred safely.
        DB::table('retail_returns')->where('status', 'completed')->update([
            'ar_reduction_value' => DB::raw('settlement_value'),
            'actual_refund_value' => DB::raw('settlement_value'),
        ]);
    }

    public function down(): void
    {
        if (DB::table('retail_returns')->where('ar_reduction_value', '>', 0)->exists()) {
            throw new \RuntimeException('Cannot discard posted customer AR reductions.');
        }

        Schema::table('retail_returns', function (Blueprint $table): void {
            $table->dropColumn(['actual_refund_value', 'ar_reduction_value']);
        });
    }
};
