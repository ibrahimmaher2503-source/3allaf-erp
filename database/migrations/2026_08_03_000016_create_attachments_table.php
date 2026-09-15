<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('purpose');
            $table->string('original_filename');
            $table->string('storage_filename');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->string('detected_mime_type');
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('visibility')->default('private');
            $table->string('status')->index();
            $table->string('request_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('retention_until')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['purpose', 'status']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['branch_id', 'store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
