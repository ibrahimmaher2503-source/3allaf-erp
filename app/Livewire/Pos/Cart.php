<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class Cart extends Component
{
    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    #[On('pos-cart-updated')]
    public function refreshCart(): void {}

    public function quantity(int $productId, string $quantity, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->quantity(request(), $user, $productId, $quantity);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', \App\Support\UserSafeError::message($exception));
        }
    }

    public function unit(int $productId, int $productUnitId, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->unit(request(), $user, $productId, $productUnitId);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', \App\Support\UserSafeError::message($exception));
        }
    }

    public function remove(int $productId, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->remove(request(), $user, $productId);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', \App\Support\UserSafeError::message($exception));
        }
    }

    public function clear(PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        try {
            /** @var User $user */
            $user = auth()->user();
            $cart->clear(request(), $user);
            $this->dispatch('pos-cart-updated');
        } catch (\Throwable $exception) {
            $this->addError('cart', \App\Support\UserSafeError::message($exception));
        }
    }

    public function render(PosCartSnapshot $snapshot, PosContextResolver $contextResolver, PosOpenOrderManager $orders): View
    {
        /** @var User $user */
        $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $data = $snapshot->build($context);
        $activeOrder = $orders->active(request(), $user, $context, false);
        $openOrders = $orders->orders($user, $context);

        return view('livewire.pos.cart', [...$data, 'store' => $store, 'context' => $context, 'activeOrder' => $activeOrder, 'openOrders' => $openOrders]);
    }
}
