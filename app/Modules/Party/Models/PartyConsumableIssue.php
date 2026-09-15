<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class PartyConsumableIssue extends Model
{
    protected $fillable = ['public_id', 'party_operating_order_id', 'store_id', 'status', 'created_by', 'idempotency_key'];
    protected static function booted(): void
    {
        self::creating(fn (self $issue): string => $issue->public_id ??= (string) Str::uuid());
        self::updating(fn (): never => throw new LogicException('Party consumable issues are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Party consumable issues are immutable.'));
    }
    /** @return BelongsTo<PartyOperatingOrder, $this> */
    public function order(): BelongsTo { return $this->belongsTo(PartyOperatingOrder::class, 'party_operating_order_id'); }
    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** @return HasMany<PartyConsumableIssueLine, $this> */
    public function lines(): HasMany { return $this->hasMany(PartyConsumableIssueLine::class); }
}
