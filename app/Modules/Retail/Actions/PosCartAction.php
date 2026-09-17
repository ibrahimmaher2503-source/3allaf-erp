<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Services\ConvertProductQuantity;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PosCartAction
{
    public function __construct(
        private readonly EffectivePriceResolver $prices,
        private readonly PosContextResolver $contextResolver,
        private readonly PosOpenOrderManager $openOrders,
        private readonly PosCartSnapshot $snapshot,
        private readonly ConvertProductQuantity $converter,
    ) {}

    public function add(Request $request, User $user, int $productId, string $quantity, ?int $productUnitId = null): void
    {
        $context = $this->context($request, $user);
        $store = $this->store($context);
        $product = Product::query()->sellable()->with(['parent', 'productUnits.unit'])->find($productId);
        if (! $product?->isSellable()) {
            throw new InvalidArgumentException(__('Select an active simple product or a fully resolved variation SKU. Product families cannot be added.'));
        }
        $unit = $this->resolveUnit($product, $productUnitId);
        $customerId = $request->session()->get('pos.customer_id');
        if ($this->prices->resolve($product->id, $store->id, productUnitId: $unit?->id, customerId: $customerId ? (int) $customerId : null) === null) {
            throw new InvalidArgumentException(__('The selected SKU unit has no positive selling price for this customer and store.'));
        }
        $entered = $this->enteredQuantity($product, $unit, $quantity);
        $baseQuantity = $unit ? $this->converter->execute($product, $unit, $entered) : $entered;
        $cart = collect($request->session()->get('pos.cart', []));
        $key = $cart->search(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $product->id);
        if ($key !== false && (int) ($cart[$key]['product_unit_id'] ?? 0) !== (int) ($unit?->id ?? 0)) {
            throw new InvalidArgumentException(__('Change the unit on the existing cart line before adding more.'));
        }
        $existing = $key === false ? '0' : (string) ($cart[$key]['quantity'] ?? '0');
        $requestedTotal = bcadd($existing, $entered, 6);
        $requestedBase = $unit ? $this->converter->execute($product, $unit, $requestedTotal) : $requestedTotal;
        $balance = StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->first();
        $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 6) : '0';
        if (bccomp($available, $requestedBase, 6) < 0) {
            throw new InvalidArgumentException(__('The selected SKU does not have enough available stock.'));
        }

        if ($key === false) {
            $cart->push(['product_id' => $product->id, 'product_unit_id' => $unit?->id, 'quantity' => $entered]);
        } else {
            $line = $cart[$key];
            $line['quantity'] = $requestedTotal;
            $cart[$key] = $line;
        }
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
    }

    public function quantity(Request $request, User $user, int $productId, string $quantity): void
    {
        $context = $this->context($request, $user);
        $store = $this->store($context);
        $product = Product::query()->sellable()->with('productUnits.unit')->find($productId);
        if (! $product) {
            throw new InvalidArgumentException(__('The cart SKU is no longer sellable. The cart was preserved.'));
        }
        $cart = collect($request->session()->get('pos.cart', []));
        $index = $cart->search(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $productId);
        if ($index === false) {
            throw new InvalidArgumentException(__('The requested cart line was not found.'));
        }
        $line = $cart[$index];
        $unit = $this->resolveUnit($product, filled($line['product_unit_id'] ?? null) ? (int) $line['product_unit_id'] : null);
        $entered = $this->enteredQuantity($product, $unit, $quantity);
        $baseQuantity = $unit ? $this->converter->execute($product, $unit, $entered) : $entered;
        $balance = StockBalance::query()->where('store_id', $store->id)->where('product_id', $productId)->first();
        $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 6) : '0';
        if (bccomp($available, $baseQuantity, 6) < 0) {
            throw new InvalidArgumentException(__('The requested quantity exceeds available stock.'));
        }

        $before = (string) ($line['quantity'] ?? '0');
        $line['quantity'] = $entered;
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
        app(RecordAuditEvent::class)->execute(category: 'retail', event: 'pos_cart_quantity_updated', explicitSourceId: 'cart:'.$productId, before: ['quantity' => $before], after: ['quantity' => $entered], branchId: $store->branch_id, storeId: $store->id, metadata: ['product_id' => $productId, 'actor_id' => $user->id]);
    }

    public function unit(Request $request, User $user, int $productId, int $productUnitId): void
    {
        $context = $this->context($request, $user);
        $product = Product::query()->sellable()->with('productUnits.unit')->findOrFail($productId);
        $unit = $this->resolveUnit($product, $productUnitId) ?? throw new InvalidArgumentException(__('The selected unit is unavailable.'));
        $cart = collect($request->session()->get('pos.cart', []));
        $index = $cart->search(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $productId);
        if ($index === false) {
            throw new InvalidArgumentException(__('The requested cart line was not found.'));
        }
        $line = $cart[$index];
        $line['product_unit_id'] = $unit->id;
        $line['quantity'] = '1';
        foreach (['open_price_amount', 'open_price_reason', 'open_price_approval_id', 'discount_amount', 'discount_type', 'discount_reason', 'discount_approval_id'] as $key) {
            unset($line[$key]);
        }
        $cart[$index] = $line;
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
    }

    public function remove(Request $request, User $user, int $productId): void
    {
        $context = $this->context($request, $user);
        $cart = collect($request->session()->get('pos.cart', []))->reject(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $productId);
        $request->session()->put('pos.cart', $cart->values()->all());
        $this->persist($request, $user, $context);
    }

    public function clear(Request $request, User $user): void
    {
        $context = $this->context($request, $user);
        $request->session()->forget('pos.cart');
        $this->persist($request, $user, $context);
    }

    private function persist(Request $request, User $user, PosContext $context): void
    {
        $order = $this->openOrders->persistSession($request, $user, $context);
        $data = $this->snapshot->build($context);
        $this->openOrders->updateTotals($order, $data['preview'] ?? null);
    }

    private function context(Request $request, User $user): PosContext
    {
        $context = $this->contextResolver->resolve($user);
        $order = $this->openOrders->active($request, $user, $context);
        if ($order !== null) {
            $this->openOrders->hydrateSession($request, $order);
        }

        return $context;
    }

    private function store(PosContext $context): Store
    {
        return $context->store
            ?? throw new InvalidArgumentException($context->disabledReason ?? __('POS is disabled until you open an assigned cashier shift.'));
    }

    private function resolveUnit(Product $product, ?int $productUnitId): ?ProductUnit
    {
        if ($product->productUnits->isEmpty()) {
            return null;
        }
        $unit = $productUnitId ? $product->productUnits->firstWhere('id', $productUnitId) : null;

        return $unit ?? $product->productUnits->firstWhere('is_base_unit', true);
    }

    private function enteredQuantity(Product $product, ?ProductUnit $unit, string $quantity): string
    {
        $places = $product->fractional_quantity ? (int) ($unit?->unit?->decimal_places ?? 6) : 0;
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity) || strlen(rtrim(explode('.', $quantity, 2)[1] ?? '', '0')) > $places || bccomp($quantity, '0', 6) <= 0 || bccomp($quantity, '999999', 6) > 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero and within the allowed limit.'));
        }

        return bcadd($quantity, '0', 6);
    }
}
