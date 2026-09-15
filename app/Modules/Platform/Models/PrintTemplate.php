<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintTemplate extends Model
{
    protected $fillable = ['company_id', 'code', 'name_ar', 'name_en', 'document_type', 'paper_size', 'language', 'header_text', 'footer_text', 'layout_settings', 'status'];
    protected $casts = ['layout_settings' => 'array'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function printers(): HasMany { return $this->hasMany(PrinterConfiguration::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }
        return $query->whereIn('company_id', Store::visibleTo($user)->select('company_id'));
    }
}
