<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name_ar', 255);
            $table->string('name_en', 255);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('price_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 20)->default('draft');
            $table->string('source_type', 40);
            $table->string('source_reference', 120)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->foreignId('approval_record_id')->nullable()->constrained('approval_records')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->text('reason_text')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['price_list_id', 'version']);
            $table->index(['state', 'effective_from', 'effective_to']);
            $table->index(['source_type', 'source_reference']);
        });

        Schema::create('price_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_version_id')->constrained('price_versions')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->decimal('amount', 14, 3);
            $table->decimal('reference_amount', 14, 3)->nullable();
            $table->boolean('open_price_allowed')->default(false);
            $table->string('active_key', 120)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['price_version_id', 'product_id', 'store_id']);
            $table->index(['product_id', 'store_id']);
            $table->index(['branch_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lines');
        Schema::dropIfExists('price_versions');
        Schema::dropIfExists('price_lists');
    }
};
