<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ProductDependencyService
{
    /** @var list<string> */
    private const CONFIGURATION_TABLES = ['barcodes', 'product_images', 'product_suppliers', 'product_ages', 'product_characters', 'product_colours', 'product_genders', 'product_family_option_groups', 'product_family_option_values', 'product_variant_values', 'product_store_assignments'];

    /** @var array<string, array{type_column:string,id_column:string,type_values:list<string>}> */
    private const POLYMORPHIC_HISTORY_TABLES = [
        'audit_logs' => ['type_column' => 'source_type', 'id_column' => 'source_id', 'type_values' => [Product::class, 'products', 'product']],
        'approval_records' => ['type_column' => 'source_type', 'id_column' => 'source_id', 'type_values' => [Product::class, 'products', 'product']],
        'attachments' => ['type_column' => 'source_type', 'id_column' => 'source_id', 'type_values' => [Product::class, 'products', 'product']],
        'alerts' => ['type_column' => 'source_type', 'id_column' => 'source_id', 'type_values' => [Product::class, 'products', 'product']],
    ];

    /** @return array{counts:array<string,int>,configuration_counts:array<string,int>,operational_total:int,configuration_total:int,permanent_delete_allowed:bool} */
    public function inspect(Product $product): array
    {
        $database = DB::connection()->getDatabaseName();
        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')->where('CONSTRAINT_SCHEMA', $database)->where('REFERENCED_TABLE_SCHEMA', $database)->where('REFERENCED_TABLE_NAME', 'products')->where('REFERENCED_COLUMN_NAME', 'id')->get(['TABLE_NAME', 'COLUMN_NAME']);
        $operational = []; $configuration = [];
        foreach ($foreignKeys as $foreignKey) {
            $table = (string) $foreignKey->TABLE_NAME; $column = (string) $foreignKey->COLUMN_NAME;
            $count = $table === 'products' && $column === 'parent_product_id' ? Product::query()->where('parent_product_id', $product->id)->count() : DB::table($table)->where($column, $product->id)->count();
            if ($count < 1) continue;
            if (in_array($table, self::CONFIGURATION_TABLES, true)) {
                $configuration[$table] = (int) $count;
            } else {
                $operational[$table] = (int) $count;
            }
        }
        foreach (self::POLYMORPHIC_HISTORY_TABLES as $table => $reference) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, [$reference['type_column'], $reference['id_column']])) continue;
            $count = DB::table($table)->whereIn($reference['type_column'], $reference['type_values'])->where($reference['id_column'], (string) $product->id)->count();
            if ($count > 0) $operational[$table] = ($operational[$table] ?? 0) + (int) $count;
        }
        if (Schema::hasTable('uat_records') && Schema::hasColumns('uat_records', ['table_name', 'record_id'])) {
            $count = DB::table('uat_records')->where('table_name', 'products')->where('record_id', $product->id)->count();
            if ($count > 0) $operational['uat_records'] = ($operational['uat_records'] ?? 0) + (int) $count;
        }
        ksort($operational); ksort($configuration); $operationalTotal = array_sum($operational);

        return ['counts' => $operational, 'configuration_counts' => $configuration, 'operational_total' => $operationalTotal, 'configuration_total' => array_sum($configuration), 'permanent_delete_allowed' => $operationalTotal === 0];
    }

    public function assertCompanyScope(int $companyId, Product $product): void
    {
        if (! Company::query()->whereKey($companyId)->where('status', 'active')->exists()) abort(404);
        $outsideAssignments = Schema::hasTable('product_store_assignments')
            ? DB::table('product_store_assignments')->where('product_id', $product->id)->where('company_id', '<>', $companyId)->count()
            : 0;
        if ($outsideAssignments > 0 || Company::query()->where('status', 'active')->where('id', '<>', $companyId)->exists()) {
            throw ValidationException::withMessages(['product' => __('Product ownership cannot be proven exclusive to the selected company. Permanent deletion was blocked for central review.')]);
        }
    }

    public function removeConfiguration(Product $product): void
    {
        foreach (self::CONFIGURATION_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')) DB::table($table)->where('product_id', $product->id)->delete();
        }
    }
}
