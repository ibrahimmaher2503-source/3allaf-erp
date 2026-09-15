<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CustomerPolicySettingVersion extends Model
{
    protected $table = 'customer_policy_setting_versions';

    protected $fillable = [
        'key',
        'value',
        'value_type',
        'version',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Customer policy settings are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Customer policy settings are append-only.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
