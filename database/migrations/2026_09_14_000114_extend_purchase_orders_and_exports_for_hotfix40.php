<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('receiving_status', 30)->default('not_received')->after('status');
            $table->index(['branch_id', 'status', 'receiving_status'], 'po_branch_workflow_idx');
        });

        Schema::create('purchase_order_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->uuid('attachment_id');
            $table->unsignedInteger('version');
            $table->string('document_status', 30);
            $table->string('locale', 10);
            $table->char('content_sha256', 64);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();
            $table->foreign('attachment_id')->references('id')->on('attachments')->restrictOnDelete();
            $table->unique(['purchase_order_id', 'version', 'locale'], 'po_document_version_locale_unique');
            $table->index(['purchase_order_id', 'document_status', 'generated_at'], 'po_document_lookup_idx');
        });

        Schema::table('export_jobs', function (Blueprint $table): void {
            $table->char('request_hash', 64)->nullable()->after('snapshot_hash');
            $table->index(['requested_by', 'request_hash', 'status'], 'export_request_dedupe_idx');
        });
    }

    public function down(): void
    {
        // Hotfix40 adds only nullable/defaulted/indexed compatibility fields and
        // append-only document metadata. Application rollback intentionally
        // retains them so approved PDF/export history is never destroyed.
    }
};
