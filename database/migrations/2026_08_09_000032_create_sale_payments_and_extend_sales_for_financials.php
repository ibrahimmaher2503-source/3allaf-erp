<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TSK-024 — payment capture, tax snapshot, and cash rounding (DEC-066).
     *
     * `docs/48` §5 requires the tax rate and mode in force at approval to be
     * stored on the invoice so a reprint reproduces the original figures.
     * §3 step 8 requires cash rounding to adjust what is collected without
     * altering the invoice total, so it is a separate column and a separate
     * printed line — never folded into `total`.
     */
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->string('method_code');
            $table->string('method_type');
            // Amount actually applied to settle the sale.
            $table->decimal('amount', 14, 2);
            // Cash may be tendered above the applied amount; the difference is change.
            $table->decimal('tendered_amount', 14, 2)->nullable();
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->string('evidence_reference')->nullable();
            $table->uuid('evidence_attachment_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['sale_id', 'method_code']);
            $table->foreign('evidence_attachment_id')->references('id')->on('attachments')->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table): void {
            // Tax is a per-invoice choice by an authorised user (POS-04).
            $table->boolean('tax_applicable')->default(false)->after('tax_total');
            $table->foreignId('tax_setting_id')->nullable()->after('tax_applicable')->constrained('tax_settings')->nullOnDelete();
            $table->decimal('tax_rate_snapshot', 5, 2)->nullable()->after('tax_setting_id');
            $table->string('tax_treatment_snapshot')->nullable()->after('tax_rate_snapshot');
            $table->boolean('tax_inclusive_snapshot')->default(false)->after('tax_treatment_snapshot');
            // Cash rounding adjusts the collected amount only (docs/48 §3 step 8).
            $table->decimal('cash_rounding_amount', 14, 2)->default(0)->after('change_total');
            $table->decimal('payable_total', 14, 2)->default(0)->after('cash_rounding_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_setting_id');
            $table->dropColumn([
                'tax_applicable',
                'tax_rate_snapshot',
                'tax_treatment_snapshot',
                'tax_inclusive_snapshot',
                'cash_rounding_amount',
                'payable_total',
            ]);
        });

        Schema::dropIfExists('sale_payments');
    }
};
