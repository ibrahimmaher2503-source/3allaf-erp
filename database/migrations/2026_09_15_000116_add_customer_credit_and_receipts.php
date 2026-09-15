<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone_normalized', 64)->nullable()->change();
            $table->string('phone_display', 64)->nullable()->change();
            $table->string('customer_type', 20)->nullable()->after('customer_group_id');
            $table->decimal('credit_limit', 19, 4)->nullable()->after('customer_type');
            $table->text('notes')->nullable()->after('credit_limit');
            $table->index(['customer_type', 'status'], 'customers_credit_type_status_idx');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('outstanding_amount', 19, 4)->default(0)->after('paid_total');
            $table->string('payment_status', 20)->default('unpaid')->after('outstanding_amount');
            $table->index(['customer_id', 'payment_status', 'approved_at'], 'sales_customer_payment_status_idx');
        });

        Schema::create('customer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->unsignedBigInteger('cash_account_id')->nullable()->index();
            $table->date('receipt_date');
            $table->decimal('amount', 19, 4);
            $table->string('reference', 190)->nullable();
            $table->string('evidence_reference', 190)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->string('idempotency_key', 190)->unique();
            $table->string('payload_hash', 64);
            $table->timestamps();
            $table->index(['customer_id', 'status', 'receipt_date'], 'customer_receipts_balance_idx');
        });

        Schema::create('customer_receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_receipt_id')->constrained('customer_receipts')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_receipt_id', 'sale_id'], 'customer_receipt_sale_unique');
            $table->index(['sale_id', 'created_at'], 'customer_receipt_allocations_sale_idx');
        });

        Schema::create('customer_account_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->date('adjustment_date');
            $table->decimal('amount', 19, 4);
            $table->text('reason');
            $table->string('reference', 190)->nullable();
            $table->string('status', 20)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->string('idempotency_key', 190)->unique();
            $table->string('payload_hash', 64);
            $table->timestamps();
            $table->index(['customer_id', 'status', 'adjustment_date'], 'customer_adjustments_balance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_adjustments');
        Schema::dropIfExists('customer_receipt_allocations');
        Schema::dropIfExists('customer_receipts');
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_customer_payment_status_idx');
            $table->dropColumn(['outstanding_amount', 'payment_status']);
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_credit_type_status_idx');
            $table->dropColumn(['customer_type', 'credit_limit', 'notes']);
            $table->string('phone_normalized', 64)->nullable(false)->change();
            $table->string('phone_display', 64)->nullable(false)->change();
        });
    }
};
