<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label_ar', 255);
            $table->string('label_en', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'code']);
        });

        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 50)->nullable()->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('reason_id')->constrained('supplier_return_reasons')->restrictOnDelete();
            $table->date('return_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedInteger('lock_version')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status', 'created_at']);
            $table->index(['purchase_invoice_id', 'status']);
            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('purchase_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->restrictOnDelete();
            $table->foreignId('purchase_invoice_line_id')->constrained('purchase_invoice_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('total_cost', 19, 4);
            $table->timestamps();

            $table->unique(['purchase_return_id', 'purchase_invoice_line_id'], 'purchase_return_line_source_unique');
            $table->index(['purchase_invoice_line_id', 'product_id'], 'purchase_return_line_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_return_reasons');
    }
};
