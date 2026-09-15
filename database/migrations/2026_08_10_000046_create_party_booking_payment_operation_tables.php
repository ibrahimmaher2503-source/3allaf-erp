<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('booking_number', 80)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('child_id')->nullable()->constrained('customer_children')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('party_date');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 80)->default('UTC');
            $table->string('location', 190);
            $table->string('primary_contact', 120);
            $table->string('secondary_contact', 120)->nullable();
            $table->text('notes')->nullable();
            $table->json('responsibilities')->nullable();
            $table->json('resource_keys')->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['branch_id', 'store_id', 'party_date', 'status'], 'party_bookings_scope_status_index');
            $table->index(['store_id', 'starts_at', 'ends_at', 'status'], 'party_bookings_schedule_index');
            $table->index(['customer_id', 'created_at'], 'party_bookings_customer_created_index');
        });

        Schema::create('party_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('party_booking_id')->unique()->constrained('party_bookings')->restrictOnDelete();
            $table->string('invoice_number', 80)->unique();
            $table->string('final_invoice_number', 80)->nullable()->unique();
            $table->string('final_receipt_number', 80)->nullable()->unique();
            $table->string('state', 40)->default('draft');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('wallet_applied_amount', 19, 4)->default(0);
            $table->decimal('balance_due', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->string('currency_code', 12);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->string('final_close_idempotency_key', 190)->nullable()->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['state', 'created_at'], 'party_invoices_state_created_index');
        });

        Schema::create('party_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_invoice_id')->constrained('party_invoices')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('line_type', 40);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('description_ar', 190);
            $table->string('description_en', 190);
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->string('resource_key', 190)->nullable();
            $table->string('source_reference', 190)->nullable();
            $table->timestamps();

            $table->unique(['party_invoice_id', 'line_number']);
            $table->index(['party_invoice_id', 'line_type'], 'party_invoice_lines_type_index');
        });

        Schema::create('party_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('party_invoice_id')->constrained('party_invoices')->restrictOnDelete();
            $table->foreignId('party_booking_id')->constrained('party_bookings')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->string('method_code', 80);
            $table->string('method_type', 50);
            $table->decimal('amount', 19, 4);
            $table->string('reference', 190)->nullable();
            $table->string('evidence_reference', 190)->nullable();
            $table->string('receipt_number', 80)->unique();
            $table->string('receipt_label', 255);
            $table->string('status', 30)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->index(['party_invoice_id', 'status', 'created_at'], 'party_payments_invoice_status_index');
            $table->index(['branch_id', 'store_id', 'created_at'], 'party_payments_scope_created_index');
        });

        Schema::create('party_operating_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('order_number', 80)->unique();
            $table->foreignId('party_booking_id')->constrained('party_bookings')->restrictOnDelete();
            $table->foreignId('party_invoice_id')->constrained('party_invoices')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 40)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['branch_id', 'store_id', 'status', 'created_at'], 'party_orders_scope_status_index');
            $table->index(['party_booking_id', 'status'], 'party_orders_booking_status_index');
        });

        Schema::create('party_operating_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_operating_order_id')->constrained('party_operating_orders')->restrictOnDelete();
            $table->foreignId('party_invoice_line_id')->nullable()->constrained('party_invoice_lines')->restrictOnDelete();
            $table->string('line_type', 40);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('resource_key', 190)->nullable();
            $table->string('description', 190);
            $table->decimal('planned_quantity', 20, 6)->default(0);
            $table->decimal('issued_quantity', 20, 6)->default(0);
            $table->decimal('consumed_quantity', 20, 6)->default(0);
            $table->decimal('returned_quantity', 20, 6)->default(0);
            $table->string('unit', 50)->nullable();
            $table->string('responsible_user_id', 120)->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->index(['party_operating_order_id', 'line_type'], 'party_order_lines_type_index');
            $table->index(['product_id', 'party_operating_order_id'], 'party_order_lines_product_index');
        });

        Schema::create('party_consumable_issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('party_operating_order_id')->constrained('party_operating_orders')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 30)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamps();

            $table->index(['party_operating_order_id', 'created_at'], 'party_issues_order_created_index');
        });

        Schema::create('party_consumable_issue_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_consumable_issue_id')->constrained('party_consumable_issues')->restrictOnDelete();
            $table->foreignId('party_operating_order_line_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['party_consumable_issue_id', 'party_operating_order_line_id'], 'party_issue_line_unique');
            $table->foreign('party_operating_order_line_id', 'party_issue_line_order_line_foreign')->references('id')->on('party_operating_order_lines')->restrictOnDelete();
        });

        Schema::create('party_consumable_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('party_operating_order_id')->constrained('party_operating_orders')->restrictOnDelete();
            $table->foreignId('party_consumable_issue_id')->constrained('party_consumable_issues')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 30)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamps();
        });

        Schema::create('party_consumable_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_consumable_return_id');
            $table->foreignId('party_consumable_issue_line_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['party_consumable_return_id', 'party_consumable_issue_line_id'], 'party_return_line_unique');
            $table->foreign('party_consumable_return_id', 'party_return_lines_return_foreign')->references('id')->on('party_consumable_returns')->restrictOnDelete();
            $table->foreign('party_consumable_issue_line_id', 'party_return_lines_issue_line_foreign')->references('id')->on('party_consumable_issue_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_consumable_return_lines');
        Schema::dropIfExists('party_consumable_returns');
        Schema::dropIfExists('party_consumable_issue_lines');
        Schema::dropIfExists('party_consumable_issues');
        Schema::dropIfExists('party_operating_order_lines');
        Schema::dropIfExists('party_operating_orders');
        Schema::dropIfExists('party_payments');
        Schema::dropIfExists('party_invoice_lines');
        Schema::dropIfExists('party_invoices');
        Schema::dropIfExists('party_bookings');
    }
};
