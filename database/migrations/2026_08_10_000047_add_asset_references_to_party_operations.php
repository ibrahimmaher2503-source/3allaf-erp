<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_invoice_lines', function (Blueprint $table): void {
            $table->foreignId('rental_asset_id')->nullable()->after('product_id');
            $table->foreignId('asset_reservation_id')->nullable()->after('resource_key');
            $table->foreign('rental_asset_id', 'party_invoice_lines_asset_foreign')->references('id')->on('rental_assets')->restrictOnDelete();
            $table->foreign('asset_reservation_id', 'party_invoice_lines_reservation_foreign')->references('id')->on('asset_reservations')->restrictOnDelete();
            $table->index(['rental_asset_id', 'line_type'], 'party_invoice_lines_asset_type_index');
        });

        Schema::table('party_operating_order_lines', function (Blueprint $table): void {
            $table->foreignId('rental_asset_id')->nullable()->after('product_id');
            $table->foreignId('asset_reservation_id')->nullable()->after('resource_key');
            $table->foreignId('asset_checkout_id')->nullable()->after('asset_reservation_id');
            $table->foreignId('asset_return_id')->nullable()->after('asset_checkout_id');
            $table->foreignId('asset_inspection_event_id')->nullable()->after('asset_return_id');
            $table->foreign('rental_asset_id', 'party_order_lines_asset_foreign')->references('id')->on('rental_assets')->restrictOnDelete();
            $table->foreign('asset_reservation_id', 'party_order_lines_reservation_foreign')->references('id')->on('asset_reservations')->restrictOnDelete();
            $table->foreign('asset_checkout_id', 'party_order_lines_checkout_foreign')->references('id')->on('asset_checkouts')->restrictOnDelete();
            $table->foreign('asset_return_id', 'party_order_lines_return_foreign')->references('id')->on('asset_returns')->restrictOnDelete();
            $table->foreign('asset_inspection_event_id', 'party_order_lines_inspection_foreign')->references('id')->on('asset_events')->restrictOnDelete();
            $table->index(['rental_asset_id', 'line_type'], 'party_order_lines_asset_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('party_operating_order_lines', function (Blueprint $table): void {
            $table->dropForeign('party_order_lines_inspection_foreign');
            $table->dropForeign('party_order_lines_return_foreign');
            $table->dropForeign('party_order_lines_checkout_foreign');
            $table->dropForeign('party_order_lines_reservation_foreign');
            $table->dropForeign('party_order_lines_asset_foreign');
            $table->dropIndex('party_order_lines_asset_type_index');
            $table->dropColumn(['asset_inspection_event_id', 'asset_return_id', 'asset_checkout_id', 'asset_reservation_id', 'rental_asset_id']);
        });

        Schema::table('party_invoice_lines', function (Blueprint $table): void {
            $table->dropForeign('party_invoice_lines_reservation_foreign');
            $table->dropForeign('party_invoice_lines_asset_foreign');
            $table->dropIndex('party_invoice_lines_asset_type_index');
            $table->dropColumn(['asset_reservation_id', 'rental_asset_id']);
        });
    }
};
