<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Platform\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseOrderDocument extends Model
{
    protected $fillable = ['purchase_order_id', 'attachment_id', 'version', 'document_status', 'locale', 'content_sha256', 'generated_by', 'generated_at', 'frozen_at'];
    protected $casts = ['version' => 'integer', 'generated_at' => 'datetime', 'frozen_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function attachment(): BelongsTo { return $this->belongsTo(Attachment::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
}
