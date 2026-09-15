<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 120)->unique();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('category', 120)->nullable();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('location', 190)->nullable();
            $table->string('condition', 40)->default('good');
            $table->string('status', 40)->default('available');
            $table->decimal('cost_value', 14, 2)->nullable();
            $table->string('cost_currency', 3)->default('EGP');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['branch_id', 'store_id', 'status']);
            $table->index(['category', 'status']);
        });

        Schema::create('asset_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('rental_assets')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('source_reference', 190)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 80)->default('UTC');
            $table->unsignedInteger('buffer_before_minutes')->default(0);
            $table->unsignedInteger('buffer_after_minutes')->default(0);
            $table->string('status', 30)->default('reserved');
            $table->foreignId('reserved_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['asset_id', 'status', 'starts_at', 'ends_at']);
            $table->index(['branch_id', 'store_id', 'starts_at', 'ends_at']);
        });

        Schema::create('asset_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('rental_assets')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('asset_reservations')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('source_reference', 190);
            $table->timestamp('checked_out_at');
            $table->string('location_before', 190)->nullable();
            $table->string('location_after', 190)->nullable();
            $table->string('condition_before', 40);
            $table->text('notes')->nullable();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamps();
            $table->index(['asset_id', 'checked_out_at']);
        });

        Schema::create('asset_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('rental_assets')->restrictOnDelete();
            $table->foreignId('checkout_id')->constrained('asset_checkouts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->timestamp('returned_at');
            $table->string('location_after', 190)->nullable();
            $table->string('condition_after', 40);
            $table->string('outcome', 40)->default('under_inspection');
            $table->text('notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamps();
            $table->index(['asset_id', 'returned_at']);
        });

        Schema::create('asset_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('rental_assets')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('party_reference', 190)->nullable();
            $table->text('assessment');
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('cost_value', 14, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->string('resulting_status', 40)->nullable();
            $table->string('status', 30)->default('submitted');
            $table->string('evidence_attachment_id', 120)->nullable();
            $table->foreignId('approval_record_id')->nullable()->constrained('approval_records')->restrictOnDelete();
            $table->foreignId('correction_of_id')->nullable()->constrained('asset_events')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'event_type', 'created_at']);
            $table->index(['branch_id', 'store_id', 'status']);
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('quotation_number', 120)->unique();
            $table->string('activity_type', 20);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('valid_until');
            $table->string('status', 30)->default('draft');
            $table->string('currency_code', 3)->default('EGP');
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('source_reference', 190)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['branch_id', 'store_id', 'status', 'valid_until']);
            $table->index(['activity_type', 'created_at']);
        });

        Schema::create('quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('line_type', 30);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('description_ar', 190);
            $table->string('description_en', 190);
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->string('source_reference', 190)->nullable();
            $table->timestamps();
            $table->unique(['quotation_id', 'line_number']);
            $table->index(['product_id', 'line_type']);
        });

        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('alert_key', 190)->unique();
            $table->string('alert_type', 60);
            $table->string('severity', 20)->default('warning');
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'store_id', 'status', 'severity']);
            $table->index(['alert_type', 'status']);
        });

        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('report_key', 80);
            $table->string('format', 20);
            $table->string('status', 20)->default('queued');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('storage_disk', 60)->nullable();
            $table->string('storage_path', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['requested_by', 'status', 'created_at']);
            $table->index(['report_key', 'branch_id', 'store_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('asset_events');
        Schema::dropIfExists('asset_returns');
        Schema::dropIfExists('asset_checkouts');
        Schema::dropIfExists('asset_reservations');
        Schema::dropIfExists('rental_assets');
    }
};
