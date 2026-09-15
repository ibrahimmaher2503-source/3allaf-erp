<?php
namespace App\Modules\Inventory\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class StockCountMember extends Model {
    protected $fillable=['stock_count_id','user_id','role'];
    public function count(): BelongsTo { return $this->belongsTo(StockCount::class,'stock_count_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
