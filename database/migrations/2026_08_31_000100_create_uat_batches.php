<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uat_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->uuid('batch_key')->unique();
            $table->string('status', 20)->default('active');
            $table->json('coverage')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('uat_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uat_batch_id')->constrained('uat_batches')->cascadeOnDelete();
            $table->string('table_name', 80);
            $table->unsignedBigInteger('record_id');
            $table->unsignedSmallInteger('delete_order');
            $table->timestamps();
            $table->unique(['uat_batch_id', 'table_name', 'record_id']);
            $table->index(['uat_batch_id', 'delete_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uat_records');
        Schema::dropIfExists('uat_batches');
    }
};
