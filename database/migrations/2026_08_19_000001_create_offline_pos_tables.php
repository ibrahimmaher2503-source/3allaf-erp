<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->string('name');
            $table->string('token_hash');
            $table->string('policy_version', 80);
            $table->string('schema_version', 20);
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name']);
            $table->index(['branch_id', 'store_id', 'revoked_at']);
        });

        Schema::create('offline_sync_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offline_device_id')->constrained('offline_devices')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('state')->default('completed');
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('conflicted_count')->default(0);
            $table->timestamps();
            $table->index(['offline_device_id', 'created_at']);
        });

        Schema::create('offline_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offline_device_id')->constrained('offline_devices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('offline_sync_batch_id')->nullable()->constrained('offline_sync_batches')->nullOnDelete();
            $table->foreignId('server_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('local_uuid', 100)->unique();
            $table->string('state')->default('queued');
            $table->string('policy_version', 80);
            $table->string('schema_version', 20);
            $table->string('payload_hash', 64);
            $table->json('canonical_payload');
            $table->dateTime('captured_at');
            $table->dateTime('price_cached_at');
            $table->dateTime('expires_at');
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();
            $table->index(['offline_device_id', 'state', 'captured_at']);
            $table->index(['branch_id', 'store_id', 'state']);
        });

        Schema::create('offline_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offline_transaction_id')->constrained('offline_transactions')->restrictOnDelete();
            $table->string('field', 60);
            $table->text('local_value')->nullable();
            $table->text('server_value')->nullable();
            $table->string('disposition', 40)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['offline_transaction_id', 'field']);
            $table->index(['disposition', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_conflicts');
        Schema::dropIfExists('offline_transactions');
        Schema::dropIfExists('offline_sync_batches');
        Schema::dropIfExists('offline_devices');
    }
};
