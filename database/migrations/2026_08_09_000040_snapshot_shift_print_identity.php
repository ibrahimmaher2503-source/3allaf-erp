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
        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->string('company_name_ar_snapshot')->nullable()->after('currency_code');
            $table->string('company_name_en_snapshot')->nullable()->after('company_name_ar_snapshot');
            $table->string('branch_code_snapshot')->nullable()->after('company_name_en_snapshot');
            $table->string('branch_name_ar_snapshot')->nullable()->after('branch_code_snapshot');
            $table->string('branch_name_en_snapshot')->nullable()->after('branch_name_ar_snapshot');
            $table->string('store_code_snapshot')->nullable()->after('branch_name_en_snapshot');
            $table->string('store_name_ar_snapshot')->nullable()->after('store_code_snapshot');
            $table->string('store_name_en_snapshot')->nullable()->after('store_name_ar_snapshot');
            $table->string('cash_drawer_code_snapshot')->nullable()->after('store_name_en_snapshot');
            $table->string('cash_drawer_name_ar_snapshot')->nullable()->after('cash_drawer_code_snapshot');
            $table->string('cash_drawer_name_en_snapshot')->nullable()->after('cash_drawer_name_ar_snapshot');
        });

        DB::table('pos_shifts')->orderBy('id')->get()->each(static function (object $shift): void {
            $branch = DB::table('branches')->where('id', $shift->branch_id)->first();
            $store = DB::table('stores')->where('id', $shift->store_id)->first();
            $drawer = DB::table('cash_drawers')->where('id', $shift->cash_drawer_id)->first();
            $company = $store === null ? null : DB::table('companies')->where('id', $store->company_id)->first();
            DB::table('pos_shifts')->where('id', $shift->id)->update([
                'company_name_ar_snapshot' => $company?->name_ar,
                'company_name_en_snapshot' => $company?->name_en,
                'branch_code_snapshot' => $branch?->code,
                'branch_name_ar_snapshot' => $branch?->name_ar,
                'branch_name_en_snapshot' => $branch?->name_en,
                'store_code_snapshot' => $store?->code,
                'store_name_ar_snapshot' => $store?->name_ar,
                'store_name_en_snapshot' => $store?->name_en,
                'cash_drawer_code_snapshot' => $drawer?->code,
                'cash_drawer_name_ar_snapshot' => $drawer?->name_ar,
                'cash_drawer_name_en_snapshot' => $drawer?->name_en,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->dropColumn([
                'company_name_ar_snapshot', 'company_name_en_snapshot',
                'branch_code_snapshot', 'branch_name_ar_snapshot', 'branch_name_en_snapshot',
                'store_code_snapshot', 'store_name_ar_snapshot', 'store_name_en_snapshot',
                'cash_drawer_code_snapshot', 'cash_drawer_name_ar_snapshot', 'cash_drawer_name_en_snapshot',
            ]);
        });
    }
};
