<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Services\PosCalculationService;
use InvalidArgumentException;

final class PosCartSnapshot
{
    public function __construct(private readonly EffectivePriceResolver $prices, private readonly PosCalculationService $calculator) {}

    /** @return array<string, mixed> */
    public function build(PosContext $context): array
    {
        if (! $context->isReady() || $context->store === null) {
            return [
                'cart' => collect(request()->session()->get('pos.cart', [])),
                'products' => collect(),
                'lines' => [],
                'preview' => null,
                'error' => $context->disabledReason ?? __('POS is disabled until you open an assigned cashier shift.'),
                'taxApplicable' => false,
                'taxSetting' => null,
            ];
        }

        $store = $context->store;
        $cart = collect(request()->session()->get('pos.cart', []));
        $ids = $cart->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $products = Product::query()->with(['parent.images.attachment', 'images.attachment', 'variantValues.group', 'variantValues.value', 'productUnits.unit'])->whereIn('id', $ids)->get()->keyBy('id');
        $prices = $this->prices->resolveForStore($ids->all(), (int) $store->id);
        $lines = [];
        $error = null;
        foreach ($cart as $cartLine) {
            $product = $products->get((int) ($cartLine['product_id'] ?? 0));
            $price = $product ? $prices->get($product->id) : null;
            if (! $product?->isSellable() || ! $price) {
                $error = __('One or more cart SKUs became inactive, unpriced, or invalid. The cart was preserved.');

                continue;
            }
            $productUnit = filled($cartLine['product_unit_id'] ?? null) ? $product->productUnits->firstWhere('id', (int) $cartLine['product_unit_id']) : null;
            $factor = (string) ($productUnit?->conversion_factor ?? '1');
            $unitPrice = isset($cartLine['open_price_amount']) ? (string) $cartLine['open_price_amount'] : bcmul((string) $price->amount, $factor, 4);
            $lines[] = ['product' => $product, 'product_unit' => $productUnit, 'quantity' => (string) $cartLine['quantity'], 'unit_price' => $unitPrice, 'discount_amount' => (string) ($cartLine['discount_amount'] ?? '0.00'), 'price' => $price, 'cart' => $cartLine];
        }
        $taxApplicable = (bool) request()->session()->get('pos.tax_applicable', false);
        $tax = TaxSetting::query()->where('status', 'active')->where('is_default', true)->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))->first();
        $taxSetting = $tax;
        try {
            $preview = $lines === [] ? null : $this->calculator->calculate(array_map(fn (array $line): array => ['quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'discount_amount' => $line['discount_amount']], $lines), '0.00', $taxApplicable ? ['applicable' => true, 'rate' => $taxSetting?->rate, 'inclusive' => (bool) ($taxSetting?->is_tax_inclusive ?? false)] : ['applicable' => false]);
        } catch (InvalidArgumentException $exception) {
            $preview = null;
            $error = \App\Support\UserSafeError::message($exception);
        }

        return compact('cart', 'products', 'lines', 'preview', 'error', 'taxApplicable', 'taxSetting');
    }
}
