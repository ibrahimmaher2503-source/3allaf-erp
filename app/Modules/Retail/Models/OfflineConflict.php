<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OfflineConflict extends Model
{
    protected $fillable = ['offline_transaction_id', 'field', 'local_value', 'server_value', 'disposition', 'reviewed_by', 'reason', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    /** @return BelongsTo<OfflineTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(OfflineTransaction::class, 'offline_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
