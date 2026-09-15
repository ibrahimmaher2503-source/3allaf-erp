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
        $activeStates = ['open', 'closing_submitted', 'variance_review'];

        $duplicateCashier = DB::table('pos_shifts')->whereIn('status', $activeStates)
            ->select('cashier_id')->groupBy('cashier_id')->havingRaw('COUNT(*) > 1')->first();
        $duplicateDrawer = DB::table('pos_shifts')->whereIn('status', $activeStates)
            ->select('cash_drawer_id')->groupBy('cash_drawer_id')->havingRaw('COUNT(*) > 1')->first();
        if ($duplicateCashier !== null || $duplicateDrawer !== null) {
            throw new \RuntimeException('Active POS shift assignments contain duplicate cashiers or drawers; reconcile them before migrating.');
        }

        Schema::create('active_pos_shift_assignments', function (Blueprint $table): void {
            $table->foreignId('shift_id')->primary()->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('cashier_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('cash_drawer_id')->unique()->constrained('cash_drawers')->restrictOnDelete();
            $table->timestamps();
        });

        DB::table('pos_shifts')->whereIn('status', $activeStates)->orderBy('id')->get()
            ->each(static function (object $shift): void {
                DB::table('active_pos_shift_assignments')->insert([
                    'shift_id' => $shift->id,
                    'cashier_id' => $shift->cashier_id,
                    'cash_drawer_id' => $shift->cash_drawer_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        if (! $this->hasIndexForColumns('sales', ['shift_id'])) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->index('shift_id', 'sales_shift_id_reconciliation_index');
            });
        }

        if (! $this->hasUniqueIndexForColumns('pos_shifts', ['variance_approval_record_id'])) {
            Schema::table('pos_shifts', function (Blueprint $table): void {
                $table->unique('variance_approval_record_id', 'pos_shifts_variance_approval_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndexNamed('sales', 'sales_shift_id_reconciliation_index')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropIndex('sales_shift_id_reconciliation_index');
            });
        }

        if ($this->hasIndexNamed('pos_shifts', 'pos_shifts_variance_approval_unique')) {
            Schema::table('pos_shifts', function (Blueprint $table): void {
                $table->dropUnique('pos_shifts_variance_approval_unique');
            });
        }

        Schema::dropIfExists('active_pos_shift_assignments');
    }

    /** @param list<string> $columns */
    private function hasIndexForColumns(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            static fn (array $index): bool => array_values($index['columns'] ?? []) === $columns,
        );
    }

    /** @param list<string> $columns */
    private function hasUniqueIndexForColumns(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            static fn (array $index): bool => (bool) ($index['unique'] ?? false)
                && array_values($index['columns'] ?? []) === $columns,
        );
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            static fn (array $index): bool => (string) ($index['name'] ?? '') === $name,
        );
    }
};
