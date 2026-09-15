<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('payment_policy', 20)->nullable()->after('payment_terms');
            $table->unsignedSmallInteger('credit_days')->nullable()->after('payment_policy');
            $table->decimal('credit_limit', 19, 4)->nullable()->after('credit_days');
            $table->string('commercial_registration', 100)->nullable()->after('tax_number');
            $table->text('notes')->nullable()->after('address');

            $table->index(['payment_policy', 'status'], 'suppliers_payment_policy_status_index');
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->unsignedBigInteger('cash_account_id')->nullable()->index();
            $table->date('payment_date');
            $table->string('currency_code', 3)->default('EGP');
            $table->decimal('amount', 19, 4);
            $table->string('reference', 190)->nullable();
            $table->string('evidence_reference', 190)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('approved');
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'currency_code', 'status', 'payment_date'], 'supplier_payments_balance_index');
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained('supplier_payments')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'purchase_invoice_id'], 'supplier_payment_invoice_unique');
            $table->index(['purchase_invoice_id', 'amount'], 'supplier_payment_invoice_amount_index');
        });

        Schema::create('supplier_account_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->restrictOnDelete();
            $table->date('adjustment_date');
            $table->string('currency_code', 3)->default('EGP');
            $table->string('direction', 20);
            $table->decimal('amount', 19, 4);
            $table->text('reason');
            $table->string('reference', 190)->nullable();
            $table->string('status', 20)->default('approved');
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'currency_code', 'status', 'adjustment_date'], 'supplier_adjustments_balance_index');
            $table->index(['purchase_invoice_id', 'status'], 'supplier_adjustments_invoice_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_account_adjustments');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('suppliers_payment_policy_status_index');
            $table->dropColumn(['payment_policy', 'credit_days', 'credit_limit', 'commercial_registration', 'notes']);
        });
    }
};
