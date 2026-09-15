<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->string('charge_type', 30);
            $table->decimal('amount', 19, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_invoice_id', 'charge_type']);
        });

        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->decimal('allocated_charge_amount', 19, 4)->nullable()->after('line_total');
            $table->decimal('inventory_unit_cost', 19, 6)->nullable()->after('allocated_charge_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn(['allocated_charge_amount', 'inventory_unit_cost']);
        });
        Schema::dropIfExists('purchase_invoice_charges');
    }
};
