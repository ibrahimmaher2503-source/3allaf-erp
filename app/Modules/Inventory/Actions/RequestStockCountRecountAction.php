<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
final class RequestStockCountRecountAction { public function execute(int $id,array $lineIds,string $reason):StockCount {Gate::authorize('stock_counts.request_recount');return DB::transaction(function()use($id,$lineIds,$reason):StockCount{$count=StockCount::query()->with('lines')->lockForUpdate()->findOrFail($id);if($count->status!=='submitted')throw new InvalidArgumentException(__('Only a submitted count session can be sent for recount.'));if(trim($reason)===''||$lineIds===[])throw new InvalidArgumentException(__('Select variance lines and provide a recount reason.'));$selected=$count->lines()->whereIn('id',array_map('intval',$lineIds))->lockForUpdate()->get();if($selected->count()!==count(array_unique($lineIds)))abort(404);foreach($selected as $line)$line->update(['completed_at'=>null,'completed_by'=>null,'recount_number'=>$line->recount_number+1,'notes'=>$reason]);$count->update(['status'=>'in_progress','submitted_at'=>null,'entry_closed_at'=>null,'lock_version'=>$count->lock_version+1]);app(RecordAuditEvent::class)->execute('inventory','request_stock_count_recount',$count,null,['status'=>'in_progress'],branchId:$count->branch_id,reasonText:$reason,metadata:['line_ids'=>$selected->modelKeys(),'requested_by'=>Auth::id()]);return $count;});}}
