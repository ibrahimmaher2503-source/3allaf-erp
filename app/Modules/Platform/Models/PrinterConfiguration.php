<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterConfiguration extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'store_id',
        'printer_type',
        'paper_size',
        'template_name',
        'print_template_id',
        'connection_type',
        'ip_address',
        'port',
        'is_default',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'port' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function printTemplate(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereNull('branch_id')->whereNull('store_id')
                ->orWhereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }

    public static function resolveForScope(?int $branchId, ?int $storeId = null, ?string $templateName = null): ?self
    {
        if ($storeId !== null && $branchId === null) {
            return null;
        }

        return self::query()
            ->where('status', 'active')
            ->when($templateName !== null, fn ($query) => $query->where('template_name', $templateName))
            ->where(function ($query) use ($branchId, $storeId): void {
                if ($storeId !== null) {
                    $query->where('branch_id', $branchId)->where('store_id', $storeId)
                        ->orWhereNull('branch_id')->whereNull('store_id');
                } elseif ($branchId !== null) {
                    $query->where('branch_id', $branchId)->whereNull('store_id')
                        ->orWhereNull('branch_id')->whereNull('store_id');
                } else {
                    $query->whereNull('branch_id')->whereNull('store_id');
                }
            })
            ->orderByDesc('store_id')
            ->orderByDesc('branch_id')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }
}
