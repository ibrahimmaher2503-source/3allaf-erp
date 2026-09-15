<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('transfer_number', 60)->unique();
            $table->foreignId('source_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('destination_store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('difference_status', 30)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->text('reason_notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_store_id', 'status']);
            $table->index(['destination_store_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_requested', 20, 6);
            $table->decimal('quantity_dispatched', 20, 6)->default(0);
            $table->decimal('quantity_received', 20, 6)->default(0);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('difference_quantity', 20, 6)->default(0);
            $table->string('difference_type', 40)->nullable();
            $table->text('difference_reason')->nullable();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('adjustment_number', 60)->unique();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('adjustment_type', 30);
            $table->string('status', 30)->default('draft');
            $table->string('reason_code', 80);
            $table->text('reason_notes')->nullable();
            $table->boolean('allow_negative')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['adjustment_type', 'created_at']);
        });

        Schema::create('inventory_adjustment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_delta', 20, 6);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('before_on_hand', 20, 6)->nullable();
            $table->decimal('after_on_hand', 20, 6)->nullable();
            $table->timestamps();

            $table->unique(['inventory_adjustment_id', 'product_id'], 'inventory_adjustment_product_unique');
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('count_number', 60)->unique();
            $table->string('count_type', 20)->default('partial');
            $table->string('scope_type', 30)->default('store');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->timestamp('reference_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'status']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('reference_on_hand', 20, 6)->default(0);
            $table->decimal('movement_quantity_after_reference', 20, 6)->default(0);
            $table->decimal('expected_quantity', 20, 6)->default(0);
            $table->decimal('counted_quantity', 20, 6)->nullable();
            $table->decimal('variance_quantity', 20, 6)->nullable();
            $table->boolean('is_counted')->default(false);
            $table->string('input_method', 20)->nullable();
            $table->unsignedInteger('recount_number')->default(0);
            $table->timestamp('counted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
