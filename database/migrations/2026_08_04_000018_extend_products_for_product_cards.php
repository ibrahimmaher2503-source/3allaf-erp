<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('model_number', 100)->nullable();
            $table->string('product_type', 20)->default('standard')->index();
            $table->string('unit_of_measure', 50)->nullable();
            $table->decimal('average_cost', 12, 2)->nullable();
            $table->decimal('reorder_threshold', 12, 3)->nullable();
            $table->decimal('dimension_length', 10, 3)->nullable();
            $table->decimal('dimension_width', 10, 3)->nullable();
            $table->decimal('dimension_height', 10, 3)->nullable();
            $table->string('dimension_unit', 20)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('target_age', 100)->nullable();
            $table->string('suitable_gender', 30)->nullable();
            $table->string('colour', 100)->nullable()->index();
            $table->string('size', 100)->nullable()->index();
            $table->string('character', 100)->nullable()->index();
            $table->text('key_points_ar')->nullable();
            $table->text('key_points_en')->nullable();
            $table->text('keywords_ar')->nullable();
            $table->text('keywords_en')->nullable();
            $table->boolean('fractional_quantity')->default(false);

            $table->index(['product_type', 'status']);
            $table->index(['target_age', 'status']);
            $table->index(['suitable_gender', 'status']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->string('role', 20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['product_id', 'attachment_id']);
            $table->index(['product_id', 'status', 'role', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['product_type', 'status']);
            $table->dropIndex(['target_age', 'status']);
            $table->dropIndex(['suitable_gender', 'status']);
            $table->dropIndex(['product_type']);
            $table->dropIndex(['colour']);
            $table->dropIndex(['size']);
            $table->dropIndex(['character']);
            $table->dropColumn([
                'description_ar', 'description_en', 'model_number', 'product_type', 'unit_of_measure',
                'average_cost', 'reorder_threshold', 'dimension_length', 'dimension_width', 'dimension_height',
                'dimension_unit', 'weight', 'target_age', 'suitable_gender', 'colour', 'size', 'character',
                'key_points_ar', 'key_points_en', 'keywords_ar', 'keywords_en', 'fractional_quantity',
            ]);
        });
    }
};
