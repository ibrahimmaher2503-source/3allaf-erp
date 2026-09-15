<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('products', 'prod_status_parent_code_idx')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropIndex('prod_status_parent_code_idx');
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists('products', 'prod_status_parent_code_idx')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->index(['status', 'parent_product_id', 'item_code'], 'prod_status_parent_code_idx');
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            static fn (array $index): bool => ($index['name'] ?? null) === $name,
        );
    }
};
