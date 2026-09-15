<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_store_assignments')) {
            $columnsAreComplete = Schema::hasColumns('product_store_assignments', ['id', 'company_id', 'branch_id', 'store_id', 'product_id', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at']);
            $indexes = collect(Schema::getIndexes('product_store_assignments'))->keyBy('name');
            $foreignKeys = collect(Schema::getForeignKeys('product_store_assignments'));
            $unique = $indexes->get('product_store_assignment_unique');
            $scope = $indexes->get('product_store_assignment_scope_idx');
            $expectedForeignKeys = [
                'company_id' => 'companies',
                'branch_id' => 'branches',
                'store_id' => 'stores',
                'product_id' => 'products',
                'created_by' => 'users',
                'updated_by' => 'users',
            ];
            $foreignKeysAreComplete = collect($expectedForeignKeys)->every(
                fn (string $foreignTable, string $column): bool => $foreignKeys->contains(
                    fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]
                        && $foreignKey['foreign_table'] === $foreignTable
                        && $foreignKey['foreign_columns'] === ['id'],
                ),
            );

            if (! $columnsAreComplete
                || ! is_array($unique)
                || ! $unique['unique']
                || $unique['columns'] !== ['store_id', 'product_id']
                || ! is_array($scope)
                || $scope['columns'] !== ['company_id', 'branch_id', 'store_id', 'status']
                || ! $foreignKeysAreComplete) {
                throw new RuntimeException('Existing product_store_assignments table is incomplete; migration refused to mark a partial schema as applied.');
            }

            return;
        }

        Schema::create('product_store_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'product_id'], 'product_store_assignment_unique');
            $table->index(['company_id', 'branch_id', 'store_id', 'status'], 'product_store_assignment_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store_assignments');
    }
};
