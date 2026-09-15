<?php
namespace App\Modules\Inventory\Models;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class StockCountContribution extends Model {
    protected $fillable=['stock_count_id','store_id','product_id','quantity','counter_id','input_method','request_id','device_id','reason_code','reverses_contribution_id','contributed_at','metadata'];
    protected $casts=['quantity'=>'decimal:6','contributed_at'=>'datetime','metadata'=>'array'];
    public function count(): BelongsTo { return $this->belongsTo(StockCount::class,'stock_count_id'); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function counter(): BelongsTo { return $this->belongsTo(User::class,'counter_id'); }
}
