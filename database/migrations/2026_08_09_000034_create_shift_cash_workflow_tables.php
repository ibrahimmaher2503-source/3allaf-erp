<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TSK-025 — shift lifecycle, cash movements, blind close, and variance.
     *
     * `docs/32` §5 defines the state machine, §9 makes expected totals derived
     * and non-editable, §10 forbids exposing expected values before submission,
     * and §14 makes a closed shift immutable. The submitted actuals therefore
     * live in their own append-only table rather than as editable columns on
     * the shift.
     */
    public function up(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->string('currency_code', 3)->default('EGP')->after('closing_cash');
            $table->string('idempotency_key')->nullable()->unique()->after('currency_code');
            $table->string('opening_document_number')->nullable()->unique()->after('idempotency_key');
            $table->string('closing_document_number')->nullable()->unique()->after('opening_document_number');
            $table->timestamp('submitted_at')->nullable()->after('closed_at');
            $table->foreignId('opened_by')->nullable()->after('cashier_id')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('opened_by')->constrained('users')->nullOnDelete();
            $table->foreignId('variance_approved_by')->nullable()->after('closed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('variance_approved_at')->nullable()->after('submitted_at');
            $table->text('variance_approval_note')->nullable()->after('policy_notes');
            $table->unsignedInteger('recount_count')->default(0)->after('variance_approved_at');
            // Optimistic-concurrency guard for close/approve (docs/32 §16).
            $table->unsignedInteger('lock_version')->default(1)->after('recount_count');
            $table->index(['branch_id', 'status']);
        });

        // A drawer may hold at most one non-terminal shift, and a cashier may
        // hold at most one. Enforced in the action under a lock; these indexes
        // exist for the lookup rather than as a partial-unique constraint.
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $table->foreignId('cash_drawer_id')->constrained('cash_drawers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('movement_type');
            // Signed at the application boundary: cash_in positive, cash_out negative.
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['shift_id', 'movement_type']);
        });

        // Blind submission (docs/32 §10-§11). Append-only and versioned so a
        // recount produces a new row rather than editing the first.
        Schema::create('shift_closing_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $table->unsignedInteger('attempt');
            $table->decimal('actual_cash', 14, 2);
            /** @var array<string, string> per-method actual electronic amounts */
            $table->json('actual_by_method')->nullable();
            // Server-derived at submission time; never supplied by the cashier.
            $table->decimal('expected_cash', 14, 2);
            $table->json('expected_by_method')->nullable();
            $table->decimal('cash_variance', 14, 2);
            $table->json('method_variance')->nullable();
            $table->decimal('total_variance', 14, 2);
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['shift_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_closing_submissions');
        Schema::dropIfExists('cash_movements');

        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opened_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('variance_approved_by');
            $table->dropIndex(['branch_id', 'status']);
            // Drop dependent indexes before their columns so MySQL/MariaDB can
            // reverse this migration cleanly.
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['opening_document_number']);
            $table->dropUnique(['closing_document_number']);
            $table->dropColumn([
                'currency_code',
                'idempotency_key',
                'opening_document_number',
                'closing_document_number',
                'submitted_at',
                'variance_approved_at',
                'variance_approval_note',
                'recount_count',
                'lock_version',
            ]);
        });
    }
};
