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
        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->after('parent_id');
            $table->unsignedInteger('sort_order')->default(0)->after('name_en');
            $table->unique(['company_id', 'code'], 'customer_groups_company_code_unique');
            $table->index(['company_id', 'parent_id', 'sort_order'], 'customer_groups_hierarchy_order_index');
        });

        Schema::table('supplier_groups', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->after('parent_id');
            $table->unsignedInteger('sort_order')->default(0)->after('name_en');
            $table->unique(['company_id', 'code'], 'supplier_groups_company_code_unique');
            $table->index(['company_id', 'parent_id', 'sort_order'], 'supplier_groups_hierarchy_order_index');
        });

        Schema::create('governorates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('name_ar_normalized', 120);
            $table->string('name_en_normalized', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['governorate_id', 'company_id', 'code'], 'cities_scope_code_unique');
            $table->unique(['governorate_id', 'company_id', 'name_ar_normalized'], 'cities_scope_name_ar_unique');
            $table->unique(['governorate_id', 'company_id', 'name_en_normalized'], 'cities_scope_name_en_unique');
            $table->index(['company_id', 'governorate_id', 'status', 'sort_order'], 'cities_scope_lookup_index');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('governorate_id')->nullable()->after('customer_group_id')->constrained('governorates')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->after('governorate_id')->constrained('cities')->restrictOnDelete();
            $table->string('secondary_phone_normalized', 64)->nullable()->after('secondary_phone')->index();
            $table->index(['governorate_id', 'city_id', 'status'], 'customers_residence_status_index');
        });
        DB::table('customers')->whereNotNull('secondary_phone')->orderBy('id')->each(function (object $customer): void {
            $digits = preg_replace('/[^0-9]+/', '', strtr((string) $customer->secondary_phone, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
            if (is_string($digits) && str_starts_with($digits, '0020') && strlen($digits) === 16) {
                $digits = '0'.substr($digits, 4);
            } elseif (is_string($digits) && str_starts_with($digits, '20') && strlen($digits) === 12) {
                $digits = '0'.substr($digits, 2);
            }
            DB::table('customers')->where('id', $customer->id)->update(['secondary_phone_normalized' => $digits !== '' ? $digits : null]);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('settlement_method', 30)->nullable()->after('preferred_payment_method_id');
            $table->string('settlement_other_description', 255)->nullable()->after('settlement_method');
            $table->index(['status', 'settlement_method'], 'suppliers_status_settlement_index');
        });

        DB::table('suppliers')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'suppliers.preferred_payment_method_id')
            ->whereNotNull('suppliers.preferred_payment_method_id')
            ->select(['suppliers.id', 'payment_methods.code', 'payment_methods.name_ar', 'payment_methods.name_en'])
            ->orderBy('suppliers.id')
            ->each(function (object $supplier): void {
                $source = mb_strtolower(implode(' ', array_filter([(string) $supplier->code, (string) $supplier->name_ar, (string) $supplier->name_en])));
                $method = match (true) {
                    str_contains($source, 'cash') || str_contains($source, 'نقد') => 'cash',
                    str_contains($source, 'cheque') || str_contains($source, 'check') || str_contains($source, 'شيك') => 'cheques',
                    str_contains($source, 'install') || str_contains($source, 'دفع') || str_contains($source, 'قسط') => 'installments',
                    str_contains($source, 'trust') || str_contains($source, 'deposit') || str_contains($source, 'أمان') => 'trust_deposits',
                    default => 'other',
                };
                DB::table('suppliers')->where('id', $supplier->id)->update([
                    'settlement_method' => $method,
                    'settlement_other_description' => $method === 'other'
                        ? trim((string) ($supplier->name_en ?: $supplier->name_ar ?: $supplier->code))
                        : null,
                ]);
            });

        Schema::table('customer_import_batches', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('added_rows')->default(0)->after('invalid_rows');
            $table->unsignedInteger('duplicate_existing_rows')->default(0)->after('added_rows');
            $table->unsignedInteger('duplicate_file_rows')->default(0)->after('duplicate_existing_rows');
            $table->index(['company_id', 'created_by', 'created_at'], 'customer_import_company_actor_index');
        });

        Schema::table('supplier_import_batches', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('added_rows')->default(0)->after('invalid_rows');
            $table->index(['company_id', 'created_by', 'created_at'], 'supplier_import_company_actor_index');
        });

        $governorates = [
            ['CAI', 'القاهرة', 'Cairo'], ['GIZ', 'الجيزة', 'Giza'], ['ALX', 'الإسكندرية', 'Alexandria'],
            ['DKH', 'الدقهلية', 'Dakahlia'], ['SHR', 'البحر الأحمر', 'Red Sea'], ['BHR', 'البحيرة', 'Beheira'],
            ['FYM', 'الفيوم', 'Fayoum'], ['GHR', 'الغربية', 'Gharbia'], ['ISL', 'الإسماعيلية', 'Ismailia'],
            ['MNF', 'المنوفية', 'Monufia'], ['MIN', 'المنيا', 'Minya'], ['QLY', 'القليوبية', 'Qalyubia'],
            ['WGD', 'الوادي الجديد', 'New Valley'], ['SUZ', 'السويس', 'Suez'], ['ASW', 'أسوان', 'Aswan'],
            ['ASY', 'أسيوط', 'Assiut'], ['BNS', 'بني سويف', 'Beni Suef'], ['PSD', 'بورسعيد', 'Port Said'],
            ['DMT', 'دمياط', 'Damietta'], ['SHQ', 'الشرقية', 'Sharqia'], ['SIN', 'جنوب سيناء', 'South Sinai'],
            ['KFS', 'كفر الشيخ', 'Kafr El Sheikh'], ['MTR', 'مطروح', 'Matrouh'], ['LXR', 'الأقصر', 'Luxor'],
            ['QNA', 'قنا', 'Qena'], ['NSI', 'شمال سيناء', 'North Sinai'], ['SHG', 'سوهاج', 'Sohag'],
        ];
        $now = now();
        DB::table('governorates')->insert(array_map(static fn (array $row, int $index): array => [
            'code' => $row[0], 'name_ar' => $row[1], 'name_en' => $row[2], 'sort_order' => $index + 1,
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ], $governorates, array_keys($governorates)));

        $governorateIds = DB::table('governorates')->pluck('id', 'code');
        $cities = [
            ['CAI', 'CAI-CENTRAL', 'وسط القاهرة', 'Central Cairo'], ['CAI', 'CAI-NASR', 'مدينة نصر', 'Nasr City'],
            ['CAI', 'CAI-HELIOPOLIS', 'مصر الجديدة', 'Heliopolis'], ['CAI', 'CAI-MAADI', 'المعادي', 'Maadi'],
            ['CAI', 'CAI-NEW', 'القاهرة الجديدة', 'New Cairo'], ['GIZ', 'GIZ-GIZA', 'الجيزة', 'Giza'],
            ['GIZ', 'GIZ-DOKKI', 'الدقي', 'Dokki'], ['GIZ', 'GIZ-MOHANDESSIN', 'المهندسين', 'Mohandessin'],
            ['GIZ', 'GIZ-OCTOBER', 'السادس من أكتوبر', '6th of October'], ['GIZ', 'GIZ-SHEIKH', 'الشيخ زايد', 'Sheikh Zayed'],
            ['ALX', 'ALX-CENTRAL', 'وسط الإسكندرية', 'Central Alexandria'], ['ALX', 'ALX-MONTAZA', 'المنتزه', 'Montaza'],
            ['ALX', 'ALX-SMOUHA', 'سموحة', 'Smouha'], ['ALX', 'ALX-BORG', 'برج العرب', 'Borg El Arab'],
        ];
        DB::table('cities')->insert(array_map(static fn (array $row, int $index): array => [
            'governorate_id' => $governorateIds[$row[0]], 'company_id' => null, 'code' => $row[1],
            'name_ar' => $row[2], 'name_en' => $row[3], 'name_ar_normalized' => mb_strtolower(trim($row[2])),
            'name_en_normalized' => mb_strtolower(trim($row[3])), 'sort_order' => $index + 1, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ], $cities, array_keys($cities)));
    }

    public function down(): void
    {
        Schema::table('supplier_import_batches', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropIndex('supplier_import_company_actor_index');
            $table->dropColumn(['company_id', 'added_rows']);
        });
        Schema::table('customer_import_batches', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropIndex('customer_import_company_actor_index');
            $table->dropColumn(['company_id', 'added_rows', 'duplicate_existing_rows', 'duplicate_file_rows']);
        });
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('suppliers_status_settlement_index');
            $table->dropColumn(['settlement_method', 'settlement_other_description']);
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['governorate_id']);
            $table->dropIndex('customers_residence_status_index');
            $table->dropIndex(['secondary_phone_normalized']);
            $table->dropColumn(['city_id', 'governorate_id', 'secondary_phone_normalized']);
        });
        Schema::dropIfExists('cities');
        Schema::dropIfExists('governorates');
        Schema::table('supplier_groups', function (Blueprint $table): void {
            $table->dropUnique('supplier_groups_company_code_unique');
            $table->dropIndex('supplier_groups_hierarchy_order_index');
            $table->dropColumn(['code', 'sort_order']);
        });
        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->dropUnique('customer_groups_company_code_unique');
            $table->dropIndex('customer_groups_hierarchy_order_index');
            $table->dropColumn(['code', 'sort_order']);
        });
    }
};
