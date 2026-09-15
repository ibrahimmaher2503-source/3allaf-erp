<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Actions\CompletePosOpenOrderAction;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PaymentReferenceGuard;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Modules\Retail\Support\PosReceiptProfile;
use App\Support\UserSafeError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Hotfix16 routes are registered before the preserved Hotfix15 compatibility
| handlers. Existing named URLs remain stable; matching requests use these
| durable open-order handlers first.
*/

Route::get('pos', function (Request $request, PosContextResolver $contexts, PosOpenOrderManager $orders, PosCartSnapshot $snapshots) {
    /** @var User $user */
    $user = $request->user();
    abort_unless($user->can('pos_sales.view'), 403);
    $context = $contexts->resolve($user);
    $store = $context->store;
    $shift = $context->shift;
    $completedSaleId = $request->session()->get('pos.completed_sale_id');
    $activeOrder = $orders->active($request, $user, $context, ! is_numeric($completedSaleId));
    if ($activeOrder !== null) {
        $orders->hydrateSession($request, $activeOrder);
        $activeOrder->load(['customer', 'paymentDrafts.paymentMethod']);
    }

    $snapshot = $snapshots->build($context);
    $orders->updateTotals($activeOrder, $snapshot['preview'] ?? null);
    $openOrders = $orders->orders($user, $context);
    $selectedCustomer = $activeOrder?->customer;
    $customerSearchResults = collect();
    $customerQuery = trim((string) $request->query('customer_q', ''));
    $customerPurposes = [];
    $customerPolicyError = null;
    if ($store !== null && $user->can('customers.view')) {
        try {
            $customerPurposes = CustomerPolicy::allowedPurposes('customer.consent.purpose')['value'];
        } catch (InvalidArgumentException $exception) {
            $customerPolicyError = UserSafeError::message($exception);
        }
        if ($customerQuery !== '') {
            $escaped = addcslashes($customerQuery, '%_\\');
            $prefix = $escaped.'%';
            $digits = preg_replace('/[^0-9]+/', '', $customerQuery) ?: '';
            $customerSearchResults = Customer::query()
                ->visibleFrom($user, (int) $store->branch_id, (int) $store->id)
                ->where('status', 'active')
                ->where(function ($query) use ($prefix, $digits): void {
                    $query->where('name_ar', 'like', $prefix)
                        ->orWhere('name_en', 'like', $prefix)
                        ->orWhere('public_id', 'like', $prefix)
                        ->orWhere('email', 'like', $prefix);
                    if ($digits !== '') {
                        $query->orWhere('phone_normalized', 'like', $digits.'%')
                            ->orWhere('secondary_phone_normalized', 'like', $digits.'%');
                    }
                })
                ->orderByRaw('CASE WHEN phone_normalized = ? OR public_id = ? THEN 0 ELSE 1 END', [$digits, $customerQuery])
                ->orderBy(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en')
                ->limit(12)
                ->get(['id', 'public_id', 'name_ar', 'name_en', 'phone_display', 'email']);
        }
    }

    $completedSale = null;
    if (is_numeric($completedSaleId)) {
        $completedSale = Sale::query()->visibleTo($user)->whereKey((int) $completedSaleId)->where('status', 'approved')->first();
    }

    return view('pages.pos.index', [
        'context' => $context,
        'store' => $store,
        'shift' => $shift,
        'activeOrder' => $activeOrder?->fresh(['customer']),
        'openOrders' => $openOrders,
        'selectedCustomer' => $selectedCustomer,
        'customerSearchResults' => $customerSearchResults,
        'customerQuery' => $customerQuery,
        'customerPurposes' => $customerPurposes,
        'customerPolicyError' => $customerPolicyError,
        'previewError' => $snapshot['error'] ?? null,
        'completedSale' => $completedSale,
    ]);
})->middleware('can:pos_sales.view')->name('pos');

Route::post('pos/orders', function (Request $request, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    $data = $request->validate(['creation_token' => ['required', 'uuid']]);
    /** @var User $user */
    $user = $request->user();
    try {
        $context = $contexts->resolve($user);
        $order = $orders->create($user, $context, $data['creation_token']);
        $orders->switch($request, $user, $context, $order->uuid);
        $request->session()->forget('pos.completed_sale_id');

        return to_route('pos')->with('success', __('A clean order was created and activated.'));
    } catch (\Throwable $exception) {
        return back()->withErrors(['order' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.orders.store');

Route::post('pos/orders/switch', function (Request $request, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    $data = $request->validate(['open_order_uuid' => ['required', 'uuid']]);
    try {
        $orders->switch($request, $user, $contexts->resolve($user), $data['open_order_uuid']);
        $request->session()->forget('pos.completed_sale_id');

        return to_route('pos');
    } catch (\Throwable $exception) {
        return back()->withErrors(['order' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.orders.switch');

Route::post('pos/orders/cancel', function (Request $request, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    $data = $request->validate(['open_order_uuid' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:500']]);
    try {
        $orders->cancel($request, $user, $contexts->resolve($user), $data['open_order_uuid'], $data['reason'] ?? null);

        return to_route('pos')->with('success', __('The open order was cancelled without posting a sale.'));
    } catch (\Throwable $exception) {
        return back()->withErrors(['order' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.orders.cancel');

Route::post('pos/cart/add', function (Request $request, PosCartAction $cart) {
    $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:999999']]);
    try {
        $cart->add($request, $request->user(), (int) $data['product_id'], (string) $data['quantity']);

        return back()->with('success', __('Product added to the active order.'));
    } catch (\Throwable $exception) {
        return back()->withErrors(['cart' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.cart.add');

Route::post('pos/cart/remove', function (Request $request, PosCartAction $cart) {
    $data = $request->validate(['product_id' => ['required', 'integer']]);
    try {
        $cart->remove($request, $request->user(), (int) $data['product_id']);

        return back();
    } catch (\Throwable $exception) {
        return back()->withErrors(['cart' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.cart.remove');

Route::post('pos/cart/quantity', function (Request $request, PosCartAction $cart) {
    $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:999999']]);
    try {
        $cart->quantity($request, $request->user(), (int) $data['product_id'], (string) $data['quantity']);

        return back();
    } catch (\Throwable $exception) {
        return back()->withErrors(['cart' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.cart.quantity');

Route::post('pos/cart/clear', function (Request $request, PosCartAction $cart) {
    try {
        $cart->clear($request, $request->user());

        return back();
    } catch (\Throwable $exception) {
        return back()->withErrors(['cart' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.cart.clear');

Route::post('pos/suspend', function (Request $request, CompletePosOpenOrderAction $sales, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    try {
        $context = $contexts->resolve($user);
        $order = $orders->active($request, $user, $context, false);
        if ($order === null || $context->store === null) {
            throw new InvalidArgumentException(__('The active order is unavailable.'));
        }
        $orders->hydrateSession($request, $order);
        $sale = $sales->execute($user, $context, $order, [], true);
        $request->session()->forget([PosOpenOrderManager::SESSION_KEY, 'pos.cart', 'pos.checkout_token', 'pos.tax_applicable', 'pos.customer_id']);

        return to_route('pos')->with('success', __('Sale suspended. Resume code: :code', ['code' => $sale->suspendedSale?->resume_code]));
    } catch (\Throwable $exception) {
        return back()->withErrors(['cart' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.suspend');

Route::post('pos/checkout', function (Request $request, CompletePosOpenOrderAction $sales, PosContextResolver $contexts, PosOpenOrderManager $orders) {
    /** @var User $user */
    $user = $request->user();
    $validated = $request->validate([
        'checkout_token' => ['required', 'uuid'],
        'open_order_uuid' => ['required', 'uuid'],
        'payment_mode' => ['required', 'in:cash,card,split,other,credit'],
        'tax_applicable' => ['nullable', 'boolean'],
        'notes' => ['nullable', 'string', 'max:1000'],
        'payments' => ['sometimes', 'array', 'max:12'],
        'payments.*.method_id' => ['required', 'integer', 'distinct:strict'],
        'payments.*.amount' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        'payments.*.tendered' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        'payments.*.evidence_reference' => ['nullable', 'string', 'max:190'],
        'payments.*.evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        'payments.*.gift_card_identifier' => ['nullable', 'string', 'max:100'],
    ]);

    try {
        $context = $contexts->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('The POS context is no longer active.'));
        }
        $order = $orders->findScoped($user, $context, $validated['open_order_uuid']);
        if ($order === null || ! hash_equals((string) $order->checkout_token, (string) $validated['checkout_token'])) {
            throw new InvalidArgumentException(__('This checkout draft is stale or belongs to another POS context. Reload the active order.'));
        }
        $orders->hydrateSession($request, $order);
        $customer = $order->customer_id ? Customer::query()->visibleFrom($user, $order->branch_id, $order->store_id)->whereKey($order->customer_id)->where('status', 'active')->first() : null;
        if ($order->customer_id !== null && $customer === null) {
            throw new InvalidArgumentException(__('The selected customer is no longer available for this outlet. Choose another customer or use walk-in.'));
        }
        $payments = array_values($validated['payments'] ?? []);
        if (($validated['payment_mode'] === 'credit') !== ($payments === [])
            || ($validated['payment_mode'] === 'credit' && ($customer === null || ! in_array($customer->customer_type, ['credit', 'both'], true)))) {
            throw new InvalidArgumentException(__('An active credit customer and no payment portions are required for a credit checkout.'));
        }

        $orders->savePaymentDraft(
            $request, $user, $context, $validated['payment_mode'], $validated['notes'] ?? null,
            array_map(static fn (array $payment): array => [
                'method_id' => $payment['method_id'],
                'amount' => $payment['amount'] ?? null,
                'tendered' => $payment['tendered'] ?? null,
                'evidence_reference' => $payment['evidence_reference'] ?? null,
                'gift_card_identifier' => $payment['gift_card_identifier'] ?? null,
            ], $payments),
        );
        $tenders = [];
        $cashCount = 0;
        foreach ($payments as $index => $payment) {
            $method = PaymentMethod::query()->whereKey((int) $payment['method_id'])->where('status', 'active')->first();
            if ($method === null) {
                throw new InvalidArgumentException(__('One selected payment method is no longer active.'));
            }
            if ($method->isCash()) {
                $cashCount++;
                if (! isset($payment['tendered']) || trim((string) $payment['tendered']) === '') {
                    throw new InvalidArgumentException(__('Enter the cash received or choose the exact amount.'));
                }
            } elseif (! isset($payment['amount']) || bccomp((string) $payment['amount'], '0', 2) <= 0) {
                throw new InvalidArgumentException(__('Every card or electronic portion must be greater than zero.'));
            }

            $reference = PaymentReferenceGuard::normalize($payment['evidence_reference'] ?? null);
            $attachmentId = null;
            if ($request->hasFile("payments.{$index}.evidence")) {
                abort_unless($user->can('pos_sales.payment_evidence_upload'), 403);
                $attachment = app(StoreAttachment::class)->execute(
                    $request->file("payments.{$index}.evidence"),
                    'payment_evidence',
                    new AttachmentSourceReference(branchId: (int) $order->branch_id, storeId: (int) $order->store_id, visibility: 'private'),
                );
                $attachmentId = $attachment->id;
            }

            $giftCard = null;
            if ((string) $method->type === 'gift_card') {
                abort_unless($user->can('gift_cards.redeem'), 403);
                $identifier = trim((string) ($payment['gift_card_identifier'] ?? ''));
                $giftCard = GiftCard::query()->visibleTo($user)->where('identifier', $identifier)
                    ->where('branch_id', $order->branch_id)->where('store_id', $order->store_id)
                    ->whereIn('status', ['active', 'partially_used'])->first();
                if ($giftCard === null) {
                    throw new InvalidArgumentException(__('The Gift Card is not active in this sale context.'));
                }
            }

            $tenders[] = [
                'method' => $method,
                'amount' => (string) ($payment['amount'] ?? '0.00'),
                'tendered' => isset($payment['tendered']) ? (string) $payment['tendered'] : null,
                'evidence_reference' => $reference,
                'evidence_attachment_id' => $attachmentId,
                'gift_card' => $giftCard,
            ];
        }
        if ($cashCount > 1 || ($validated['payment_mode'] === 'cash' && ($cashCount !== 1 || count($tenders) !== 1)) || ($validated['payment_mode'] === 'card' && $cashCount !== 0) || ($validated['payment_mode'] === 'split' && count($tenders) < 2)) {
            throw new InvalidArgumentException(__('The selected payment portions do not match the checkout mode.'));
        }

        $sale = $sales->execute($user, $context, $order, $tenders, false, $validated['notes'] ?? null);

        $request->session()->forget([PosOpenOrderManager::SESSION_KEY, 'pos.cart', 'pos.checkout_token', 'pos.tax_applicable', 'pos.customer_id']);
        $request->session()->flash('pos.completed_sale_id', $sale->id);

        return to_route('pos')->with('success', __('Sale completed successfully'));
    } catch (\Throwable $exception) {
        return back()->withInput()->withErrors(['payments' => UserSafeError::message($exception)]);
    }
})->middleware('can:pos_sales.create')->name('pos.checkout');

Route::get('pos/sales/{sale}/receipt', function (Request $request, Sale $sale, PosReceiptProfile $profiles) {
    /** @var User $user */
    $user = $request->user();
    $profile = $profiles->resolve($user, $sale);
    $sale->load(['store.company', 'branch', 'cashier', 'customer', 'lines', 'payments.paymentMethod']);
    app(RecordAuditEvent::class)->execute(
        category: 'retail', event: 'sale_receipt_previewed', source: $sale,
        branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id,
        metadata: ['format' => $profile['format'], 'paper_size' => $profile['paper_size'], 'printer_id' => $profile['printer']?->id, 'browser_fallback' => $profile['browser_fallback']],
    );

    return view($profile['format'] === 'thermal' ? 'pages.sales.thermal' : 'pages.sales.print', [
        'sale' => $sale,
        'receiptProfile' => $profile,
        'receiptPreview' => true,
    ]);
})->middleware('can:pos_sales.print')->name('pos.receipt.preview');
