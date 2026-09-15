<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('has_variations')->default(false)->after('product_type');
            $table->foreignId('parent_product_id')->nullable()->after('has_variations')->constrained('products')->restrictOnDelete();
            $table->string('variant_signature', 500)->nullable()->after('parent_product_id');
            $table->unsignedInteger('variant_sort_order')->nullable()->after('variant_signature');

            $table->unique(['parent_product_id', 'variant_signature'], 'products_family_variant_signature_unique');
            $table->index(['parent_product_id', 'status', 'variant_sort_order'], 'products_family_variants_index');
            $table->index(['has_variations', 'status'], 'products_family_status_index');
        });

        Schema::create('product_option_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_option_group_id')->constrained('product_option_groups')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->string('colour_swatch', 9)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_option_group_id', 'code'], 'product_option_values_group_code_unique');
            $table->index(['product_option_group_id', 'status', 'sort_order'], 'product_option_values_group_status_index');
        });

        Schema::create('product_family_option_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_option_group_id')->constrained('product_option_groups')->restrictOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();

            $table->unique(['product_id', 'product_option_group_id'], 'product_family_group_unique');
            $table->unique(['product_id', 'sort_order'], 'product_family_group_order_unique');
        });

        Schema::create('product_family_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_option_value_id')->constrained('product_option_values')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_option_value_id'], 'product_family_value_unique');
        });

        Schema::create('product_variant_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_option_group_id')->constrained('product_option_groups')->restrictOnDelete();
            $table->foreignId('product_option_value_id')->constrained('product_option_values')->restrictOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();

            $table->unique(['product_id', 'product_option_group_id'], 'product_variant_group_unique');
            $table->unique(['product_id', 'product_option_value_id'], 'product_variant_value_unique');
            $table->index(['product_option_value_id', 'product_id']);
        });

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->json('variant_snapshot')->nullable()->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropColumn('variant_snapshot');
        });
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_family_option_values');
        Schema::dropIfExists('product_family_option_groups');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_option_groups');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['parent_product_id']);
            $table->dropUnique('products_family_variant_signature_unique');
            $table->dropIndex('products_family_variants_index');
            $table->dropIndex('products_family_status_index');
            $table->dropColumn(['has_variations', 'parent_product_id', 'variant_signature', 'variant_sort_order']);
        });
    }
};
