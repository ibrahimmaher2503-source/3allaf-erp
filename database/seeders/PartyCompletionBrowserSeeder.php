<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PartyCompletionBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        if (! str_starts_with($database, 'toyjoy_party_completion_')) {
            throw new RuntimeException('Party browser fixtures may only be seeded into a named disposable Party completion database.');
        }

        $now = now();
        $company = Company::query()->firstOrFail();
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $company->id, 'code' => 'PARTY-UI-BR',
            'name_ar' => 'فرع الحفلات', 'name_en' => 'Party UI Branch',
            'timezone' => 'Africa/Cairo', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $storeId = DB::table('stores')->insertGetId([
            'company_id' => $company->id, 'branch_id' => $branchId,
            'code' => 'PARTY-UI', 'type' => 'party',
            'name_ar' => 'متجر الحفلات', 'name_en' => 'Party UI Store',
            'status' => 'active', 'allows_negative_stock' => false,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'phone_normalized' => '201055500001', 'phone_display' => '+201055500001',
            'name_ar' => 'عميل اختبار الحفلات', 'name_en' => 'Party Browser Customer',
            'status' => 'active', 'created_by' => $admin->id, 'updated_by' => $admin->id,
            'created_branch_id' => $branchId, 'created_store_id' => $storeId,
            'idempotency_key' => 'party-browser-customer', 'lock_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('customer_scopes')->insert([
            'customer_id' => $customerId, 'branch_id' => $branchId, 'store_id' => $storeId,
            'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'code' => 'PARTY-UI-CAT', 'name_ar' => 'مستهلكات الحفلات',
            'name_en' => 'Party consumables', 'status' => 'active', 'sort_order' => 0,
            'created_by' => $admin->id, 'updated_by' => $admin->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'item_code' => 'PARTY-CUPS-UI', 'name_ar' => 'أكواب حفلات',
            'name_en' => 'Party Cups', 'category_id' => $categoryId,
            'status' => 'active', 'barcode_mode' => 'none', 'has_variations' => false,
            'product_type' => 'simple', 'lock_version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('stock_balances')->insert([
            'product_id' => $productId, 'store_id' => $storeId,
            'on_hand' => 100, 'reserved' => 0, 'in_transit' => 0,
            'average_cost' => 1, 'total_value' => 100, 'version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('rental_assets')->insert([
            'public_id' => (string) Str::uuid(), 'code' => 'PARTY-ASSET-UI',
            'name_ar' => 'قلعة حفلات', 'name_en' => 'Party Castle', 'category' => 'Inflatable',
            'branch_id' => $branchId, 'store_id' => $storeId, 'location' => 'Party UI Store',
            'condition' => 'good', 'status' => 'available', 'cost_currency' => 'EGP',
            'created_by' => $admin->id, 'updated_by' => $admin->id, 'lock_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
