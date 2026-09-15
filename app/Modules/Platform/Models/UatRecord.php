<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UatRecord extends Model
{
    protected $fillable = ['uat_batch_id', 'table_name', 'record_id', 'delete_order'];
    public function batch(): BelongsTo { return $this->belongsTo(UatBatch::class, 'uat_batch_id'); }
}
