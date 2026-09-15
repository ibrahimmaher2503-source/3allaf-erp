<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('type', 30);
            $table->string('currency_code', 3)->default('EGP');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status', 'type']);
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar', 255);
            $table->string('name_en', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'active', 'name_ar']);
        });

        Schema::create('cash_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->date('transaction_date');
            $table->decimal('amount', 19, 4);
            $table->string('transaction_type', 50);
            $table->string('source_type', 150)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('cash_transactions')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->text('description');
            $table->string('reference', 190)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['cash_account_id', 'transaction_date', 'id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 19, 4);
            $table->text('description');
            $table->string('reference', 190)->nullable();
            $table->string('status', 20)->default('approved');
            $table->string('idempotency_key', 190)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->index(['company_id', 'expense_date', 'status']);
            $table->index(['expense_category_id', 'expense_date']);
        });

        Schema::table('customer_receipts', function (Blueprint $table): void {
            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->restrictOnDelete();
        });
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->dropForeign(['cash_account_id']));
        Schema::table('customer_receipts', fn (Blueprint $table) => $table->dropForeign(['cash_account_id']));
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('cash_accounts');
    }
};
