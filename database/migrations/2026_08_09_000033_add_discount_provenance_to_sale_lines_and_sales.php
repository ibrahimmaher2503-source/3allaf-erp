<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TSK-024 — discount provenance for POS-05 non-stacking (DEC-066 / POSF-04).
     *
     * Only one discount type may apply to an amount. Storing the discount
     * *type* on the row is what makes non-stacking enforceable in the
     * calculation service rather than in the UI (`docs/48` §4). A replacement
     * is an explicit user choice and is recorded with actor and reason.
     */
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->string('discount_type')->nullable()->after('discount_amount');
            $table->decimal('discount_rate', 5, 2)->nullable()->after('discount_type');
            $table->text('discount_reason')->nullable()->after('discount_rate');
            $table->foreignId('discount_applied_by')->nullable()->after('discount_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('discount_replaced_by')->nullable()->after('discount_applied_by')->constrained('users')->nullOnDelete();
            $table->timestamp('discount_replaced_at')->nullable()->after('discount_replaced_by');
            // Invoice-level discount allocated pro-rata to this line (docs/48 §3 step 4).
            $table->decimal('allocated_invoice_discount', 14, 2)->default(0)->after('discount_replaced_at');
            // Open price requires explicit authorisation (PRC-08 / TSK-017).
            $table->boolean('is_open_price')->default(false)->after('unit_price');
            $table->foreignId('open_price_authorized_by')->nullable()->after('is_open_price')->constrained('users')->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('invoice_discount_type')->nullable()->after('discount_total');
            $table->text('invoice_discount_reason')->nullable()->after('invoice_discount_type');
            $table->foreignId('invoice_discount_applied_by')->nullable()->after('invoice_discount_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_discount_applied_by');
            $table->dropColumn(['invoice_discount_type', 'invoice_discount_reason']);
        });

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_applied_by');
            $table->dropConstrainedForeignId('discount_replaced_by');
            $table->dropConstrainedForeignId('open_price_authorized_by');
            $table->dropColumn([
                'discount_type',
                'discount_rate',
                'discount_reason',
                'discount_replaced_at',
                'allocated_invoice_discount',
                'is_open_price',
            ]);
        });
    }
};
