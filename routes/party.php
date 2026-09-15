<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Party\Actions\CancelPartyBookingAction;
use App\Modules\Party\Actions\CheckoutPartyRentalAssetAction;
use App\Modules\Party\Actions\CompletePartyOperatingOrderAction;
use App\Modules\Party\Actions\ConfirmPartyBookingAction;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Actions\CreatePartyOperatingOrderAction;
use App\Modules\Party\Actions\FinalizePartyInvoiceAction;
use App\Modules\Party\Actions\InspectPartyRentalAssetAction;
use App\Modules\Party\Actions\IssuePartyConsumableAction;
use App\Modules\Party\Actions\RecordPartyConsumableActualAction;
use App\Modules\Party\Actions\RecordPartyPaymentAction;
use App\Modules\Party\Actions\ReleasePartyOperatingOrderAction;
use App\Modules\Party\Actions\ReschedulePartyBookingAction;
use App\Modules\Party\Actions\ReturnPartyConsumableAction;
use App\Modules\Party\Actions\ReturnPartyRentalAssetAction;
use App\Modules\Party\Actions\SavePartyInvoiceAction;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyConsumableIssue;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyPayment;
use App\Modules\Party\Queries\PartyDashboard;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::prefix('parties')->name('parties.')->group(function (): void {
    $partyStores = static fn (User $user) => Store::query()->visibleTo($user)->where('type', 'party')->where('status', 'active')->orderBy('name_en');

    Route::get('calendar', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = filled($validated['from'] ?? null)
            ? CarbonImmutable::parse((string) $validated['from'])->startOfDay()
            : now()->startOfDay()->toImmutable();
        $to = filled($validated['to'] ?? null)
            ? CarbonImmutable::parse((string) $validated['to'])->endOfDay()
            : $from->addDays(6)->endOfDay();
        abort_if($from->diffInDays($to) > 30, 422, __('Choose a calendar range of 31 days or fewer.'));

        $reservations = AssetReservation::query()
            ->with(['asset', 'store'])
            ->whereHas('asset', fn ($query) => $query->visibleTo($user))
            ->whereIn('status', ['reserved', 'fulfilled'])
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->orderBy('starts_at')
            ->limit(200)
            ->get();
        $bookings = $user->can('party_bookings_invoices.view')
            ? PartyBooking::query()->visibleTo($user)->with(['customer:id,name_ar,name_en', 'store:id,code,name_ar,name_en'])
                ->whereNot('party_bookings.status', 'cancelled')->where('party_bookings.starts_at', '<=', $to)->where('party_bookings.ends_at', '>=', $from)
                ->orderBy('party_bookings.starts_at')->limit(200)->get()
            : collect();

        return view('pages.party.calendar', compact('from', 'to', 'reservations', 'bookings'));
    })->middleware('can:rental_assets.view')->name('calendar');

    Route::get('bookings', function (Request $request, PartyDashboard $dashboardQuery) use ($partyStores) {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_column(\App\Modules\Party\Enums\PartyBookingStatus::cases(), 'value'))], 'store_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'per_page' => ['nullable', Rule::in(['20', '50', '100'])]]);
        $stores = $partyStores($user)->get(['id', 'code', 'name_ar', 'name_en']);
        $requestedStoreId = filled($validated['store_id'] ?? null) ? (int) $validated['store_id'] : null;
        abort_if($requestedStoreId !== null && ! $stores->contains('id', $requestedStoreId), 404);
        $contextStoreId = app(\App\Modules\Platform\Support\WorkContext::class)->id($user);
        $storeId = $requestedStoreId ?? ($stores->contains('id', $contextStoreId) ? $contextStoreId : null);
        $term = trim((string) ($validated['q'] ?? ''));
        $bookings = PartyBooking::query()->visibleTo($user)->with(['customer', 'store', 'invoice'])
            ->when($storeId !== null, fn ($query) => $query->where('party_bookings.store_id', $storeId))
            ->when(filled($validated['status'] ?? null), fn ($query) => $query->where('party_bookings.status', $validated['status']))
            ->when(filled($validated['from'] ?? null), fn ($query) => $query->whereDate('party_bookings.party_date', '>=', $validated['from']))
            ->when(filled($validated['to'] ?? null), fn ($query) => $query->whereDate('party_bookings.party_date', '<=', $validated['to']))
            ->when($term !== '', function ($query) use ($term, $user): void {
                $like = '%'.$term.'%';
                $query->where(fn ($scope) => $scope->where('party_bookings.booking_number', 'like', $like)->orWhere('party_bookings.location', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer->where('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)->when($user->can('customers.sensitive'), fn ($customerQuery) => $customerQuery->orWhere('phone_e164', 'like', $like))));
            })->latest('party_bookings.party_date')->latest('party_bookings.starts_at')->paginate((int) ($validated['per_page'] ?? 20))->withQueryString();
        $dashboard = $dashboardQuery->for($user, $storeId);

        return view('pages.party.bookings.index', compact('bookings', 'dashboard', 'stores', 'storeId', 'term'));
    })->middleware('can:party_bookings_invoices.view')->name('bookings.index');

    Route::get('bookings/create', function (Request $request) use ($partyStores) {
        /** @var User $user */
        $user = $request->user();
        $stores = $partyStores($user)->get();
        $customers = Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->limit(300)->get();
        $assets = RentalAsset::query()->visibleTo($user)->with('store')->whereIn('status', ['available', 'reserved'])->whereHas('store', fn ($query) => $query->where('type', 'party')->where('status', 'active'))->orderBy('code')->limit(200)->get();
        $products = Product::query()->sellable()->orderBy('name_en')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en']);

        return view('pages.party.bookings.create', compact('stores', 'customers', 'assets', 'products'));
    })->middleware('can:party_bookings_invoices.create')->name('bookings.create');

    Route::post('bookings', function (Request $request, CreatePartyBookingAction $action) use ($partyStores) {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'store_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'child_id' => ['nullable', 'integer'],
            'party_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'location' => ['required', 'string', 'max:190'],
            'primary_contact' => ['required', 'string', 'max:120'],
            'secondary_contact' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'string', 'max:190'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_type' => ['nullable', Rule::in(['service', 'consumable', 'rental_asset', 'other'])],
            'lines.*.description' => ['nullable', 'string', 'max:190'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.asset_id' => ['nullable', 'integer'],
            'lines.*.resource_key' => ['nullable', 'string', 'max:190'],
        ]);
        $validated['lines'] = array_values(array_filter($validated['lines'], static fn (array $line): bool => filled($line['description'] ?? null) || filled($line['quantity'] ?? null) || filled($line['unit_price'] ?? null)));
        if ($validated['lines'] === []) {
            return back()->withInput()->withErrors(['lines' => __('At least one Party invoice line is required.')]);
        }
        $store = $partyStores($user)->whereKey($validated['store_id'])->firstOrFail();
        $customer = Customer::query()->visibleTo($user)->whereKey($validated['customer_id'])->where('status', 'active')->firstOrFail();
        try {
            $booking = $action->execute($user, $store, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['booking' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('parties.bookings.show', $booking)->with('success', __('Party booking and working invoice created.'));
    })->middleware('can:party_bookings_invoices.create')->name('bookings.store');

    Route::get('bookings/{bookingId}', function (Request $request, int $bookingId) {
        /** @var User $user */
        $user = $request->user();
        $booking = PartyBooking::query()->visibleTo($user)->with(['customer', 'child', 'branch', 'store', 'invoice.lines', 'invoice.payments', 'operatingOrders'])->whereKey($bookingId)->firstOrFail();

        return view('pages.party.bookings.show', compact('booking'));
    })->middleware('can:party_bookings_invoices.view')->name('bookings.show');

    Route::post('bookings/{bookingId}/confirm', function (Request $request, int $bookingId, ConfirmPartyBookingAction $action) {
        /** @var User $user */
        $user = $request->user();
        $booking = PartyBooking::query()->visibleTo($user)->whereKey($bookingId)->firstOrFail();
        try {
            $action->execute($user, $booking);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['booking' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party booking confirmed.'));
    })->middleware('can:party_bookings_invoices.approve')->name('bookings.confirm');

    Route::post('bookings/{bookingId}/reschedule', function (Request $request, int $bookingId, ReschedulePartyBookingAction $action) {
        /** @var User $user */
        $user = $request->user();
        $booking = PartyBooking::query()->visibleTo($user)->whereKey($bookingId)->firstOrFail();
        $validated = $request->validate([
            'party_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'location' => ['required', 'string', 'max:190'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        try {
            $action->execute($user, $booking, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['booking' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party booking rescheduled. Confirm it again after reviewing the new schedule.'));
    })->middleware('can:party_bookings_invoices.edit')->name('bookings.reschedule');

    Route::post('bookings/{bookingId}/cancel', function (Request $request, int $bookingId, CancelPartyBookingAction $action) {
        /** @var User $user */
        $user = $request->user();
        $booking = PartyBooking::query()->visibleTo($user)->whereKey($bookingId)->firstOrFail();
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $action->execute($user, $booking, $validated['reason']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['booking' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('parties.bookings.index')->with('success', __('Party booking cancelled.'));
    })->middleware('can:party_bookings_invoices.cancel')->name('bookings.cancel');

    Route::get('invoices', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        $mode = (string) $request->string('mode', 'working');
        abort_unless(in_array($mode, ['working', 'payments', 'settlement'], true), 404);
        $term = trim((string) $request->string('q'));
        $state = trim((string) $request->string('state'));
        $invoices = PartyInvoice::query()->visibleTo($user)->with(['booking.customer', 'booking.store'])
            ->where('state', '!=', 'final')
            ->when($mode === 'payments', fn ($query) => $query->where('balance_due', '>', 0))
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function ($scope) use ($like): void {
                    $scope->where('invoice_number', 'like', $like)
                        ->orWhereHas('booking', fn ($booking) => $booking->where('booking_number', 'like', $like)
                            ->orWhereHas('customer', fn ($customer) => $customer->where('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)));
                });
            })
            ->when($state !== '', fn ($query) => $query->where('state', $state))
            ->latest('id')->paginate(20)->withQueryString();

        return view('pages.party.invoices.index', compact('mode', 'invoices', 'term', 'state'));
    })->middleware('can:party_bookings_invoices.view')->name('invoices.index');

    Route::get('invoices/{invoiceId}', function (Request $request, int $invoiceId) {
        /** @var User $user */
        $user = $request->user();
        $invoice = PartyInvoice::query()->visibleTo($user)->with(['booking.customer', 'booking.store', 'lines'])->whereKey($invoiceId)->firstOrFail();
        $assets = RentalAsset::query()->visibleTo($user)->with('store')->whereIn('status', ['available', 'reserved'])->whereHas('store', fn ($query) => $query->where('type', 'party')->where('status', 'active'))->orderBy('code')->limit(200)->get();
        $products = Product::query()->sellable()->orderBy('name_en')->limit(500)->get(['id', 'item_code', 'name_ar', 'name_en']);

        return view('pages.party.invoices.show', compact('invoice', 'assets', 'products'));
    })->middleware('can:party_bookings_invoices.view')->name('invoices.show');

    Route::put('invoices/{invoiceId}', function (Request $request, int $invoiceId, SavePartyInvoiceAction $action) {
        /** @var User $user */
        $user = $request->user();
        $invoice = PartyInvoice::query()->visibleTo($user)->whereKey($invoiceId)->firstOrFail();
        $request->merge(['lines' => array_values(array_filter((array) $request->input('lines', []), static fn (mixed $line): bool => is_array($line) && (filled($line['description'] ?? null) || filled($line['quantity'] ?? null) || filled($line['unit_price'] ?? null) || filled($line['product_id'] ?? null) || filled($line['asset_id'] ?? null))))]);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:4000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_type' => ['required', Rule::in(['service', 'consumable', 'rental_asset', 'other'])],
            'lines.*.description' => ['required', 'string', 'max:190'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.asset_id' => ['nullable', 'integer'],
            'lines.*.resource_key' => ['nullable', 'string', 'max:190'],
        ]);
        try {
            $saved = $action->execute($user, $invoice, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['invoice' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('parties.invoices.show', $saved)->with('success', __('Working Party invoice saved.'));
    })->middleware('can:party_bookings_invoices.edit')->name('invoices.update');

    Route::get('invoices/{invoiceId}/payments', function (Request $request, int $invoiceId) {
        /** @var User $user */
        $user = $request->user();
        $invoice = PartyInvoice::query()->visibleTo($user)->with(['booking.customer', 'booking.store', 'payments'])->whereKey($invoiceId)->firstOrFail();
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('name_en')->get();

        return view('pages.party.payments.index', compact('invoice', 'methods'));
    })->middleware('can:party_bookings_invoices.view')->name('invoices.payments');

    Route::post('invoices/{invoiceId}/payments', function (Request $request, int $invoiceId, RecordPartyPaymentAction $action) {
        /** @var User $user */
        $user = $request->user();
        $invoice = PartyInvoice::query()->visibleTo($user)->whereKey($invoiceId)->firstOrFail();
        $validated = $request->validate(['payment_method_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0'], 'reference' => ['nullable', 'string', 'max:190'], 'evidence_reference' => ['nullable', 'string', 'max:190'], 'idempotency_key' => ['required', 'string', 'max:190']]);
        $method = PaymentMethod::query()->whereKey($validated['payment_method_id'])->where('status', 'active')->firstOrFail();
        try {
            $payment = $action->execute($user, $invoice, $method, (string) $validated['amount'], $validated['idempotency_key'], $validated['reference'] ?? null, $validated['evidence_reference'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['payment' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('parties.invoices.payments', $invoice)->with('success', __('Party payment recorded.'));
    })->middleware('can:party_bookings_invoices.create')->name('invoices.payments.store');

    Route::post('invoices/{invoiceId}/finalize', function (Request $request, int $invoiceId, FinalizePartyInvoiceAction $action) {
        /** @var User $user */
        $user = $request->user();
        $invoice = PartyInvoice::query()->visibleTo($user)->whereKey($invoiceId)->firstOrFail();
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:190'], 'confirmation' => ['required', Rule::in(['FINAL CLOSE'])]]);
        try {
            $final = $action->execute($user, $invoice, $validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['finalize' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('parties.invoices.show', $final)->with('success', __('Party invoice finalized and closed.'));
    })->middleware('can:party_bookings_invoices.approve')->name('invoices.finalize');

    Route::get('invoices/{invoiceId}/settle', function (Request $request, int $invoiceId) {
        /** @var User $user */
        $user = $request->user();
        $invoice = PartyInvoice::query()->visibleTo($user)->with(['booking.customer', 'booking.store', 'payments', 'lines'])->whereKey($invoiceId)->firstOrFail();

        return view('pages.party.invoices.settle', compact('invoice'));
    })->middleware('can:party_bookings_invoices.view')->name('invoices.settle');

    Route::get('orders', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_column(\App\Modules\Party\Enums\PartyOperatingOrderState::cases(), 'value'))]]);
        $term = trim((string) ($validated['q'] ?? ''));
        $orders = PartyOperatingOrder::query()->visibleTo($user)->with(['booking.customer', 'store'])
            ->when(filled($validated['status'] ?? null), fn ($query) => $query->where('party_operating_orders.status', $validated['status']))
            ->when($term !== '', function ($query) use ($term): void { $like = '%'.$term.'%'; $query->where(fn ($scope) => $scope->where('party_operating_orders.order_number', 'like', $like)->orWhereHas('booking', fn ($booking) => $booking->where('booking_number', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)))); })
            ->latest('party_operating_orders.id')->paginate(20)->withQueryString();

        return view('pages.party.orders.index', compact('orders', 'term'));
    })->middleware('can:party_operating_orders_consumables.view')->name('orders.index');

    Route::post('bookings/{bookingId}/orders', function (Request $request, int $bookingId, CreatePartyOperatingOrderAction $action) {
        /** @var User $user */
        $user = $request->user();
        $booking = PartyBooking::query()->visibleTo($user)->with('invoice')->whereKey($bookingId)->firstOrFail();
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:190']]);
        try {
            $order = $action->execute($user, $booking, $booking->invoice, $validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => \App\Support\UserSafeError::message($exception)]);
        }

        return to_route('parties.orders.show', $order)->with('success', __('Party operating order created.'));
    })->middleware('can:party_operating_orders_consumables.create')->name('orders.store');

    Route::get('orders/{orderId}', function (Request $request, int $orderId) {
        /** @var User $user */
        $order = PartyOperatingOrder::query()->visibleTo($user = $request->user())->with(['booking.customer', 'booking.store', 'invoice', 'lines.rentalAsset', 'lines.assetReservation', 'lines.assetCheckout', 'lines.assetReturn', 'lines.assetInspectionEvent', 'consumableIssues.lines'])->whereKey($orderId)->firstOrFail();

        return view('pages.party.orders.show', compact('order'));
    })->middleware('can:party_operating_orders_consumables.view')->name('orders.show');

    Route::post('orders/{orderId}/release', function (Request $request, int $orderId, ReleasePartyOperatingOrderAction $action) {
        /** @var User $user */
        $order = PartyOperatingOrder::query()->visibleTo($user = $request->user())->whereKey($orderId)->firstOrFail();
        try {
            $action->execute($user, $order);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party operating order released.'));
    })->middleware('can:party_operating_orders_consumables.approve')->name('orders.release');

    Route::post('orders/{orderId}/issue', function (Request $request, int $orderId, IssuePartyConsumableAction $action) {
        /** @var User $user */
        $order = PartyOperatingOrder::query()->visibleTo($user = $request->user())->with('lines')->whereKey($orderId)->firstOrFail();
        $validated = $request->validate(['line_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'gt:0'], 'idempotency_key' => ['required', 'string', 'max:190']]);
        $line = $order->lines()->whereKey($validated['line_id'])->firstOrFail();
        try {
            $action->execute($user, $order, $line, (string) $validated['quantity'], $validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['issue' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party consumable issued from the Party store.'));
    })->middleware('can:party_operating_orders_consumables.create')->name('orders.issue');

    Route::post('orders/{orderId}/return', function (Request $request, int $orderId, ReturnPartyConsumableAction $action) {
        /** @var User $user */
        $order = PartyOperatingOrder::query()->visibleTo($user = $request->user())->with(['lines', 'consumableIssues.lines'])->whereKey($orderId)->firstOrFail();
        $validated = $request->validate(['issue_id' => ['required', 'integer'], 'line_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'gt:0'], 'idempotency_key' => ['required', 'string', 'max:190']]);
        $issue = $order->consumableIssues->firstWhere('id', (int) $validated['issue_id']);
        abort_unless($issue instanceof PartyConsumableIssue, 404);
        $line = $order->lines->firstWhere('id', (int) $validated['line_id']);
        abort_unless($line !== null, 404);
        try {
            $action->execute($user, $issue, $line, (string) $validated['quantity'], $validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['return' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Unused Party consumable returned to the Party store.'));
    })->middleware('can:party_operating_orders_consumables.create')->name('orders.return');

    Route::post('orders/{orderId}/actuals', function (Request $request, int $orderId, RecordPartyConsumableActualAction $action) {
        /** @var User $user */
        $user = $request->user();
        $order = PartyOperatingOrder::query()->visibleTo($user)->whereKey($orderId)->firstOrFail();
        $validated = $request->validate(['line_id' => ['required', 'integer'], 'consumed_quantity' => ['required', 'numeric', 'min:0']]);
        $line = $order->lines()->whereKey($validated['line_id'])->firstOrFail();
        try {
            $action->execute($user, $order, $line, (string) $validated['consumed_quantity']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['actual' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Actual Party consumable quantity recorded.'));
    })->middleware('can:party_operating_orders_consumables.edit')->name('orders.actuals');

    Route::post('orders/{orderId}/assets/{lineId}/checkout', function (Request $request, int $orderId, int $lineId, CheckoutPartyRentalAssetAction $action) {
        /** @var User $user */
        $user = $request->user();
        $order = PartyOperatingOrder::query()->visibleTo($user)->whereKey($orderId)->firstOrFail();
        $line = $order->lines()->whereKey($lineId)->firstOrFail();
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:190']]);
        try {
            $action->execute($user, $order, $line, $validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['asset' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party rental asset checked out through the asset reservation.'));
    })->middleware('can:party_operating_orders_consumables.create')->name('orders.assets.checkout');

    Route::post('orders/{orderId}/assets/{lineId}/return', function (Request $request, int $orderId, int $lineId, ReturnPartyRentalAssetAction $action) {
        /** @var User $user */
        $user = $request->user();
        $order = PartyOperatingOrder::query()->visibleTo($user)->whereKey($orderId)->firstOrFail();
        $line = $order->lines()->whereKey($lineId)->firstOrFail();
        $validated = $request->validate(['condition_after' => ['required', 'string', 'max:40'], 'idempotency_key' => ['required', 'string', 'max:190']]);
        try {
            $action->execute($user, $order, $line, $validated['condition_after'], $validated['idempotency_key']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['asset' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party rental asset returned for inspection.'));
    })->middleware('can:party_operating_orders_consumables.create')->name('orders.assets.return');

    Route::post('orders/{orderId}/assets/{lineId}/inspect', function (Request $request, int $orderId, int $lineId, InspectPartyRentalAssetAction $action) {
        /** @var User $user */
        $user = $request->user();
        $order = PartyOperatingOrder::query()->visibleTo($user)->whereKey($orderId)->firstOrFail();
        $line = $order->lines()->whereKey($lineId)->firstOrFail();
        $validated = $request->validate(['resulting_status' => ['required', Rule::in(['available', 'damaged', 'under_maintenance', 'lost'])], 'assessment' => ['required', 'string', 'max:2000']]);
        try {
            $action->execute($user, $order, $line, $validated['resulting_status'], $validated['assessment']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['asset' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party rental asset inspection recorded.'));
    })->middleware('can:party_operating_orders_consumables.create')->name('orders.assets.inspect');

    Route::post('orders/{orderId}/complete', function (Request $request, int $orderId, CompletePartyOperatingOrderAction $action) {
        /** @var User $user */
        $order = PartyOperatingOrder::query()->visibleTo($user = $request->user())->whereKey($orderId)->firstOrFail();
        try {
            $action->execute($user, $order);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => \App\Support\UserSafeError::message($exception)]);
        }

        return back()->with('success', __('Party operating order completed.'));
    })->middleware('can:party_operating_orders_consumables.approve')->name('orders.complete');

    Route::get('payments/{paymentId}/print', function (Request $request, int $paymentId) {
        /** @var User $user */
        $payment = PartyPayment::query()->whereHas('invoice', fn ($invoice) => $invoice->visibleTo($user = $request->user()))->with(['invoice.booking.customer', 'invoice.booking.store'])->whereKey($paymentId)->firstOrFail();
        app(RecordAuditEvent::class)->execute('party', 'party_payment_receipt_printed', $payment, branchId: $payment->branch_id, storeId: $payment->store_id, metadata: ['format' => 'a4', 'receipt_number' => $payment->receipt_number]);

        return view('pages.party.print-payment', compact('payment'));
    })->middleware('can:party_bookings_invoices.print')->name('payments.print');

    Route::get('invoices/{invoiceId}/print', function (Request $request, int $invoiceId) {
        /** @var User $user */
        $invoice = PartyInvoice::query()->visibleTo($user = $request->user())->with(['booking.customer', 'booking.store', 'lines', 'payments'])->whereKey($invoiceId)->firstOrFail();
        app(RecordAuditEvent::class)->execute('party', $invoice->state === 'final' ? 'party_final_invoice_printed' : 'party_working_invoice_printed', $invoice, branchId: $invoice->booking->branch_id, storeId: $invoice->booking->store_id, metadata: ['format' => 'a4', 'final' => $invoice->state === 'final']);

        return view('pages.party.print-invoice', compact('invoice'));
    })->middleware('can:party_bookings_invoices.print')->name('invoices.print');
});
