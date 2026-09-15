<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
final class CancelStockCountSessionAction { public function execute(int $id,string $reason):StockCount {Gate::authorize('stock_counts.cancel');return DB::transaction(function()use($id,$reason):StockCount{$count=StockCount::query()->lockForUpdate()->findOrFail($id);if(!in_array($count->status,['draft','in_progress','submitted','recount_required'],true))throw new InvalidArgumentException(__('This count session can no longer be cancelled.'));if(trim($reason)==='')throw new InvalidArgumentException(__('A cancellation reason is required.'));$before=$count->only(['status','lock_version']);$count->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>Auth::id(),'notes'=>trim($reason),'lock_version'=>$count->lock_version+1]);app(RecordAuditEvent::class)->execute('inventory','cancel_stock_count_session',$count,$before,$count->only(['status','cancelled_at','cancelled_by']),branchId:$count->branch_id,reasonText:$reason);return $count;});}}
