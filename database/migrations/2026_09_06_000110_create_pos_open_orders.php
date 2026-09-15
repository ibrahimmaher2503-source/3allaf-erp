<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_open_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('cash_drawer_id')->constrained('cash_drawers')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('completed_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('status', 20)->default('open');
            $table->uuid('checkout_token')->unique();
            $table->boolean('tax_applicable')->default(false);
            $table->string('payment_mode', 20)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('last_activity_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['shift_id', 'cashier_id', 'sequence_number'], 'pos_open_order_sequence_unique');
            $table->index(['company_id', 'branch_id', 'store_id', 'cash_drawer_id', 'shift_id', 'cashier_id', 'status'], 'pos_open_order_scope_status');
            $table->index(['cashier_id', 'status', 'last_activity_at'], 'pos_open_order_cashier_activity');
        });

        Schema::create('pos_open_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_open_order_id')->constrained('pos_open_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('line_position');
            $table->decimal('quantity', 18, 6);
            $table->json('draft_payload');
            $table->timestamps();

            $table->unique(['pos_open_order_id', 'product_id'], 'pos_open_order_product_unique');
            $table->unique(['pos_open_order_id', 'line_position'], 'pos_open_order_line_position_unique');
        });

        Schema::create('pos_open_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_open_order_id')->constrained('pos_open_orders')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->unsignedInteger('line_position');
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('tendered_amount', 14, 2)->nullable();
            $table->string('safe_reference', 190)->nullable();
            $table->string('gift_card_identifier', 100)->nullable();
            $table->timestamps();

            $table->unique(['pos_open_order_id', 'line_position'], 'pos_open_order_payment_position_unique');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->index(['status', 'name_ar'], 'customers_status_name_ar_index');
            $table->index(['status', 'name_en'], 'customers_status_name_en_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_status_name_ar_index');
            $table->dropIndex('customers_status_name_en_index');
        });
        Schema::dropIfExists('pos_open_order_payments');
        Schema::dropIfExists('pos_open_order_lines');
        Schema::dropIfExists('pos_open_orders');
    }
};
