<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'parent_id',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Order categories as a deterministic pre-order tree.
     *
     * Sort order belongs to each sibling set: every node is followed by its
     * ordered descendants before the next root/sibling is rendered. The
     * padded path keeps numeric ordering stable without loading an unbounded
     * category collection into Livewire.
     */
    public function scopeHierarchical(Builder $query): Builder
    {
        $derivedTable = <<<'SQL'
(
    WITH RECURSIVE category_tree AS (
        SELECT
            c.id,
            c.parent_id,
            CAST(CONCAT(LPAD(c.sort_order, 10, '0'), '-', LPAD(c.id, 10, '0')) AS CHAR(255)) AS hierarchy_path,
            0 AS hierarchy_depth
        FROM categories c
        WHERE c.parent_id IS NULL

        UNION ALL

        SELECT
            child.id,
            child.parent_id,
            CAST(CONCAT(parent.hierarchy_path, '/', LPAD(child.sort_order, 10, '0'), '-', LPAD(child.id, 10, '0')) AS CHAR(255)) AS hierarchy_path,
            parent.hierarchy_depth + 1 AS hierarchy_depth
        FROM categories child
        INNER JOIN category_tree parent ON parent.id = child.parent_id
    )
    SELECT categories.*, category_tree.hierarchy_path, category_tree.hierarchy_depth
    FROM categories
    INNER JOIN category_tree ON category_tree.id = categories.id
) AS categories
SQL;

        return $query
            ->from(DB::raw($derivedTable))
            ->orderBy('hierarchy_path');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
