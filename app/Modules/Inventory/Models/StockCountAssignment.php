<?php
namespace App\Modules\Inventory\Models;
use App\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class StockCountAssignment extends Model {
    protected $fillable=['stock_count_id','store_id','user_id','zone'];
    public function count(): BelongsTo { return $this->belongsTo(StockCount::class,'stock_count_id'); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
