<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Enums\TaxTreatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxSetting extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'rate',
        'treatment',
        'is_default',
        'is_tax_inclusive',
        'tax_number',
        'effective_from',
        'effective_to',
        'status',
        'policy_notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_tax_inclusive' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function treatmentLabel(): string
    {
        return TaxTreatment::tryFrom((string) $this->treatment)?->label() ?? __('Standard');
    }

    public function products(): HasMany
    {
        return $this->hasMany(\App\Modules\Catalog\Models\Product::class);
    }
}
