<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('supplier_reference', 100)->nullable();
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('purchase_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->timestamps();

            $table->index(['purchase_order_line_id']);
            $table->index(['purchase_invoice_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('total_cost', 19, 4)->nullable();
            $table->decimal('consumed_cost', 19, 4)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('posted_at');
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'store_id', 'posted_at']);
            $table->index(['store_id', 'movement_type', 'posted_at']);
            $table->index(['source_type', 'source_id', 'source_line_id']);
            $table->index(
                ['product_id', 'store_id', 'posted_at', 'quantity', 'unit_cost'],
                'stock_movements_cost_lookup_index',
            );
        });

        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('on_hand', 20, 6)->default(0);
            $table->decimal('reserved', 20, 6)->default(0);
            $table->decimal('in_transit', 20, 6)->default(0);
            $table->decimal('average_cost', 19, 4)->default(0);
            $table->decimal('total_value', 19, 4)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'store_id']);
            $table->index(['store_id', 'on_hand']);
            $table->index(['product_id', 'store_id', 'average_cost']);
        });

        Schema::create('stock_period_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('quantity', 20, 6);
            $table->decimal('value', 19, 4);
            $table->timestamp('generated_at');
            $table->boolean('is_immutable')->default(true);
            $table->timestamps();

            $table->unique(
                ['product_id', 'store_id', 'period_start', 'period_end'],
                'stock_period_product_store_period_unique',
            );
            $table->index(['store_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_period_snapshots');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_invoice_lines');
        Schema::dropIfExists('purchase_invoices');
    }
};
