<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Services\ProductDependencyService;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Inventory\Models\ProductStoreAssignment;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasFactory;

    private bool $permanentRemovalAuthorized = false;

    protected static function booted(): void
    {
        self::deleting(function (self $product): void {
            if (! $product->permanentRemovalAuthorized) {
                throw new \LogicException('Products must be archived or removed through the guarded product-removal action.');
            }
            if (app(ProductDependencyService::class)->inspect($product)['operational_total'] > 0) {
                throw new \LogicException('Products with operational or historical dependencies cannot be permanently deleted.');
            }
        });
    }

    public function authorizePermanentRemoval(): self
    {
        $this->permanentRemovalAuthorized = true;

        return $this;
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    protected $fillable = [
        'item_code',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'short_description_ar', 'short_description_en', 'full_description_ar', 'full_description_en', 'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en', 'seo_slug', 'publish_visibility', 'sort_order',
        'model_number',
        'product_type',
        'feed_kind',
        'animal_type',
        'protein_percentage',
        'track_batches',
        'track_expiry',
        'has_variations',
        'parent_product_id',
        'variant_signature',
        'variant_sort_order',
        'unit_of_measure',
        'category_id',
        'brand_id',
        'tax_setting_id',
        'originating_supplier_id',
        'status',
        'barcode_mode',
        'barcode_registration_type',
        'average_cost',
        'sale_price',
        'open_price',
        'sell_online',
        'reorder_threshold',
        'dimension_length',
        'dimension_width',
        'dimension_height',
        'dimension_unit',
        'weight',
        'weight_unit',
        'target_age',
        'age_label_id',
        'suitable_gender',
        'gender_id',
        'colour',
        'colour_id',
        'size',
        'character',
        'character_id',
        'key_points_ar',
        'key_points_en',
        'keywords_ar',
        'keywords_en',
        'fractional_quantity',
        'lock_version', 'battery_required', 'battery_details',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'average_cost' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'open_price' => 'boolean',
        'sell_online' => 'boolean',
        'battery_required' => 'boolean',
        'reorder_threshold' => 'decimal:3',
        'dimension_length' => 'decimal:3',
        'dimension_width' => 'decimal:3',
        'dimension_height' => 'decimal:3',
        'weight' => 'decimal:3',
        'fractional_quantity' => 'boolean',
        'has_variations' => 'boolean',
        'variant_sort_order' => 'integer',
        'sort_order' => 'integer',
        'protein_percentage' => 'decimal:2',
        'track_batches' => 'boolean',
        'track_expiry' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function hasIncompleteCard(): bool
    {
        return blank($this->name_ar)
            || blank($this->model_number)
            || $this->category_id === null
            || $this->sale_price === null
            || bccomp((string) $this->sale_price, '0', 2) <= 0
            || ($this->product_type !== 'service' && ! ($this->relationLoaded('barcodes')
                ? $this->barcodes->contains('status', 'active')
                : $this->barcodes()->where('status', 'active')->exists()));
    }

    public function ageLabel(): BelongsTo
    {
        return $this->belongsTo(AgeLabel::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function colourLookup(): BelongsTo
    {
        return $this->belongsTo(Colour::class, 'colour_id');
    }

    public function characterLookup(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }

    public function ages(): BelongsToMany
    {
        return $this->belongsToMany(AgeLabel::class, 'product_ages');
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'product_characters');
    }

    public function colours(): BelongsToMany
    {
        return $this->belongsToMany(Colour::class, 'product_colours');
    }

    public function genders(): BelongsToMany
    {
        return $this->belongsToMany(Gender::class, 'product_genders');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function taxSetting(): BelongsTo
    {
        return $this->belongsTo(TaxSetting::class);
    }

    public function originatingSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'originating_supplier_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    public function priceOverrides(): HasMany
    {
        return $this->hasMany(ProductPriceOverride::class);
    }

    public function storeAssignments(): HasMany
    {
        return $this->hasMany(ProductStoreAssignment::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('status', 'active')
            ->orderByRaw("CASE WHEN role = 'main' THEN 0 ELSE 1 END")
            ->orderBy('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_product_id')
            ->orderBy('variant_sort_order')
            ->orderBy('id');
    }

    public function familyOptionGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionGroup::class, 'product_family_option_groups')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function familyOptionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_family_option_values')
            ->withTimestamps();
    }

    public function variantValues(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class)->orderBy('sort_order');
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'product_units')
            ->withPivot(['conversion_factor', 'is_base_unit', 'is_purchase_unit', 'is_sale_unit'])
            ->withTimestamps();
    }

    public function baseProductUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base_unit', true);
    }

    public function preferredProductSupplier(): HasOne
    {
        return $this->hasOne(ProductSupplier::class)->where('is_preferred', true);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_suppliers')
            ->withPivot([
                'supplier_item_code',
                'is_preferred',
                'last_purchase_price',
                'last_purchase_date',
                'notes',
                'created_by',
                'updated_by',
            ])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeFamiliesAndSimple(Builder $query): Builder
    {
        return $query->whereNull('parent_product_id');
    }

    /**
     * Transaction-facing products only. A family is descriptive and can never be
     * priced, stocked, scanned, purchased, quoted, or sold directly.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('products.status', 'active')->completeCard()->where(function (Builder $scope): void {
            $scope->where(function (Builder $simple): void {
                $simple->whereNull('products.parent_product_id')->where('products.has_variations', false);
            })->orWhere(function (Builder $variant): void {
                $variant->whereNotNull('products.parent_product_id')
                    ->whereHas('parent', fn (Builder $family): Builder => $family->where('status', 'active')->where('has_variations', true));
            });
        });
    }

    public function scopeCompleteCard(Builder $query): Builder
    {
        return $query->whereNotNull('products.category_id')->whereNotNull('products.model_number')->where('products.model_number', '<>', '')
            ->whereNotNull('products.name_ar')->where('products.name_ar', '<>', '')->where('products.sale_price', '>', 0)
            ->where(fn (Builder $scope) => $scope->where('products.product_type', 'service')->orWhereHas('barcodes', fn (Builder $codes) => $codes->where('status', 'active')));
    }

    public function scopeIncompleteCard(Builder $query): Builder
    {
        return $query->where(fn (Builder $scope) => $scope->whereNull('products.category_id')->orWhereNull('products.model_number')->orWhere('products.model_number', '')
            ->orWhereNull('products.name_ar')->orWhere('products.name_ar', '')->orWhereNull('products.sale_price')->orWhere('products.sale_price', '<=', 0)
            ->orWhere(fn (Builder $stock) => $stock->where('products.product_type', '<>', 'service')->whereDoesntHave('barcodes', fn (Builder $codes) => $codes->where('status', 'active'))));
    }

    public function isFamily(): bool
    {
        return $this->parent_product_id === null && (bool) $this->has_variations;
    }

    public function isVariant(): bool
    {
        return $this->parent_product_id !== null;
    }

    public function isSellable(): bool
    {
        if ($this->status !== 'active' || $this->hasIncompleteCard()) {
            return false;
        }

        if ($this->isVariant()) {
            $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();

            return $parent?->status === 'active' && (bool) $parent?->has_variations;
        }

        return ! $this->has_variations;
    }

    public function family(): self
    {
        return $this->isVariant() ? ($this->parent ?? $this->parent()->firstOrFail()) : $this;
    }

    /** @return Collection<int, ProductImage> */
    public function effectiveImages(): Collection
    {
        $own = $this->relationLoaded('images') ? $this->images : $this->images()->with('attachment')->get();
        if ($own->isNotEmpty() || ! $this->isVariant()) {
            return $own;
        }

        $family = $this->family();

        return $family->relationLoaded('images') ? $family->images : $family->images()->with('attachment')->get();
    }

    public function effectiveMainImage(): ?ProductImage
    {
        $images = $this->effectiveImages();

        return $images->firstWhere('role', 'main') ?? $images->first();
    }

    public function localizedVariationLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $values = $this->relationLoaded('variantValues')
            ? $this->variantValues
            : $this->variantValues()->with(['group', 'value'])->get();

        return $values->map(function (ProductVariantValue $selection) use ($locale): string {
            $group = str_starts_with($locale, 'ar') ? $selection->group?->name_ar : $selection->group?->name_en;
            $value = str_starts_with($locale, 'ar') ? $selection->value?->name_ar : $selection->value?->name_en;

            return trim((string) $group).': '.trim((string) $value);
        })->implode(' · ');
    }

    /** @return array<int, array<string, int|string|null>>|null */
    public function variantSnapshot(): ?array
    {
        if (! $this->isVariant()) {
            return null;
        }

        $values = $this->relationLoaded('variantValues')
            ? $this->variantValues
            : $this->variantValues()->with(['group', 'value'])->get();

        return $values->map(fn (ProductVariantValue $selection): array => [
            'group_code' => (string) $selection->group?->code,
            'group_ar' => (string) $selection->group?->name_ar,
            'group_en' => (string) $selection->group?->name_en,
            'value_code' => (string) $selection->value?->code,
            'value_ar' => (string) $selection->value?->name_ar,
            'value_en' => (string) $selection->value?->name_en,
            'colour_swatch' => $selection->value?->colour_swatch,
        ])->values()->all();
    }
}
