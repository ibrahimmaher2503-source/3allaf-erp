<?php

namespace App\Modules\Pricing\Data;

final readonly class ResolvedListPrice
{
    public function __construct(public int $priceListId, public string $priceListCode, public string $basePrice, public string $calculatedPrice, public string $finalPrice, public bool $overridden, public ?int $productUnitId = null, public ?int $overrideId = null, public ?int $baseOverrideId = null) {}

    public function snapshot(): array
    {
        return ['price_list_id' => $this->priceListId, 'price_list_code' => $this->priceListCode, 'product_unit_id' => $this->productUnitId, 'base_price' => $this->basePrice, 'resolved_price' => $this->finalPrice, 'is_override' => $this->overridden, 'override_id' => $this->overrideId, 'base_override_id' => $this->baseOverrideId];
    }
}
