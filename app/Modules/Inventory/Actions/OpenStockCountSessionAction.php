<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
final class OpenStockCountSessionAction { public function execute(int $id):StockCount { Gate::authorize('stock_counts.open'); return DB::transaction(function()use($id):StockCount{$count=StockCount::query()->with('locations')->lockForUpdate()->findOrFail($id);if($count->status!=='draft')throw new InvalidArgumentException(__('Only a draft count session can be opened.'));if($count->locations->isEmpty())throw new InvalidArgumentException(__('A count session needs at least one location.'));$at=now();foreach($count->locations as $location){app(AssertInventoryStoreScope::class)->execute((int)$location->store_id);$location->update(['snapshot_at'=>$at]);$query=Product::query()->sellable();if($count->scope_type==='category')$query->where('category_id',$count->category_id);if($count->scope_type==='supplier')$query->whereHas('productSuppliers',fn($q)=>$q->where('supplier_id',$count->supplier_id));$query->select('id')->chunkById(500,function($products)use($count,$location):void{$balances=StockBalance::query()->where('store_id',$location->store_id)->whereIn('product_id',$products->pluck('id'))->pluck('on_hand','product_id');foreach($products as $product)$count->lines()->firstOrCreate(['store_id'=>$location->store_id,'product_id'=>$product->id],['reference_on_hand'=>$balances[$product->id]??'0','is_counted'=>false]);});}$count->update(['status'=>'in_progress','reference_at'=>$at,'opened_at'=>$at,'opened_by'=>Auth::id(),'lock_version'=>$count->lock_version+1]);app(RecordAuditEvent::class)->execute('inventory','open_stock_count_session',$count,null,$count->only(['status','opened_at','opened_by']),branchId:$count->branch_id,metadata:['location_ids'=>$count->locations->pluck('store_id')->all()]);return $count->fresh(['locations.store','members.user']);});}}
