<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OpeningInventoryDocument extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['company_id', 'branch_id', 'store_id', 'document_number', 'document_date', 'status', 'notes', 'created_by', 'approved_by', 'reversed_by', 'reversal_of_id', 'approved_at', 'reversed_at', 'reversal_reason', 'idempotency_key', 'lock_version'];
    protected $casts = ['document_date' => 'date', 'approved_at' => 'datetime', 'reversed_at' => 'datetime', 'lock_version' => 'integer'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(OpeningInventoryLine::class); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
}
