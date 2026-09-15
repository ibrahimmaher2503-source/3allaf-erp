<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->string('invoice_number', 50)->nullable()->change();
            $table->date('invoice_date')->nullable()->after('store_id');
            $table->string('currency_code', 3)->nullable()->after('invoice_date');
            $table->text('notes')->nullable()->after('total_amount');
            $table->unsignedBigInteger('lock_version')->default(0)->after('notes');
            $table->foreignId('created_by')->nullable()->after('lock_version')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->string('source_type', 100)->nullable()->after('updated_by');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->timestamp('submitted_at')->nullable()->after('approved_by');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('submitted_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index(['supplier_id', 'supplier_reference', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->string('discount_type', 20)->nullable()->after('unit_cost');
            $table->decimal('discount_value', 19, 4)->default(0)->after('discount_type');
            $table->decimal('tax_rate', 9, 4)->default(0)->after('discount_amount');
            $table->string('tax_code', 50)->nullable()->after('tax_rate');
            $table->decimal('quantity_received', 20, 6)->default(0)->after('quantity');
            $table->decimal('line_total', 19, 4)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value', 'tax_rate', 'tax_code', 'quantity_received', 'line_total']);
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropIndex(['supplier_id', 'supplier_reference', 'status']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'invoice_date', 'currency_code', 'notes', 'lock_version', 'created_by', 'updated_by',
                'source_type', 'source_id', 'submitted_at', 'submitted_by', 'rejected_at', 'rejected_by',
                'rejection_reason', 'cancelled_at', 'cancelled_by', 'cancel_reason',
            ]);
        });
    }
};
