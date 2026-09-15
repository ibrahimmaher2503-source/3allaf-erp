<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('cash_drawer_id')->constrained('cash_drawers')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('policy_notes')->nullable();
            $table->timestamps();
            $table->index(['cashier_id', 'status']);
            $table->index(['store_id', 'cash_drawer_id', 'status']);
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('cash_drawer_id')->nullable()->constrained('cash_drawers')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_number')->nullable()->unique();
            $table->string('status')->default('draft');
            $table->string('idempotency_key')->unique();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('change_total', 14, 2)->default(0);
            $table->string('currency_code', 3)->default('EGP');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['cashier_id', 'status']);
        });

        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('item_code');
            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->decimal('consumed_cost', 14, 4)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sale_id', 'line_number']);
            $table->index(['product_id', 'sale_id']);
        });

        Schema::create('suspended_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->string('resume_code', 32)->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('suspended');
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspended_sales');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('pos_shifts');
    }
};
