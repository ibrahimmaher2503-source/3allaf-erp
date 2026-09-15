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
        Schema::table('stock_counts', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable()->after('reference_at');
            $table->timestamp('entry_closed_at')->nullable()->after('submitted_at');
            $table->timestamp('cancelled_at')->nullable()->after('reconciled_at');
            $table->foreignId('opened_by')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->index(['branch_id', 'status', 'opened_at'], 'stock_counts_branch_status_open_idx');
        });

        Schema::create('stock_count_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->timestamp('snapshot_at');
            $table->timestamps();
            $table->unique(['stock_count_id', 'store_id'], 'stock_count_location_unique');
            $table->index(['store_id', 'stock_count_id'], 'stock_count_location_store_idx');
        });

        Schema::create('stock_count_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20)->default('counter');
            $table->timestamps();
            $table->unique(['stock_count_id', 'user_id'], 'stock_count_member_unique');
        });

        Schema::create('stock_count_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('zone', 120)->default('all');
            $table->timestamps();
            $table->unique(['stock_count_id', 'store_id', 'user_id', 'zone'], 'stock_count_assignment_unique');
            $table->index(['stock_count_id', 'store_id', 'zone'], 'stock_count_assignment_overlap_idx');
        });

        Schema::table('stock_count_lines', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->after('stock_count_id')->constrained('stores')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable()->after('counted_at');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->boolean('explicit_zero')->default(false)->after('is_counted');
            $table->decimal('movement_after_completion', 20, 6)->default(0)->after('movement_quantity_after_reference');
            $table->decimal('normalized_closing_quantity', 20, 6)->nullable()->after('counted_quantity');
            $table->index('stock_count_id', 'stock_count_lines_count_fk_idx');
            $table->dropUnique(['stock_count_id', 'product_id']);
            $table->unique(['stock_count_id', 'store_id', 'product_id'], 'stock_count_location_product_unique');
            $table->index(['stock_count_id', 'store_id', 'completed_at'], 'stock_count_line_completion_idx');
        });

        Schema::create('stock_count_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->foreignId('counter_id')->constrained('users')->restrictOnDelete();
            $table->string('input_method', 20);
            $table->string('request_id', 120);
            $table->string('device_id', 120);
            $table->string('reason_code', 80)->nullable();
            $table->foreignId('reverses_contribution_id')->nullable()->constrained('stock_count_contributions')->restrictOnDelete();
            $table->timestamp('contributed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'request_id'], 'stock_count_contribution_request_unique');
            $table->index(['stock_count_id', 'store_id', 'product_id', 'contributed_at'], 'stock_count_contribution_total_idx');
        });

        // Preserve historical records without reinterpreting them. New sessions always
        // write explicit location rows and location-keyed lines.
        DB::table('stock_count_lines')->whereNull('store_id')->update([
            'store_id' => DB::raw('(SELECT store_id FROM stock_counts WHERE stock_counts.id = stock_count_lines.stock_count_id)'),
        ]);
        foreach (['participate','book_quantity_view','open','request_recount','close','approve','correct','post_adjustment','cancel'] as $action) {
            DB::table('permissions')->updateOrInsert(['code'=>'stock_counts.'.$action], ['module'=>'stock_counts','action'=>$action,'sensitivity'=>in_array($action,['open','close','approve','correct','post_adjustment','cancel'],true)?'sensitive':'normal','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        }
        foreach (['warehouse-manager'=>['open','request_recount','close','approve','correct','post_adjustment','cancel','book_quantity_view'],'stock-counter'=>['participate']] as $roleCode=>$actions) {
            $roleId=DB::table('roles')->where('code',$roleCode)->value('id');
            if($roleId) foreach($actions as $action){$permissionId=DB::table('permissions')->where('code','stock_counts.'.$action)->value('id');DB::table('role_permissions')->updateOrInsert(['role_id'=>$roleId,'permission_id'=>$permissionId],['created_at'=>now(),'updated_at'=>now()]);}
        }
    }

    public function down(): void
    {
        $codes=collect(['participate','book_quantity_view','open','request_recount','close','approve','correct','post_adjustment','cancel'])->map(fn($action)=>'stock_counts.'.$action);
        $permissionIds=DB::table('permissions')->whereIn('code',$codes)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id',$permissionIds)->delete();
        DB::table('permissions')->whereIn('id',$permissionIds)->delete();
        Schema::dropIfExists('stock_count_contributions');
        Schema::table('stock_count_lines', function (Blueprint $table): void {
            $table->dropUnique('stock_count_location_product_unique');
            $table->dropIndex('stock_count_line_completion_idx');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn(['completed_at', 'explicit_zero', 'movement_after_completion', 'normalized_closing_quantity']);
            $table->unique(['stock_count_id', 'product_id']);
            $table->dropIndex('stock_count_lines_count_fk_idx');
        });
        Schema::dropIfExists('stock_count_assignments');
        Schema::dropIfExists('stock_count_members');
        Schema::dropIfExists('stock_count_locations');
        if (! Schema::hasIndex('stock_counts', 'stock_counts_branch_id_foreign')) {
            Schema::table('stock_counts', fn (Blueprint $table) => $table->index('branch_id', 'stock_counts_branch_id_foreign'));
        }
        Schema::table('stock_counts', function (Blueprint $table): void {
            $table->dropIndex('stock_counts_branch_status_open_idx');
            $table->dropConstrainedForeignId('opened_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['opened_at', 'entry_closed_at', 'cancelled_at']);
        });
    }
};
