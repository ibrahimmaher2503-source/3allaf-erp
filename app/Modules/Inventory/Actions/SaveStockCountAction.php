<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SaveStockCountAction
{
    /** @param array<string,mixed> $data @param array<int,int> $unusedLegacyProductIds */
    public function execute(array $data, array $unusedLegacyProductIds = [], ?int $id = null, ?int $expectedVersion = null): StockCount
    {
        Gate::authorize($id === null ? 'stock_counts.create' : 'stock_counts.edit');
        return DB::transaction(function () use ($data, $id, $expectedVersion): StockCount {
            $actor=Auth::user(); abort_unless($actor instanceof User,403);
            $count=$id===null?null:StockCount::query()->with(['locations','members'])->lockForUpdate()->findOrFail($id);
            if($count!==null&&$count->status!=='draft') throw new InvalidArgumentException(__('Only draft count sessions can be edited.'));
            if($count!==null&&$expectedVersion!==null&&$count->lock_version!==$expectedVersion) throw new InvalidArgumentException(__('This stock count changed in another session. Please reload before saving.'));
            $branch=Branch::query()->visibleTo($actor)->whereKey((int)($data['branch_id']??0))->where('status','active')->firstOrFail();
            $locationIds=array_values(array_unique(array_map('intval',(array)($data['location_ids']??[]))));
            if($locationIds===[]) throw new InvalidArgumentException(__('Select at least one warehouse or sales outlet.'));
            $locations=Store::query()->visibleTo($actor)->where('company_id',$branch->company_id)->where('branch_id',$branch->id)->where('status','active')->whereIn('type',['warehouse','selling'])->whereIn('id',$locationIds)->lockForUpdate()->get();
            if($locations->count()!==count($locationIds)) throw new InvalidArgumentException(__('Every count location must be active, authorized, and belong to the selected branch.'));
            $memberIds=array_values(array_unique(array_map('intval',(array)($data['member_ids']??[]))));
            if($memberIds===[]) throw new InvalidArgumentException(__('Select at least one authorized count-team member.'));
            $members=User::query()->whereIn('id',$memberIds)->where('status','active')->get();
            if($members->count()!==count($memberIds)) throw new InvalidArgumentException(__('Every count-team member must be active.'));
            foreach($members as $member){if(!$member->is_super_admin&&(!$member->hasPermission('stock_counts.edit')||$locations->contains(fn(Store $store):bool=>!Store::query()->visibleTo($member)->whereKey($store->id)->exists()))) throw new InvalidArgumentException(__('A selected team member is not eligible for every selected location.'));}
            $managerId=(int)($data['manager_id']??$actor->id); $manager=User::query()->whereKey($managerId)->where('status','active')->firstOrFail();
            if(!$manager->is_super_admin&&!$manager->hasPermission('stock_counts.close')) throw new InvalidArgumentException(__('The selected session manager cannot close count sessions.'));
            $countType=($data['count_type']??'partial')==='full'?'full':'partial'; $scopeType=(string)($data['scope_type']??($countType==='full'?'full':'partial'));
            if(!in_array($scopeType,['full','category','supplier','partial'],true)) throw new InvalidArgumentException(__('Select a supported count scope.'));
            $categoryId=filled($data['category_id']??null)?(int)$data['category_id']:null; $supplierId=filled($data['supplier_id']??null)?(int)$data['supplier_id']:null;
            if($scopeType==='category'&&!Category::query()->whereKey($categoryId)->where('status','active')->exists()) throw new InvalidArgumentException(__('A valid category is required for a category count.'));
            if($scopeType==='supplier'&&!Supplier::query()->whereKey($supplierId)->where('status','active')->exists()) throw new InvalidArgumentException(__('A valid supplier is required for a supplier count.'));
            $attributes=['count_type'=>$countType,'scope_type'=>$scopeType,'branch_id'=>$branch->id,'store_id'=>$locations->first()->id,'category_id'=>$categoryId,'supplier_id'=>$supplierId,'assigned_to'=>$members->first()->id,'manager_id'=>$manager->id,'notes'=>trim((string)($data['notes']??''))?:null];
            if($count===null){$count=StockCount::query()->create($attributes+['count_number'=>app(AllocateDocumentNumber::class)->executeForBranch('stock_count',(int)$branch->id),'status'=>'draft','created_by'=>$actor->id,'idempotency_key'=>(string)($data['idempotency_key']??'stock-count:'.Str::uuid()),'lock_version'=>1]);$event='create_stock_count_session';$before=null;}
            else{$before=$count->only(['branch_id','scope_type','manager_id','lock_version']);$count->update($attributes+['lock_version'=>$count->lock_version+1]);$count->locations()->delete();$count->members()->delete();$count->assignments()->delete();$event='update_stock_count_session';}
            foreach($locations as $location)$count->locations()->create(['store_id'=>$location->id,'snapshot_at'=>now()]);
            foreach($members as $member)$count->members()->create(['user_id'=>$member->id,'role'=>$member->id===$manager->id?'manager':'counter']);
            foreach((array)($data['assignments']??[]) as $assignment){$storeId=(int)($assignment['store_id']??0);$userId=(int)($assignment['user_id']??0);$zone=trim((string)($assignment['zone']??'all'))?:'all';if(!in_array($storeId,$locationIds,true)||!in_array($userId,$memberIds,true))throw new InvalidArgumentException(__('Count assignments must use selected locations and team members.'));$count->assignments()->create(['store_id'=>$storeId,'user_id'=>$userId,'zone'=>$zone]);}
            app(RecordAuditEvent::class)->execute('inventory',$event,$count,$before,$count->only(['count_number','branch_id','scope_type','status','manager_id','lock_version']),branchId:$branch->id,metadata:['location_ids'=>$locationIds,'member_ids'=>$memberIds]);
            return $count->fresh(['locations.store','members.user','assignments.store','assignments.user']);
        });
    }
}
