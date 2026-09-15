<?php

declare(strict_types=1);

namespace App\Modules\CashControl\Models;

use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ExpenseCategory extends Model
{
    protected $fillable = ['company_id', 'code', 'name_ar', 'name_en', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
}
