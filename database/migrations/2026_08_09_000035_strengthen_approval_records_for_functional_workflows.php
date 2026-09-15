<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_records', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('request_permission')->nullable()->after('requested_action');
            $table->string('decision_permission')->nullable()->after('request_permission');
        });

        DB::table('approval_records')->orderBy('id')->each(function (object $record): void {
            [$requestPermission, $decisionPermission] = match ($record->source_type) {
                'pricing_labels' => ['pricing_labels.submit', 'pricing_labels.approve'],
                'purchase_orders' => ['purchase_orders.edit', 'purchase_orders.approve'],
                'purchase_invoices' => ['purchase_invoices_supplier_returns.edit', 'purchase_invoices_supplier_returns.approve'],
                'purchase_returns' => ['purchase_returns.edit', 'purchase_returns.approve'],
                'inventory_adjustments' => ['inventory_stock_card.submit', 'inventory_stock_card.approve'],
                'stock_counts' => ['stock_counts.submit', 'stock_counts.reconcile'],
                'stock_transfers' => ['transfers.submit', 'transfers.approve'],
                default => [$record->source_type.'.edit', $record->source_type.'.approve'],
            };
            DB::table('approval_records')->where('id', $record->id)->update([
                'uuid' => (string) Str::uuid(),
                'request_permission' => $requestPermission,
                'decision_permission' => $decisionPermission,
            ]);
        });

        Schema::table('approval_records', function (Blueprint $table): void {
            $table->unique('uuid', 'approval_records_uuid_unique');
            $table->index(['decision_permission', 'approval_state'], 'approval_records_permission_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('approval_records', function (Blueprint $table): void {
            $table->dropIndex('approval_records_permission_state_index');
            $table->dropUnique('approval_records_uuid_unique');
            $table->dropColumn(['uuid', 'request_permission', 'decision_permission']);
        });
    }
};
