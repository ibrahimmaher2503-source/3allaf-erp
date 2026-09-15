<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type')->index();
            $table->string('source_id');
            $table->string('source_version')->nullable();
            $table->string('source_hash')->nullable();
            $table->string('requested_action');
            $table->string('approval_state')->index();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('limit_context')->nullable();
            $table->string('request_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->char('pending_key', 64)->nullable()->unique();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id', 'requested_action']);
            $table->index(['approval_state', 'requested_at']);
            $table->index(['requester_id', 'approval_state']);
            $table->index(['approver_id', 'approval_state']);
            $table->index(['branch_id', 'store_id', 'approval_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
    }
};
