<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SetupDecision extends Model
{
    protected $fillable = ['company_id', 'step_key', 'decision', 'decided_by', 'decided_at'];

    protected $casts = ['decided_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Setup decisions are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Setup decisions are append-only.'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
