<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->string('contact_name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'code']);
            $table->index(['name_ar', 'status']);
            $table->index(['name_en', 'status']);
        });

        Schema::create('product_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('supplier_item_code', 100)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->decimal('last_purchase_price', 15, 4)->nullable();
            $table->timestamp('last_purchase_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
            $table->index(['supplier_id', 'product_id']);
            $table->index(['product_id', 'is_preferred']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('suppliers');
    }
};
