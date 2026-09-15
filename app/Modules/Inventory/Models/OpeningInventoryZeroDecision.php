<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OpeningInventoryZeroDecision extends Model
{
    protected $fillable = ['company_id', 'decided_by', 'reason', 'decided_at'];
    protected $casts = ['decided_at' => 'datetime'];
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
