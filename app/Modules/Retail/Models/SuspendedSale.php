<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SuspendedSale extends Model
{
    protected $fillable = ['sale_id', 'resume_code', 'created_by', 'status', 'resumed_at'];

    protected $casts = ['resumed_at' => 'datetime'];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
