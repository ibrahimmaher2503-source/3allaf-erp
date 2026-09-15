<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('subtotal', 19, 4)->default(0)->change();
            $table->decimal('tax_amount', 19, 4)->default(0)->change();
            $table->decimal('total_amount', 19, 4)->default(0)->change();
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('quantity_ordered', 20, 6)->change();
            $table->decimal('quantity_received', 20, 6)->default(0)->change();
            $table->decimal('unit_cost', 19, 4)->default(0)->change();
            $table->decimal('subtotal', 19, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('quantity_ordered', 15, 4)->change();
            $table->decimal('quantity_received', 15, 4)->default(0)->change();
            $table->decimal('unit_cost', 15, 4)->default(0)->change();
            $table->decimal('subtotal', 15, 4)->default(0)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('subtotal', 15, 4)->default(0)->change();
            $table->decimal('tax_amount', 15, 4)->default(0)->change();
            $table->decimal('total_amount', 15, 4)->default(0)->change();
        });
    }
};
