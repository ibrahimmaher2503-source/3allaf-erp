<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->decimal('base_consumer_price', 19, 3)->nullable()->after('unit_cost');
            $table->decimal('previous_product_cost', 19, 4)->nullable()->after('base_consumer_price');
            $table->decimal('previous_base_consumer_price', 19, 3)->nullable()->after('previous_product_cost');
        });

        Schema::create('purchase_invoice_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_line_id')->constrained('purchase_invoice_lines')->cascadeOnDelete();
            $table->foreignId('destination_store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('quantity', 19, 6);
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->decimal('effective_selling_price', 19, 3)->nullable();
            $table->boolean('price_is_override')->default(false);
            $table->timestamps();
            $table->unique(['purchase_invoice_line_id', 'destination_store_id'], 'purchase_distribution_line_store_unique');
            $table->index(['purchase_invoice_id', 'destination_store_id'], 'purchase_distribution_invoice_store_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_distributions');
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn(['base_consumer_price', 'previous_product_cost', 'previous_base_consumer_price']);
        });
    }
};
