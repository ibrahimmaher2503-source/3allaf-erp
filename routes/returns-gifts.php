<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\GiftCardAction;
use App\Modules\Retail\Actions\GiftReceiptAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosReturnReceiptProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware('auth')->group(function (): void {
    Route::get('returns', function (Request $request) {
        /** @var User $user */ $user = $request->user(); abort_unless($user->is_super_admin || $user->can('returns.view') || $user->can('returns_exchanges_gift_instruments.view'), 403);
        $returns = RetailReturn::query()->visibleTo($user)->with(['sourceSale', 'sourceGiftReceipt', 'lines'])->latest('id')->paginate(20)->withQueryString();
        $sales = Sale::query()->visibleTo($user)->approved()->with('lines')->latest('id')->limit(50)->get();
        $receipts = GiftReceipt::query()->visibleTo($user)->where('status', 'active')->with('lines')->latest('id')->limit(50)->get();
        $replacementProducts = Product::query()->sellable()->orderBy('item_code')->limit(100)->get(['id', 'item_code', 'name_ar', 'name_en', 'parent_product_id']);
        return view('pages.returns.index', compact('returns', 'sales', 'receipts', 'replacementProducts'));
    })->name('returns.index');

    Route::post('returns', function (Request $request, RetailReturnAction $action) {
        /** @var User $user */ $user = $request->user();
        $data = $request->validate([
            'source_sale_id' => ['nullable', 'integer'], 'source_gift_receipt_id' => ['nullable', 'integer'], 'source_gift_receipt_reference' => ['nullable', 'string', 'max:80'],
            'sale_line_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', 'in:sellable,non_sellable,damaged,manager_review'], 'disposition' => ['required', 'in:restock,quarantine'],
            'settlement_type' => ['required', 'in:cash_refund,original_tender,gift_card,exchange'], 'original_payment_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:2000'], 'inspection_notes' => ['nullable', 'string', 'max:4000'], 'exchange_lines' => ['nullable', 'array'],
        ]);
        $data['exchange_lines'] = array_values(array_filter($data['exchange_lines'] ?? [], static fn (mixed $line): bool => is_array($line) && (filled($line['product_id'] ?? null) || filled($line['quantity'] ?? null))));
        $data['lines'] = [['sale_line_id' => $data['sale_line_id'], 'quantity' => (string) $data['quantity'], 'condition' => $data['condition'], 'disposition' => $data['disposition'], 'inspection_notes' => $data['inspection_notes'] ?? null]];
        try {
            $return = $action->create($user, $data, (string) ($request->input('idempotency_key') ?: Str::uuid()));
            return to_route('returns.show', $return)->with('success', __('Return draft created. Review and submit it for approval.'));
        } catch (Throwable $exception) { return back()->withInput()->withErrors(['return' => \App\Support\UserSafeError::message($exception)]); }
    })->name('returns.store');

    Route::get('returns/{return}', function (Request $request, int $return) {
        /** @var User $user */ $user = $request->user(); abort_unless($user->is_super_admin || $user->can('returns.view') || $user->can('returns_exchanges_gift_instruments.view'), 403);
        $document = RetailReturn::query()->visibleTo($user)->whereKey($return)->firstOrFail();
        $paymentMethods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->get();
        $loaded = $document->load(['sourceSale.payments.paymentMethod', 'sourceGiftReceipt', 'lines.product', 'lines.saleLine', 'lines.returnStore', 'exchange.lines.product', 'settlements.giftCard', 'settlements.paymentMethod', 'settlements.originalPayment']);
        $refundAllocations = [];
        $refundAllocationError = null;
        if (in_array($loaded->status, ['draft', 'inspection', 'submitted', 'approved'], true) && $loaded->settlement_type === 'original_tender') {
            try {
                $refundAllocations = app(\App\Modules\Retail\Support\RetailRefundCalculator::class)->defaultAllocations($loaded->sourceSale, (string) $loaded->settlement_value);
            } catch (Throwable $exception) {
                $refundAllocationError = \App\Support\UserSafeError::message($exception);
            }
        }
        return view('pages.returns.show', ['return' => $loaded, 'paymentMethods' => $paymentMethods, 'refundAllocations' => $refundAllocations, 'refundAllocationError' => $refundAllocationError]);
    })->whereNumber('return')->name('returns.show');

    Route::post('returns/{return}/submit', function (Request $request, int $return, RetailReturnAction $action) { try { $action->submit($request->user(), RetailReturn::query()->findOrFail($return)); return back()->with('success', __('Return submitted for approval.')); } catch (Throwable $e) { return back()->withErrors(['return' => \App\Support\UserSafeError::message($e)]); } })->whereNumber('return')->name('returns.submit');
    Route::post('returns/{return}/approve', function (Request $request, int $return, RetailReturnAction $action) { try { $action->approve($request->user(), RetailReturn::query()->findOrFail($return)); return back()->with('success', __('Return approved. It can now be completed.')); } catch (Throwable $e) { return back()->withErrors(['return' => \App\Support\UserSafeError::message($e)]); } })->whereNumber('return')->name('returns.approve');
    Route::post('returns/{return}/reject', function (Request $request, int $return, RetailReturnAction $action) { try { $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]); $action->reject($request->user(), RetailReturn::query()->findOrFail($return), $validated['rejection_reason']); return back()->with('success', __('Refund rejected with an audit reason.')); } catch (Throwable $e) { return back()->withErrors(['return' => \App\Support\UserSafeError::message($e)]); } })->whereNumber('return')->name('returns.reject');
    Route::post('returns/{return}/complete', function (Request $request, int $return, RetailReturnAction $action) {
        try {
            $validated = $request->validate(['idempotency_key' => ['nullable', 'string', 'max:190'], 'payment_method_id' => ['nullable', 'integer'], 'original_payment_id' => ['nullable', 'integer'], 'allocations' => ['nullable', 'array', 'max:12'], 'allocations.*.original_payment_id' => ['nullable', 'integer'], 'allocations.*.method_id' => ['nullable', 'integer'], 'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'], 'allocations.*.safe_reference' => ['nullable', 'string', 'max:190']]);
            $document = RetailReturn::query()->findOrFail($return);
            $action->complete($request->user(), $document, (string) ($validated['idempotency_key'] ?? 'return-complete:'.$document->id), isset($validated['payment_method_id']) ? (int) $validated['payment_method_id'] : null, isset($validated['original_payment_id']) ? (int) $validated['original_payment_id'] : null, $validated['allocations'] ?? []);
            return back()->with('success', __('Return completed and settlement recorded.'));
        } catch (Throwable $e) { return back()->withErrors(['return' => \App\Support\UserSafeError::message($e)]); }
    })->whereNumber('return')->name('returns.complete');
    Route::get('returns/{return}/print', function (Request $request, int $return, PosReturnReceiptProfile $profiles) { /** @var User $user */ $user = $request->user(); $document = RetailReturn::query()->visibleTo($user)->whereKey($return)->firstOrFail(); $receiptProfile = $profiles->resolve($user, $document); return view('pages.returns.print', ['return' => $document->load(['store.company', 'branch', 'customer', 'sourceSale', 'sourceGiftReceipt', 'lines.product', 'lines.saleLine', 'lines.returnStore', 'settlements.giftCard', 'settlements.paymentMethod']), 'receiptProfile' => $receiptProfile, 'receiptPreview' => true, 'returnToPos' => false]); })->whereNumber('return')->middleware('can:returns.print')->name('returns.print');

    Route::get('gift-receipts', function (Request $request) { /** @var User $user */ $user = $request->user(); abort_unless($user->is_super_admin || $user->can('gift_receipts.view') || $user->can('returns_exchanges_gift_instruments.view'), 403); $receipts = GiftReceipt::query()->visibleTo($user)->with(['sale', 'lines'])->withCount('printEvents')->latest('id')->paginate(20)->withQueryString(); $sales = Sale::query()->visibleTo($user)->approved()->with('lines')->latest('id')->limit(50)->get(); $selectedSaleId = $request->integer('sale_id'); $selectedSale = $selectedSaleId > 0 ? $sales->firstWhere('id', $selectedSaleId) : null; return view('pages.gift-instruments.index', compact('receipts', 'sales', 'selectedSaleId', 'selectedSale')); })->name('gift.receipts.index');
    Route::post('gift-receipts', function (Request $request, GiftReceiptAction $action) { $data = $request->validate(['sale_id' => ['required', 'integer'], 'sale_line_ids' => ['nullable', 'array'], 'sale_line_ids.*' => ['integer']]); try { $receipt = $action->issue($request->user(), Sale::query()->findOrFail($data['sale_id']), (string) ($request->input('idempotency_key') ?: Str::uuid()), array_map('intval', $data['sale_line_ids'] ?? [])); return to_route('gift.receipts.print', $receipt)->with('success', __('Gift Receipt issued without prices.')); } catch (Throwable $e) { return back()->withInput()->withErrors(['receipt' => \App\Support\UserSafeError::message($e)]); } })->name('gift.receipts.store');
    Route::get('gift-receipts/{giftReceipt}/print', function (Request $request, int $giftReceipt, GiftReceiptAction $action) { $receipt = GiftReceipt::query()->findOrFail($giftReceipt); $requestedReprint = $request->boolean('reprint'); $event = $action->print($request->user(), $receipt, (string) ($request->query('print_key') ?: 'gift-receipt-print:'.$receipt->id.':'.($requestedReprint ? Str::uuid() : 'first')), (string) ($request->query('format') ?: 'thermal'), $request->query('reason'), $requestedReprint); return view('pages.gift-instruments.print', ['receipt' => $receipt->fresh(['sale', 'lines.saleLine']), 'event' => $event]); })->whereNumber('giftReceipt')->name('gift.receipts.print');
    Route::get('gift-receipts/validate/{reference}', function (Request $request, string $reference, GiftReceiptAction $action) { return response()->json($action->validate($request->user(), $reference)->only(['reference', 'status', 'sale_id'])); })->name('gift.receipts.validate');

    Route::get('gift-cards', function (Request $request) { /** @var User $user */ $user = $request->user(); abort_unless($user->is_super_admin || $user->can('gift_cards.view') || $user->can('returns_exchanges_gift_instruments.view'), 403); $cards = GiftCard::query()->visibleTo($user)->with(['holder', 'store'])->latest('id')->paginate(20)->withQueryString(); $stores = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('id')->get(); return view('pages.gift-instruments.cards', compact('cards', 'stores')); })->name('gift.cards.index');
    Route::post('gift-cards', function (Request $request, GiftCardAction $action) { $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'store_id' => ['required', 'integer'], 'valid_until' => ['nullable', 'date']]); $store = Store::query()->visibleTo($request->user())->whereKey($data['store_id'])->firstOrFail(); try { $card = $action->issue($request->user(), (string) $data['amount'], (int) $store->branch_id, (int) $store->id, 'manual', 'manual-'.$request->user()->id, (string) ($request->input('idempotency_key') ?: Str::uuid()), __('Manual issue'), null, 'EGP', isset($data['valid_until']) ? new DateTimeImmutable((string) $data['valid_until']) : null); return back()->with('success', __('Gift Card :identifier issued.', ['identifier' => $card->identifier])); } catch (Throwable $e) { return back()->withInput()->withErrors(['card' => \App\Support\UserSafeError::message($e)]); } })->name('gift.cards.store');
    Route::post('gift-cards/{card}/void', function (Request $request, int $card, GiftCardAction $action) { $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]); try { $action->void($request->user(), GiftCard::query()->findOrFail($card), $data['reason'], (string) ($request->input('idempotency_key') ?: Str::uuid())); return back()->with('success', __('Gift Card voided.')); } catch (Throwable $e) { return back()->withErrors(['card' => \App\Support\UserSafeError::message($e)]); } })->whereNumber('card')->name('gift.cards.void');
    Route::get('gift-cards/{card}', function (Request $request, int $card) {
        /** @var User $user */ $user = $request->user(); abort_unless($user->is_super_admin || $user->can('gift_cards.view') || $user->can('returns_exchanges_gift_instruments.view'), 403);
        $document = GiftCard::query()->visibleTo($user)->with(['holder', 'store', 'issuer'])->whereKey($card)->firstOrFail();
        $ledger = $document->ledger()->with('creator')->latest('id')->paginate(20)->withQueryString();
        return view('pages.gift-instruments.show', compact('document', 'ledger'));
    })->whereNumber('card')->name('gift.cards.show');
    Route::get('gift-cards/{card}/print', function (Request $request, int $card, GiftCardAction $action) { $document = GiftCard::query()->findOrFail($card); $event = $action->print($request->user(), $document, (string) ($request->query('print_key') ?: 'gift-card-print:'.$document->id.':'.($request->query('reprint') ? Str::uuid() : 'first')), (string) ($request->query('format') ?: 'thermal'), $request->query('reason')); return view('pages.gift-instruments.card-print', ['card' => $document->fresh(), 'event' => $event]); })->whereNumber('card')->name('gift.cards.print');
});
