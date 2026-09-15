<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('pos_shifts', [
            'branch_id' => ['branches', 'restrict'], 'store_id' => ['stores', 'restrict'],
            'cash_drawer_id' => ['cash_drawers', 'restrict'], 'cashier_id' => ['users', 'restrict'],
            'opened_by' => ['users', 'restrict'], 'closed_by' => ['users', 'restrict'],
            'variance_approved_by' => ['users', 'restrict'],
            'variance_approval_record_id' => ['approval_records', 'restrict'],
        ]);
        $this->replace('sales', [
            'branch_id' => ['branches', 'restrict'], 'store_id' => ['stores', 'restrict'],
            'cash_drawer_id' => ['cash_drawers', 'restrict'], 'shift_id' => ['pos_shifts', 'restrict'],
            'cashier_id' => ['users', 'restrict'], 'tax_setting_id' => ['tax_settings', 'restrict'],
        ]);
        $this->replace('sale_lines', [
            'sale_id' => ['sales', 'restrict'], 'stock_movement_id' => ['stock_movements', 'restrict'],
        ]);
        $this->replace('suspended_sales', [
            'sale_id' => ['sales', 'restrict'], 'created_by' => ['users', 'restrict'],
        ]);
        $this->replace('sale_payments', ['sale_id' => ['sales', 'restrict']]);
        $this->replace('cash_movements', [
            'shift_id' => ['pos_shifts', 'restrict'], 'approved_by' => ['users', 'restrict'],
        ]);
        $this->replace('shift_closing_submissions', ['shift_id' => ['pos_shifts', 'restrict']]);
    }

    public function down(): void
    {
        $this->replace('pos_shifts', [
            'branch_id' => ['branches', 'cascade'], 'store_id' => ['stores', 'cascade'],
            'cash_drawer_id' => ['cash_drawers', 'cascade'], 'cashier_id' => ['users', 'cascade'],
            'opened_by' => ['users', 'null'], 'closed_by' => ['users', 'null'],
            'variance_approved_by' => ['users', 'null'],
            'variance_approval_record_id' => ['approval_records', 'null'],
        ]);
        $this->replace('sales', [
            'branch_id' => ['branches', 'cascade'], 'store_id' => ['stores', 'cascade'],
            'cash_drawer_id' => ['cash_drawers', 'null'], 'shift_id' => ['pos_shifts', 'null'],
            'cashier_id' => ['users', 'cascade'], 'tax_setting_id' => ['tax_settings', 'null'],
        ]);
        $this->replace('sale_lines', [
            'sale_id' => ['sales', 'cascade'], 'stock_movement_id' => ['stock_movements', 'null'],
        ]);
        $this->replace('suspended_sales', [
            'sale_id' => ['sales', 'cascade'], 'created_by' => ['users', 'cascade'],
        ]);
        $this->replace('sale_payments', ['sale_id' => ['sales', 'cascade']]);
        $this->replace('cash_movements', [
            'shift_id' => ['pos_shifts', 'cascade'], 'approved_by' => ['users', 'null'],
        ]);
        $this->replace('shift_closing_submissions', ['shift_id' => ['pos_shifts', 'cascade']]);
    }

    /**
     * @param array<string, array{0: string, 1: 'restrict'|'cascade'|'null'}> $foreignKeys
     */
    private function replace(string $tableName, array $foreignKeys): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
            foreach ($foreignKeys as $column => [$parent, $delete]) {
                $table->dropForeign([$column]);
                $foreign = $table->foreign($column)->references('id')->on($parent);
                match ($delete) {
                    'restrict' => $foreign->restrictOnDelete(),
                    'cascade' => $foreign->cascadeOnDelete(),
                    'null' => $foreign->nullOnDelete(),
                };
            }
        });
    }
};
