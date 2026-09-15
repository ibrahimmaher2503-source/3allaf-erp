<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('price_lists', function (Blueprint $table): void {
            $table->unsignedInteger('list_number')->nullable()->after('company_id');
            $table->decimal('percentage_increase', 9, 4)->default(0)->after('name_en');
            $table->date('effective_from')->nullable()->after('status');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->unique(['company_id', 'list_number'], 'price_lists_company_number_unique');
        });
        Schema::create('product_price_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('amount', 14, 3); $table->text('reason');
            $table->date('effective_from')->nullable(); $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['company_id','price_list_id','product_id'],'product_price_override_unique');
        });
        Schema::table('branches', fn (Blueprint $table) => $table->foreignId('default_price_list_id')->nullable()->after('company_id')->constrained('price_lists')->restrictOnDelete());
        Schema::table('stores', fn (Blueprint $table) => $table->foreignId('price_list_id')->nullable()->after('branch_id')->constrained('price_lists')->restrictOnDelete());
        foreach (DB::table('companies')->select('id')->get() as $company) DB::table('price_lists')->updateOrInsert(['company_id'=>$company->id,'list_number'=>0], ['code'=>'PL-0000','name_ar'=>'قائمة المستهلك الأساسية','name_en'=>'Base Consumer Price List','percentage_increase'=>0,'status'=>'active','updated_at'=>now(),'created_at'=>now()]);
        foreach ([['pricing_lists.view','view','normal'],['pricing_lists.manage','manage','sensitive'],['pricing_lists.approve','approve','sensitive'],['pricing_lists.overrides','override','sensitive'],['pricing_lists.assign','assign','sensitive']] as [$code,$action,$sensitivity]) DB::table('permissions')->updateOrInsert(['code'=>$code], ['module'=>'pricing_lists','action'=>$action,'sensitivity'=>$sensitivity,'status'=>'active','updated_at'=>now(),'created_at'=>now()]);
        if ($admin=DB::table('roles')->where('code','system-administrator')->value('id')) foreach (DB::table('permissions')->where('module','pricing_lists')->pluck('id') as $permission) DB::table('role_permissions')->insertOrIgnore(['role_id'=>$admin,'permission_id'=>$permission]);
    }
    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('code', ['pricing_lists.view','pricing_lists.manage','pricing_lists.approve','pricing_lists.overrides','pricing_lists.assign'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::table('stores',fn(Blueprint $table)=>$table->dropConstrainedForeignId('price_list_id'));
        Schema::table('branches',fn(Blueprint $table)=>$table->dropConstrainedForeignId('default_price_list_id'));
        Schema::dropIfExists('product_price_overrides');
        Schema::table('price_lists',function(Blueprint $table):void{$table->dropUnique('price_lists_company_number_unique');$table->dropColumn(['list_number','percentage_increase','effective_from','effective_to']);});
    }
};
