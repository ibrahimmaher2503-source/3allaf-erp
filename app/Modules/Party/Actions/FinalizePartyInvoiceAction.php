<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Customer\Actions\PostPartyWalletEntryAction;
use App\Modules\Customer\Support\PartyWalletBalance;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class FinalizePartyInvoiceAction
{
    public function execute(User $actor, PartyInvoice $invoice, string $idempotencyKey): PartyInvoice
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.approve');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException(__('A final-close idempotency key is required.'));
        }

        return DB::transaction(function () use ($actor, $invoice, $idempotencyKey): PartyInvoice {
            $invoice = PartyInvoice::query()->with(['booking', 'lines'])->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->state === 'final') {
                if ((string) $invoice->final_close_idempotency_key !== $idempotencyKey) {
                    throw new InvalidArgumentException(__('This Party invoice was already finalized by another request.'));
                }

                return $invoice;
            }
            if (in_array($invoice->state, ['cancelled', 'corrected_by_reference'], true)) {
                throw new InvalidArgumentException(__('This Party invoice cannot be finalized from its current state.'));
            }
            if (! in_array($invoice->booking->status, ['confirmed', 'completed_pending_settlement'], true)) {
                throw new InvalidArgumentException(__('The Party booking must be confirmed before final close.'));
            }
            $openOrder = $invoice->booking->operatingOrders()->whereNotIn('status', ['completed', 'cancelled'])->exists();
            if ($openOrder) {
                throw new InvalidArgumentException(__('The Party operating order must be completed before final close.'));
            }
            $requiresOperation = $invoice->lines()->whereIn('line_type', ['consumable', 'rental_asset'])->exists();
            if ($requiresOperation && ! $invoice->booking->operatingOrders()->where('status', 'completed')->exists()) {
                throw new InvalidArgumentException(__('Party consumables and rental assets require a completed operating order before final close.'));
            }
            $total = bcadd((string) $invoice->lines()->sum('line_total'), '0', 4);
            $paid = bcadd((string) $invoice->payments()->where('status', 'approved')->sum('amount'), '0', 4);
            $remaining = bcsub($total, $paid, 4);
            $walletApplied = '0.0000';
            if (bccomp($remaining, '0', 4) > 0 && Gate::forUser($actor)->allows('party_wallet.settle')) {
                $store = Store::query()->visibleTo($actor)->whereKey($invoice->booking->store_id)->where('status', 'active')->firstOrFail();
                $walletBalance = app(PartyWalletBalance::class)->forCustomer((int) $invoice->booking->customer_id, $actor);
                if (bccomp($walletBalance, '0', 4) > 0) {
                    $walletApplied = bccomp($walletBalance, $remaining, 4) < 0 ? $walletBalance : $remaining;
                    app(PostPartyWalletEntryAction::class)->debit($actor, $invoice->booking->customer, $store, $walletApplied, 'party_final_settlement', (string) $invoice->id, 'party-final-wallet:'.$idempotencyKey, reference: $invoice->invoice_number, reason: 'Party final settlement');
                    $remaining = bcsub($remaining, $walletApplied, 4);
                }
            }
            $finalInvoiceNumber = app(AllocateDocumentNumber::class)->executeForBranch('party_final_invoice', (int) $invoice->booking->branch_id);
            $finalReceiptNumber = app(AllocateDocumentNumber::class)->executeForBranch('party_final_receipt', (int) $invoice->booking->branch_id);
            $before = $invoice->only(['state', 'total_amount', 'paid_amount', 'balance_due', 'credit_amount']);
            $invoice->update(['state' => 'finalizing', 'total_amount' => $total, 'paid_amount' => $paid, 'wallet_applied_amount' => $walletApplied, 'balance_due' => $remaining, 'credit_amount' => '0.0000', 'final_invoice_number' => $finalInvoiceNumber, 'final_receipt_number' => $finalReceiptNumber, 'final_close_idempotency_key' => $idempotencyKey, 'finalized_by' => $actor->id, 'finalized_at' => now(), 'updated_by' => $actor->id, 'lock_version' => $invoice->lock_version + 1]);
            $invoice->update(['state' => 'final']);
            $invoice->booking->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id, 'updated_by' => $actor->id, 'lock_version' => $invoice->booking->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('party', 'party_final_settlement_completed', $invoice, $before, $invoice->only(['state', 'total_amount', 'paid_amount', 'wallet_applied_amount', 'balance_due', 'final_invoice_number', 'final_receipt_number']), (int) $invoice->booking->branch_id, (int) $invoice->booking->store_id, metadata: ['wallet' => 'party_only', 'idempotency_key' => $idempotencyKey, 'receipt_number' => $finalReceiptNumber]);

            return $invoice->fresh(['booking', 'lines', 'payments']);
        }, 5);
    }
}
