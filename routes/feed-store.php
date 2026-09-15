<?php

use App\Http\Controllers\FeedStoreOperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('feed-store')->name('feed-store.')->group(function (): void {
    Route::get('operations', [FeedStoreOperationsController::class, 'index'])->name('operations');
    Route::post('customer-receipts', [FeedStoreOperationsController::class, 'customerReceipt'])->name('customer-receipts.store');
    Route::post('supplier-payments', [FeedStoreOperationsController::class, 'supplierPayment'])->name('supplier-payments.store');
    Route::post('expenses', [FeedStoreOperationsController::class, 'expense'])->name('expenses.store');
    Route::post('cash-accounts', [FeedStoreOperationsController::class, 'cashAccount'])->name('cash-accounts.store');
    Route::post('batches', [FeedStoreOperationsController::class, 'batch'])->name('batches.store');
});
