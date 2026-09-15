<?php
namespace App\Modules\Inventory\Models;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class StockCountLocation extends Model {
    protected $fillable=['stock_count_id','store_id','snapshot_at'];
    protected $casts=['snapshot_at'=>'datetime'];
    public function count(): BelongsTo { return $this->belongsTo(StockCount::class,'stock_count_id'); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}
