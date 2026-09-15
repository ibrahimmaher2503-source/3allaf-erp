<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class SettingsAuditLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Settings audit records are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Settings audit records are append-only.'));
    }

    protected $fillable = [
        'correlation_id',
        'user_id',
        'user_name',
        'action',
        'setting_type',
        'setting_id',
        'changes',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];
}
