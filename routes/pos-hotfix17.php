<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Support\PosReturnReceiptProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('pos/refunds/{return}/receipt', function (Request $request, int $return, PosReturnReceiptProfile $profiles) {
    /** @var User $actor */
    $actor = $request->user();
    $document = RetailReturn::query()->visibleTo($actor)->whereKey($return)->first();
    if ($document === null) { throw ValidationException::withMessages(['refund' => __('The refund document is unavailable in your authorized scope.')]); }
    $receiptProfile = $profiles->resolve($actor, $document);
    $document->load(['store.company', 'branch', 'customer', 'sourceSale', 'sourceGiftReceipt', 'lines.product', 'lines.saleLine', 'lines.returnStore', 'settlements.giftCard', 'settlements.paymentMethod']);
    app(RecordAuditEvent::class)->execute('retail', 'retail_return_receipt_previewed', $document, branchId: (int) $document->branch_id, storeId: (int) $document->store_id, metadata: ['printer_id' => $receiptProfile['printer']?->id, 'format' => $receiptProfile['format'], 'browser_fallback' => $receiptProfile['browser_fallback']]);

    return view('pages.returns.print', ['return' => $document, 'receiptProfile' => $receiptProfile, 'receiptPreview' => true, 'returnToPos' => true]);
})->whereNumber('return')->middleware('can:returns.print')->name('pos.refund.receipt');
