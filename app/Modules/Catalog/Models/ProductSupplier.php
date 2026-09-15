<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplier extends Model
{
    use HasFactory;

    protected $table = 'product_suppliers';

    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_item_code',
        'is_preferred',
        'last_purchase_price',
        'last_purchase_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_preferred' => 'boolean',
        'last_purchase_price' => 'decimal:4',
        'last_purchase_date' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
