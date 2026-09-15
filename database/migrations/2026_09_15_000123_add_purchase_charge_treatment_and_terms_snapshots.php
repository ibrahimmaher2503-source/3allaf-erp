<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_charges', function (Blueprint $table): void {
            $table->string('accounting_treatment', 30)
                ->default('landed_cost')
                ->after('amount');
            $table->index(['purchase_invoice_id', 'accounting_treatment'], 'purchase_charge_treatment_index');
        });

        DB::table('purchase_invoice_charges')
            ->whereNull('accounting_treatment')
            ->update(['accounting_treatment' => 'landed_cost']);

        // Existing charges were included in weighted-average cost before this
        // classification existed, so their only safe historical treatment is landed_cost.
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->text('payment_terms_snapshot')->nullable()->after('total_amount');
            $table->string('payment_policy_snapshot', 20)->nullable()->after('payment_terms_snapshot');
            $table->unsignedSmallInteger('credit_days_snapshot')->nullable()->after('payment_policy_snapshot');
            $table->decimal('credit_limit_snapshot', 19, 4)->nullable()->after('credit_days_snapshot');
            $table->date('due_date')->nullable()->after('credit_limit_snapshot');
            $table->timestamp('terms_snapshot_at')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_terms_snapshot',
                'payment_policy_snapshot',
                'credit_days_snapshot',
                'credit_limit_snapshot',
                'due_date',
                'terms_snapshot_at',
            ]);
        });

        Schema::table('purchase_invoice_charges', function (Blueprint $table): void {
            $table->dropIndex('purchase_charge_treatment_index');
            $table->dropColumn('accounting_treatment');
        });
    }
};
