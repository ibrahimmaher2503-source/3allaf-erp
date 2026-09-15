<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained('product_units')->restrictOnDelete();
            $table->decimal('entered_quantity', 20, 6)->nullable()->after('product_unit_id');
            $table->decimal('conversion_factor_snapshot', 20, 6)->nullable()->after('entered_quantity');
            $table->decimal('entered_unit_price', 19, 4)->nullable()->after('conversion_factor_snapshot');
        });

        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained('product_units')->restrictOnDelete();
            $table->decimal('entered_quantity', 20, 6)->nullable()->after('product_unit_id');
            $table->decimal('conversion_factor_snapshot', 20, 6)->nullable()->after('entered_quantity');
            $table->decimal('entered_unit_price', 19, 4)->nullable()->after('conversion_factor_snapshot');
        });

        DB::table('sale_lines')->whereNull('entered_quantity')->update([
            'entered_quantity' => DB::raw('quantity'),
            'conversion_factor_snapshot' => '1.000000',
            'entered_unit_price' => DB::raw('unit_price'),
        ]);
        DB::table('purchase_invoice_lines')->whereNull('entered_quantity')->update([
            'entered_quantity' => DB::raw('quantity'),
            'conversion_factor_snapshot' => '1.000000',
            'entered_unit_price' => DB::raw('unit_cost'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_unit_id', 'entered_quantity', 'conversion_factor_snapshot', 'entered_unit_price']);
        });
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_unit_id', 'entered_quantity', 'conversion_factor_snapshot', 'entered_unit_price']);
        });
    }
};
