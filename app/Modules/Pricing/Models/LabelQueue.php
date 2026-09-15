<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelQueue extends Model
{
    protected $fillable = [
        'price_version_id',
        'price_line_id',
        'product_id',
        'store_id',
        'branch_id',
        'printer_configuration_id',
        'required_quantity',
        'printed_quantity',
        'status',
        'template_name',
        'paper_size',
        'generation_key',
        'notes',
    ];

    protected $casts = [
        'required_quantity' => 'integer',
        'printed_quantity' => 'integer',
    ];

    /** @return BelongsTo<PriceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceVersion::class, 'price_version_id');
    }

    /** @return BelongsTo<PriceLine, $this> */
    public function priceLine(): BelongsTo
    {
        return $this->belongsTo(PriceLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<PrinterConfiguration, $this> */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(PrinterConfiguration::class, 'printer_configuration_id');
    }

    /** @return HasMany<LabelPrintEvent, $this> */
    public function printEvents(): HasMany
    {
        return $this->hasMany(LabelPrintEvent::class);
    }
}
