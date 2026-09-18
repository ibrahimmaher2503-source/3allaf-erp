<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->restrictOnDelete();
            $table->string('unit_code_snapshot', 32)->nullable();
            $table->decimal('entered_quantity', 19, 6)->nullable();
            $table->decimal('conversion_factor_snapshot', 19, 6)->nullable();
            $table->decimal('entered_unit_price', 19, 4)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('purchase_order_lines')->whereNotNull('entered_quantity')->exists()) {
            throw new RuntimeException('Purchase-order unit history exists; retain the schema when rolling back application code.');
        }
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn(['unit_code_snapshot', 'entered_quantity', 'conversion_factor_snapshot', 'entered_unit_price']);
        });
    }
};
