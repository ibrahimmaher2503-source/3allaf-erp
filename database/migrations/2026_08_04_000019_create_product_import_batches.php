<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256', 64);
            $table->string('mode', 30);
            $table->string('status', 30)->default('staged');
            $table->json('headers')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['created_by', 'sha256']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('product_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 20)->default('invalid');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_import_batch_id', 'row_number']);
            $table->index(['product_import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_import_batches');
    }
};
