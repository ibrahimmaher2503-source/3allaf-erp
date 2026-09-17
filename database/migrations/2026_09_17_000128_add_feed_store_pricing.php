<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalidOverrides = DB::table('product_price_overrides as price')
            ->whereRaw('(select count(*) from product_units as unit where unit.product_id = price.product_id and unit.is_base_unit = 1 and unit.is_sale_unit = 1) <> 1')
            ->exists();
        if ($invalidOverrides) {
            throw new RuntimeException('Every existing product price override must have exactly one sellable base unit before feed-store pricing can be enabled.');
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->after('customer_group_id')->constrained('price_lists')->restrictOnDelete();
        });
        Schema::table('product_units', function (Blueprint $table): void {
            $table->decimal('minimum_selling_price', 19, 4)->nullable()->after('conversion_factor');
        });
        Schema::table('product_price_overrides', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_unit_id')->nullable()->after('product_id');
        });

        DB::statement('UPDATE product_price_overrides AS price SET product_unit_id = (SELECT unit.id FROM product_units AS unit WHERE unit.product_id = price.product_id AND unit.is_base_unit = 1 AND unit.is_sale_unit = 1)');
        DB::statement('ALTER TABLE product_price_overrides MODIFY product_unit_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE product_price_overrides MODIFY amount DECIMAL(19,4) NOT NULL');
        Schema::table('product_price_overrides', function (Blueprint $table): void {
            $table->index('company_id', 'product_price_overrides_company_index');
            $table->dropUnique('product_price_override_unique');
            $table->foreign('product_unit_id', 'product_price_overrides_unit_fk')->references('id')->on('product_units')->restrictOnDelete();
            $table->unique(['company_id', 'price_list_id', 'product_id', 'product_unit_id'], 'product_price_overrides_scope_unique');
        });

        Schema::create('customer_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->decimal('price', 19, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('active_key', 120)->nullable()->unique();
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('expired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'customer_id', 'product_id', 'product_unit_id', 'status'], 'customer_prices_scope_status');
            $table->index(['effective_from', 'effective_to'], 'customer_prices_effective_dates');
        });

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->after('entered_unit_price')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('customer_product_price_id')->nullable()->after('price_list_id')->constrained('customer_product_prices')->restrictOnDelete();
            $table->string('price_source', 40)->nullable()->after('customer_product_price_id');
            $table->decimal('minimum_price_snapshot', 19, 4)->nullable()->after('price_source');
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'code' => 'pos_sales.override_below_minimum',
            'module' => 'pos_sales',
            'action' => 'override_below_minimum',
            'sensitivity' => 'sensitive',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $permissionId = DB::table('permissions')->where('code', 'pos_sales.override_below_minimum')->value('id');
        $administratorId = DB::table('roles')->where('code', 'system-administrator')->value('id');
        if ($administratorId !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $administratorId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $hasNewData = DB::table('customer_product_prices')->exists()
            || DB::table('customers')->whereNotNull('price_list_id')->exists()
            || DB::table('product_units')->whereNotNull('minimum_selling_price')->exists()
            || DB::table('sale_lines')->whereNotNull('price_list_id')->orWhereNotNull('customer_product_price_id')->orWhereNotNull('price_source')->orWhereNotNull('minimum_price_snapshot')->exists()
            || DB::table('product_price_overrides')->select(['company_id', 'price_list_id', 'product_id'])->groupBy('company_id', 'price_list_id', 'product_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($hasNewData) {
            throw new RuntimeException('Feed-store pricing contains live data; rollback is blocked to preserve unit prices, customer prices, and sale snapshots.');
        }

        $permissionIds = DB::table('permissions')->where('code', 'pos_sales.override_below_minimum')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_product_price_id');
            $table->dropConstrainedForeignId('price_list_id');
            $table->dropColumn(['price_source', 'minimum_price_snapshot']);
        });
        Schema::dropIfExists('customer_product_prices');
        Schema::table('product_price_overrides', function (Blueprint $table): void {
            $table->dropUnique('product_price_overrides_scope_unique');
            $table->dropForeign('product_price_overrides_unit_fk');
            $table->dropColumn('product_unit_id');
            $table->unique(['company_id', 'price_list_id', 'product_id'], 'product_price_override_unique');
            $table->dropIndex('product_price_overrides_company_index');
        });
        DB::statement('ALTER TABLE product_price_overrides MODIFY amount DECIMAL(14,3) NOT NULL');
        Schema::table('product_units', fn (Blueprint $table) => $table->dropColumn('minimum_selling_price'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropConstrainedForeignId('price_list_id'));
    }
};
