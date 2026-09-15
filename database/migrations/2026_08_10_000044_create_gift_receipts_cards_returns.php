<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('status', 30)->default('active');
            $table->foreignId('used_return_id')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('gift_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_receipt_id')->constrained('gift_receipts')->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained('sale_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('item_code', 120);
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->decimal('quantity', 14, 6);
            $table->timestamps();
            $table->unique(['gift_receipt_id', 'sale_line_id']);
        });

        Schema::create('gift_receipt_print_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_receipt_id')->constrained('gift_receipts')->restrictOnDelete();
            $table->foreignId('printed_by')->constrained('users')->restrictOnDelete();
            $table->string('format', 30)->default('thermal');
            $table->boolean('is_reprint')->default(false);
            $table->text('reason')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamp('printed_at');
            $table->timestamps();
        });

        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 100)->unique();
            $table->string('status', 30)->default('active');
            $table->decimal('issued_value', 14, 2);
            $table->decimal('balance', 14, 2);
            $table->string('currency_code', 3);
            $table->foreignId('holder_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('source_type', 120);
            $table->string('source_id', 120);
            $table->string('source_reference', 190)->nullable();
            $table->timestamp('valid_from');
            // Expiry is an owner-configured policy. A card without a configured
            // expiry remains valid until it is voided or fully redeemed.
            $table->timestamp('valid_until')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['store_id', 'status', 'valid_until']);
        });

        Schema::create('gift_card_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->restrictOnDelete();
            $table->string('event_type', 30);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('source_reference', 190)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['gift_card_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('retail_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('source_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->foreignId('source_gift_receipt_id')->nullable()->constrained('gift_receipts')->restrictOnDelete();
            $table->foreignId('approval_record_id')->nullable()->constrained('approval_records')->restrictOnDelete();
            $table->string('return_number', 80)->nullable()->unique();
            $table->string('status', 30)->default('draft');
            $table->string('settlement_type', 30);
            $table->text('reason');
            $table->decimal('eligible_value', 14, 2)->default(0);
            $table->decimal('settlement_value', 14, 2)->default(0);
            $table->string('currency_code', 3)->default('EGP');
            $table->string('idempotency_key', 190)->unique();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['source_sale_id', 'status']);
        });

        Schema::create('retail_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_return_id')->constrained('retail_returns')->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained('sale_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_value', 14, 4);
            $table->decimal('eligible_value', 14, 2);
            $table->string('condition', 30);
            $table->string('disposition', 30);
            $table->text('inspection_notes')->nullable();
            $table->timestamps();
            $table->unique(['retail_return_id', 'sale_line_id']);
        });

        Schema::create('exchanges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_return_id')->unique()->constrained('retail_returns')->cascadeOnDelete();
            $table->string('exchange_number', 80)->nullable()->unique();
            $table->string('status', 30)->default('draft');
            $table->decimal('replacement_value', 14, 2)->default(0);
            $table->decimal('difference_value', 14, 2)->default(0);
            $table->string('difference_direction', 20)->default('none');
            $table->timestamps();
        });

        Schema::create('exchange_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('direction', 20)->default('outbound');
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_value', 14, 4);
            $table->string('item_code', 120);
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->timestamps();
        });

        Schema::create('retail_return_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_return_id')->constrained('retail_returns')->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('gift_card_id')->nullable()->constrained('gift_cards')->restrictOnDelete();
            $table->foreignId('original_payment_id')->nullable()->constrained('sale_payments')->restrictOnDelete();
            $table->string('direction', 20);
            $table->decimal('amount', 14, 2);
            $table->string('settlement_type', 30);
            $table->string('idempotency_key', 190)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::table('gift_receipts', function (Blueprint $table): void {
            $table->foreign('used_return_id')->references('id')->on('retail_returns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_return_settlements');
        Schema::dropIfExists('exchange_lines');
        Schema::dropIfExists('exchanges');
        Schema::dropIfExists('retail_return_lines');

        Schema::table('gift_receipts', function (Blueprint $table): void {
            $table->dropForeign(['used_return_id']);
        });

        Schema::dropIfExists('retail_returns');
        Schema::dropIfExists('gift_card_ledger');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('gift_receipt_print_events');
        Schema::dropIfExists('gift_receipt_lines');
        Schema::dropIfExists('gift_receipts');
    }
};
