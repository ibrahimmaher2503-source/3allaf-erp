<?php

namespace App\Modules\Pricing\Data;

final readonly class ResolvedListPrice
{
    public function __construct(public int $priceListId, public string $priceListCode, public string $basePrice, public string $calculatedPrice, public string $finalPrice, public bool $overridden) {}
    public function snapshot(): array { return ['price_list_id' => $this->priceListId, 'price_list_code' => $this->priceListCode, 'base_price' => $this->basePrice, 'resolved_price' => $this->finalPrice, 'is_override' => $this->overridden]; }
}
