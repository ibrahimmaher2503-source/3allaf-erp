<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_returns', function (Blueprint $table): void {
            $table->foreignId('shift_id')->nullable()->after('store_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('cash_drawer_id')->nullable()->after('shift_id')->constrained('cash_drawers')->restrictOnDelete();
            $table->decimal('subtotal_refund', 14, 2)->default(0)->after('eligible_value');
            $table->decimal('discount_refund', 14, 2)->default(0)->after('subtotal_refund');
            $table->decimal('tax_refund', 14, 2)->default(0)->after('discount_refund');
            $table->decimal('rounding_refund', 14, 2)->default(0)->after('tax_refund');
            $table->string('completion_idempotency_key', 190)->nullable()->unique()->after('payload_hash');
            $table->string('completion_payload_hash', 64)->nullable()->after('completion_idempotency_key');
            $table->json('financial_reversal_snapshot')->nullable()->after('completion_payload_hash');
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('reason');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });

        Schema::table('retail_return_lines', function (Blueprint $table): void {
            $table->foreignId('return_store_id')->nullable()->after('product_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('gross_value', 14, 2)->default(0)->after('unit_value');
            $table->decimal('discount_value', 14, 2)->default(0)->after('gross_value');
            $table->decimal('tax_value', 14, 2)->default(0)->after('eligible_value');
            $table->index(['sale_line_id', 'retail_return_id'], 'retail_return_line_source_lookup');
        });

        Schema::table('retail_return_settlements', function (Blueprint $table): void {
            $table->unsignedSmallInteger('allocation_position')->default(1)->after('original_payment_id');
            $table->string('method_code_snapshot', 120)->nullable()->after('allocation_position');
            $table->string('method_type_snapshot', 60)->nullable()->after('method_code_snapshot');
            $table->string('safe_reference', 190)->nullable()->after('method_type_snapshot');
        });
// Deterministically preserve pre-Hotfix17 return economics. Legacy
// returns did not reverse tax or cash rounding, so those values remain
// zero while gross/discount are reconstructed from immutable sale lines.
DB::statement('UPDATE retail_return_lines AS rrl INNER JOIN sale_lines AS sl ON sl.id = rrl.sale_line_id SET rrl.gross_value = ROUND((sl.gross_amount * rrl.quantity) / NULLIF(sl.quantity, 0), 2), rrl.discount_value = ROUND((sl.discount_amount * rrl.quantity) / NULLIF(sl.quantity, 0), 2), rrl.tax_value = 0');
DB::statement('UPDATE retail_returns AS rr INNER JOIN (SELECT retail_return_id, SUM(gross_value) AS gross_value, SUM(discount_value) AS discount_value FROM retail_return_lines GROUP BY retail_return_id) AS totals ON totals.retail_return_id = rr.id SET rr.subtotal_refund = totals.gross_value, rr.discount_refund = totals.discount_value, rr.tax_refund = 0, rr.rounding_refund = 0');

    }

    public function down(): void
    {
        Schema::table('retail_return_settlements', function (Blueprint $table): void {
            $table->dropColumn(['allocation_position', 'method_code_snapshot', 'method_type_snapshot', 'safe_reference']);
        });

        Schema::table('retail_return_lines', function (Blueprint $table): void {
            $table->dropIndex('retail_return_line_source_lookup');
            $table->dropConstrainedForeignId('return_store_id');
            $table->dropColumn(['gross_value', 'discount_value', 'tax_value']);
        });

        Schema::table('retail_returns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('cash_drawer_id');
            $table->dropConstrainedForeignId('shift_id');
            $table->dropUnique(['completion_idempotency_key']);
            $table->dropColumn([
                'subtotal_refund', 'discount_refund', 'tax_refund', 'rounding_refund',
                'completion_idempotency_key', 'completion_payload_hash', 'financial_reversal_snapshot',
                'rejection_reason', 'rejected_at',
            ]);
        });
    }
};
