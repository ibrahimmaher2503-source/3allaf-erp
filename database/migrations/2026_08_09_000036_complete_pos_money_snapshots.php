<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_lines', function (Blueprint $table): void {
            $table->decimal('open_price_minimum', 14, 4)->nullable()->after('open_price_allowed');
            $table->decimal('open_price_maximum', 14, 4)->nullable()->after('open_price_minimum');
        });

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->decimal('reference_price', 14, 4)->nullable()->after('unit_price');
            $table->decimal('open_price_minimum_snapshot', 14, 4)->nullable()->after('open_price_authorized_by');
            $table->decimal('open_price_maximum_snapshot', 14, 4)->nullable()->after('open_price_minimum_snapshot');
            $table->text('open_price_reason')->nullable()->after('open_price_maximum_snapshot');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->index('request_fingerprint', 'sales_request_fingerprint_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_request_fingerprint_index');
            $table->dropColumn('request_fingerprint');
        });

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'reference_price',
                'open_price_minimum_snapshot',
                'open_price_maximum_snapshot',
                'open_price_reason',
            ]);
        });

        Schema::table('price_lines', function (Blueprint $table): void {
            $table->dropColumn(['open_price_minimum', 'open_price_maximum']);
        });
    }
};
