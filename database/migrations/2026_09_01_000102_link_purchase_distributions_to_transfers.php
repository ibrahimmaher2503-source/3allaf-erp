<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->foreignId('purchase_invoice_id')->nullable()->after('id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->unique(['purchase_invoice_id', 'destination_store_id'], 'purchase_distribution_destination_transfer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropForeign(['purchase_invoice_id']);
            $table->dropUnique('purchase_distribution_destination_transfer_unique');
            $table->dropColumn('purchase_invoice_id');
        });
    }
};
