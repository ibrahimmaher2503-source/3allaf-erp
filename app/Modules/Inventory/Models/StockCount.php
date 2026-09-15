<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockCount extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['count_number', 'count_type', 'scope_type', 'branch_id', 'store_id', 'category_id', 'supplier_id', 'status', 'reference_at', 'opened_at', 'submitted_at', 'entry_closed_at', 'reconciled_at', 'cancelled_at', 'created_by', 'assigned_to', 'manager_id', 'opened_by', 'approved_by', 'cancelled_by', 'idempotency_key', 'lock_version', 'notes'];

    protected $casts = ['reference_at' => 'datetime', 'opened_at' => 'datetime', 'submitted_at' => 'datetime', 'entry_closed_at' => 'datetime', 'reconciled_at' => 'datetime', 'cancelled_at' => 'datetime', 'lock_version' => 'integer'];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function locations(): HasMany { return $this->hasMany(StockCountLocation::class); }
    public function members(): HasMany { return $this->hasMany(StockCountMember::class); }
    public function assignments(): HasMany { return $this->hasMany(StockCountAssignment::class); }
    public function contributions(): HasMany { return $this->hasMany(StockCountContribution::class); }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
