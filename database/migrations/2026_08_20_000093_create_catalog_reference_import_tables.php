<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_reference_import_batches', function (Blueprint $table): void {
            $table->id(); $table->string('type', 20); $table->string('mode', 20); $table->foreignId('created_by')->constrained('users');
            $table->string('original_filename'); $table->string('storage_path'); $table->string('sha256', 64); $table->string('status', 30);
            $table->json('headers'); $table->unsignedInteger('total_rows')->default(0); $table->unsignedInteger('valid_rows')->default(0); $table->unsignedInteger('invalid_rows')->default(0);
            $table->timestamp('approved_at')->nullable(); $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['created_by', 'type', 'mode', 'sha256'], 'catalog_ref_import_unique');
        });
        Schema::create('catalog_reference_import_rows', function (Blueprint $table): void {
            $table->id(); $table->foreignId('catalog_reference_import_batch_id')->constrained('catalog_reference_import_batches', 'id', 'catalog_ref_row_batch_fk')->cascadeOnDelete(); $table->unsignedInteger('row_number');
            $table->json('raw_data'); $table->json('errors')->nullable(); $table->string('status', 20); $table->timestamps(); $table->index(['catalog_reference_import_batch_id', 'status'], 'catalog_ref_row_batch_status_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('catalog_reference_import_rows'); Schema::dropIfExists('catalog_reference_import_batches'); }
};
