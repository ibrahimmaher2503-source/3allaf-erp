<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('phone_normalized', 64)->unique();
            $table->string('phone_display', 64);
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('email', 190)->nullable();
            $table->string('secondary_phone', 64)->nullable();
            $table->text('address_ar')->nullable();
            $table->text('address_en')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('merged_into_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['name_ar', 'name_en']);
            $table->index('merged_into_id');
        });

        Schema::create('customer_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'branch_id', 'store_id'], 'customer_scope_identity_unique');
            $table->index(['branch_id', 'store_id']);
        });

        Schema::create('customer_merge_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duplicate_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('survivor_customer_id')->constrained('customers')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('merged_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['duplicate_customer_id', 'survivor_customer_id'], 'customer_merge_history_index');
        });

        Schema::create('customer_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('purpose', 80);
            $table->string('status', 30);
            $table->timestamp('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 50);
            $table->string('wording_version', 80);
            $table->text('wording_text');
            $table->timestamp('retention_until')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'purpose', 'captured_at']);
            $table->index(['branch_id', 'store_id']);
        });

        Schema::create('customer_children', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->date('birth_date')->nullable();
            $table->string('purpose', 80);
            $table->string('consent_status', 30)->default('granted');
            $table->string('consent_wording_version', 80)->nullable();
            $table->text('consent_wording_text')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['purpose', 'birth_date']);
        });

        Schema::create('loyalty_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('activity', 30)->default('retail');
            $table->bigInteger('points');
            $table->text('reason');
            $table->string('source_reference', 190)->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approval_record_id')->nullable()->constrained('approval_records')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['customer_id', 'status', 'created_at']);
            $table->index(['branch_id', 'store_id', 'status']);
        });

        Schema::create('loyalty_ledger', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->string('activity', 30);
            $table->string('event_type', 40);
            $table->bigInteger('points');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('source_type', 120)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('source_reference', 190)->nullable();
            $table->string('rule_key', 120)->nullable();
            $table->string('rule_version', 80)->nullable();
            $table->string('reason', 190)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_record_id')->nullable()->constrained('approval_records')->nullOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'effective_at']);
            $table->index(['customer_id', 'expires_at', 'event_type']);
            $table->index(['source_type', 'source_id']);
            $table->index(['branch_id', 'store_id', 'effective_at']);
        });

        Schema::create('loyalty_point_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('debit_ledger_id')->constrained('loyalty_ledger')->cascadeOnDelete();
            $table->foreignId('earn_ledger_id')->constrained('loyalty_ledger')->restrictOnDelete();
            $table->unsignedBigInteger('points');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['debit_ledger_id', 'earn_ledger_id'], 'loyalty_allocation_pair_unique');
            $table->index(['earn_ledger_id', 'created_at']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('cashier_id')->constrained('customers')->nullOnDelete();
            $table->index(['customer_id', 'status', 'approved_at'], 'sales_customer_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_customer_history_index');
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('loyalty_point_allocations');
        Schema::dropIfExists('loyalty_ledger');
        Schema::dropIfExists('loyalty_adjustments');
        Schema::dropIfExists('customer_merge_events');
        Schema::dropIfExists('customer_children');
        Schema::dropIfExists('customer_consents');
        Schema::dropIfExists('customer_scopes');
        Schema::dropIfExists('customers');
    }
};
