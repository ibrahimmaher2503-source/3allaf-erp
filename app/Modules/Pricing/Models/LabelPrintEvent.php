<?php

namespace App\Modules\Pricing\Models;

use App\Models\User;
use App\Modules\Platform\Models\PrinterConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelPrintEvent extends Model
{
    protected $fillable = [
        'label_queue_id',
        'printer_configuration_id',
        'user_id',
        'event_type',
        'idempotency_key',
        'quantity',
        'copies',
        'reason',
        'printed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'copies' => 'integer',
        'printed_at' => 'datetime',
    ];

    /** @return BelongsTo<LabelQueue, $this> */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(LabelQueue::class, 'label_queue_id');
    }

    /** @return BelongsTo<PrinterConfiguration, $this> */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(PrinterConfiguration::class, 'printer_configuration_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
