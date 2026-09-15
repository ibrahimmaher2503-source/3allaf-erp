<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
final class CompleteStockCountLineAction { public function execute(int $lineId,bool $explicitZero=false):StockCountLine { Gate::authorize('stock_counts.participate');return DB::transaction(function()use($lineId,$explicitZero):StockCountLine{$line=StockCountLine::query()->with('count')->lockForUpdate()->findOrFail($lineId);$count=$line->count;if($count->status!=='in_progress')throw new InvalidArgumentException(__('Count input is closed for this session.'));if(!$count->members()->where('user_id',Auth::id())->exists())abort(404);app(AssertInventoryStoreScope::class)->execute((int)$line->store_id);if(!$line->is_counted&&!$explicitZero)throw new InvalidArgumentException(__('Not counted is not zero. Confirm zero explicitly or record a contribution.'));if($explicitZero&&$count->contributions()->where('store_id',$line->store_id)->where('product_id',$line->product_id)->exists())throw new InvalidArgumentException(__('A product with count contributions cannot be confirmed as zero.'));$line->update(['counted_quantity'=>$explicitZero?'0':$line->counted_quantity,'is_counted'=>true,'explicit_zero'=>$explicitZero,'completed_at'=>now(),'completed_by'=>Auth::id()]);app(RecordAuditEvent::class)->execute('inventory','complete_stock_count_line',$line,null,$line->only(['counted_quantity','explicit_zero','completed_at']),branchId:$count->branch_id,storeId:$line->store_id);return $line;});}}
