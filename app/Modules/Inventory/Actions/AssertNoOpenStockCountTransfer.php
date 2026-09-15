<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Actions;
use App\Modules\Inventory\Models\StockCount;
use InvalidArgumentException;
final class AssertNoOpenStockCountTransfer {
 public function execute(int ...$storeIds):void {
  $blocking=StockCount::query()->whereIn('status',['in_progress','entry_closed','submitted','recount_required'])->whereHas('locations',fn($q)=>$q->whereIn('store_id',array_unique($storeIds)))->lockForUpdate()->first();
  if($blocking) throw new InvalidArgumentException(__('Transfer blocked by open count session :session.', ['session'=>$blocking->count_number]));
 }
}
