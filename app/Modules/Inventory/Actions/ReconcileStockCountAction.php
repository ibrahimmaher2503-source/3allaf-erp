<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryAdjustmentLine;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReconcileStockCountAction
{
 public function __construct(private readonly AssertInventoryStoreScope $scope) {}
 public function execute(int $id):StockCount {
  Gate::authorize('stock_counts.approve'); Gate::authorize('stock_counts.post_adjustment');
  return DB::transaction(function()use($id):StockCount{
   $count=StockCount::query()->with(['lines','locations'])->lockForUpdate()->findOrFail($id);
   if($count->status==='reconciled')return $count->fresh(['locations.store','lines.product']);
   if($count->status!=='submitted')throw new InvalidArgumentException(__('Only submitted stock counts can be reconciled.'));
   if($count->members()->where('user_id',Auth::id())->where('role','counter')->exists())throw new InvalidArgumentException(__('A stock counter cannot approve a session in which they participated.'));
   foreach($count->lines as $line){
    $this->scope->execute((int)$line->store_id);
    StockBalance::query()->where('product_id',$line->product_id)->where('store_id',$line->store_id)->lockForUpdate()->first();
    $after=StockMovement::query()->where('product_id',$line->product_id)->where('store_id',$line->store_id)->where('posted_at','>',$line->completed_at)->sum('quantity');
    if(bccomp((string)$after,(string)$line->movement_after_completion,6)!==0)throw new InvalidArgumentException(__('Stock changed after count entry closed. Close the session again before approval.'));
   }
   $approval=ApprovalRecord::query()->where('source_type','stock_counts')->where('source_id',(string)$count->id)->where('requested_action','reconcile')->where('approval_state',ApprovalState::Pending->value)->lockForUpdate()->firstOrFail();
   $adjustmentIds=[];
   foreach($count->lines->groupBy('store_id') as $storeId=>$lines){
    $adjustment=InventoryAdjustment::query()->create(['adjustment_number'=>app(AllocateDocumentNumber::class)->executeForBranch('inventory_adjustment',(int)$count->branch_id),'store_id'=>$storeId,'adjustment_type'=>'count_adjustment','status'=>'approved','reason_code'=>'count_variance','reason_notes'=>__('Approved stock-count session :session reconciliation.',['session'=>$count->count_number]),'allow_negative'=>false,'created_by'=>$count->created_by,'submitted_by'=>$count->manager_id,'approved_by'=>Auth::id(),'submitted_at'=>$count->submitted_at,'approved_at'=>now(),'idempotency_key'=>'COUNT-ADJUSTMENT:'.$count->id.':'.$storeId,'lock_version'=>2,'notes'=>__('Generated from an approved multi-location count session.')]);
    $adjustmentIds[]=$adjustment->id;
    foreach($lines as $line){if(!$line->is_counted||$line->variance_quantity===null||bccomp((string)$line->variance_quantity,'0',6)===0)continue;$adjustmentLine=InventoryAdjustmentLine::query()->create(['inventory_adjustment_id'=>$adjustment->id,'product_id'=>$line->product_id,'quantity_delta'=>$line->variance_quantity,'unit_cost'=>null,'before_on_hand'=>$line->expected_quantity,'after_on_hand'=>bcadd((string)$line->expected_quantity,(string)$line->variance_quantity,6)]);app(PostInventoryMovement::class)->execute($line->product_id,(int)$storeId,(string)$line->variance_quantity,'count_reconciliation',null,'COUNT-MOVEMENT:'.$count->id.':'.$line->id,StockCount::class,$count->id,$adjustmentLine->id);}
   }
   $before=$count->only(['status','lock_version']);$count->update(['status'=>'reconciled','approved_by'=>Auth::id(),'reconciled_at'=>now(),'lock_version'=>$count->lock_version+1]);
   app(ApproveRequest::class)->execute($approval,(string)$before['lock_version'],decisionNote:__('Stock-count reconciliation approved.'));
   app(RecordAuditEvent::class)->execute('inventory','reconcile_stock_count_session',$count,$before,$count->only(['status','approved_by','reconciled_at','lock_version']),branchId:$count->branch_id,metadata:['adjustment_ids'=>$adjustmentIds,'location_ids'=>$count->locations->pluck('store_id')->all()]);
   return $count->fresh(['locations.store','lines.product']);
  });
 }
}
