<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'status', 'sort_order']);
            $table->index(['status', 'code']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'code']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('item_code', 50)->unique();
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('barcode_mode', 20)->default('none');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['status', 'category_id']);
            $table->index(['status', 'brand_id']);
            $table->index(['name_ar', 'status']);
            $table->index(['name_en', 'status']);
        });

        Schema::create('barcode_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 4)->unique();
            $table->unsignedInteger('next_serial')->default(1);
            $table->timestamps();
        });

        Schema::create('barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('barcode', 64)->unique();
            $table->string('source', 20);
            $table->string('supplier_code', 4)->nullable();
            $table->unsignedInteger('serial_value')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_primary')->default(false);
            $table->string('allocation_key', 100)->nullable()->unique();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['source', 'supplier_code', 'serial_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcodes');
        Schema::dropIfExists('barcode_sequences');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
