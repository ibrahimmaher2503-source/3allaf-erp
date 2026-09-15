<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('barcode_registration_type', 20)->nullable()->after('barcode_mode')->index();
            $table->foreignId('originating_supplier_id')->nullable()->after('brand_id')->constrained('suppliers')->restrictOnDelete();
            $table->boolean('open_price')->default(false)->after('sale_price');
            $table->boolean('sell_online')->default(false)->after('open_price');
            $table->string('weight_unit', 12)->nullable()->after('weight');
        });

        Schema::create('opening_inventory_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('document_number', 50)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('opening_inventory_documents')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['company_id', 'status', 'created_at']);
        });

        Schema::create('opening_inventory_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opening_inventory_document_id')->constrained('opening_inventory_documents')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('total_value', 19, 4);
            $table->timestamps();
            $table->unique(['opening_inventory_document_id', 'product_id', 'store_id'], 'opening_lines_document_product_store_unique');
            $table->index(['product_id', 'store_id']);
        });

        Schema::create('opening_inventory_zero_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_inventory_zero_decisions');
        Schema::dropIfExists('opening_inventory_lines');
        Schema::dropIfExists('opening_inventory_documents');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['originating_supplier_id']);
            $table->dropIndex(['barcode_registration_type']);
            $table->dropColumn(['barcode_registration_type', 'originating_supplier_id', 'open_price', 'sell_online', 'weight_unit']);
        });
    }
};
