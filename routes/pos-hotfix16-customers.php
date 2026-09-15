<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Support\UserSafeError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('pos/customer/select', function (Request $request, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    abort_unless($user->can('pos_sales.create') && $user->can('customers.view'), 403);
    $validated = $request->validate(['customer_id' => ['required', 'integer']]);
    try {
        $context = $contexts->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('The active order is unavailable.'));
        }
        $customer = Customer::query()->visibleFrom($user, (int) $context->store->branch_id, (int) $context->store->id)
            ->whereKey($validated['customer_id'])->where('status', 'active')->first();
        if ($customer === null) {
            throw new InvalidArgumentException(__('The selected customer is not available in this POS context.'));
        }
        $orders->setCustomer($request, $user, $context, $customer);

        return back()->with('success', __('Customer attached to the active order.'));
    } catch (\Throwable $exception) {
        return back()->withErrors(['customer' => UserSafeError::message($exception)]);
    }
})->middleware(['can:pos_sales.create', 'can:customers.view'])->name('pos.customer.select');

Route::post('pos/customer/clear', function (Request $request, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    try {
        $orders->setCustomer($request, $user, $contexts->resolve($user), null);

        return back()->with('success', __('The active order now uses the walk-in customer.'));
    } catch (\Throwable $exception) {
        return back()->withErrors(['customer' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.customer.clear');

Route::post('pos/customer/create', function (Request $request, CreateCustomerAction $customers, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    abort_unless($user->can('pos_sales.create') && $user->can('customers.create'), 403);
    $validated = $request->validate([
        'idempotency_key' => ['required', 'uuid'],
        'phone' => ['required', 'string', 'max:64', PhoneNormalizer::validationRule()],
        'name_ar' => ['required', 'string', 'max:190'],
        'name_en' => ['required', 'string', 'max:190'],
        'consent_purpose' => ['required', 'string', 'max:80'],
    ]);
    try {
        $context = $contexts->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('The active order is unavailable.'));
        }
        $customer = $customers->execute($user, $context->store, $validated + ['consents' => [[
            'purpose' => $validated['consent_purpose'], 'status' => 'granted', 'source' => 'pos',
        ]]]);
        $orders->setCustomer($request, $user, $context, $customer);

        return back()->with('success', __('Customer created and attached only to the active order.'));
    } catch (\Throwable $exception) {
        return back()->withInput()->withErrors(['customer' => UserSafeError::message($exception)]);
    }
})->middleware(['can:pos_sales.create', 'can:customers.create'])->name('pos.customer.create');
