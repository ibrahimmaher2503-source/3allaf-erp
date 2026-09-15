<?php

declare(strict_types=1);

namespace App\Modules\Party\Actions;

use App\Models\User;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyPayment;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordPartyPaymentAction
{
    public function execute(User $actor, PartyInvoice $invoice, PaymentMethod $method, string $amount, string $idempotencyKey, ?string $reference = null, ?string $evidenceReference = null): PartyPayment
    {
        Gate::forUser($actor)->authorize('party_bookings_invoices.create');
        $amount = $this->money($amount);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') throw new InvalidArgumentException(__('A Party payment idempotency key is required.'));
        $payload = ['invoice_id' => (int) $invoice->id, 'method_id' => (int) $method->id, 'amount' => $amount, 'reference' => $reference, 'evidence_reference' => $evidenceReference];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use ($actor, $invoice, $method, $amount, $idempotencyKey, $reference, $evidenceReference, $payloadHash): PartyPayment {
                $existing = PartyPayment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->payload_hash, $payloadHash)) throw new InvalidArgumentException(__('This Party payment idempotency key was already used with different data.'));
                    return $existing;
                }
                $lockedInvoice = PartyInvoice::query()->with('booking')->lockForUpdate()->findOrFail($invoice->id);
                if (in_array($lockedInvoice->state, ['final', 'cancelled', 'corrected_by_reference'], true)) throw new InvalidArgumentException(__('Payments cannot be added to a final Party invoice.'));
                $method = PaymentMethod::query()->whereKey($method->id)->where('status', 'active')->firstOrFail();
                if ((bool) $method->requires_evidence && ! filled($evidenceReference)) throw new InvalidArgumentException(__('This payment method requires evidence reference.'));
                $paid = bcadd((string) PartyPayment::query()->where('party_invoice_id', $lockedInvoice->id)->where('status', 'approved')->sum('amount'), '0', 4);
                $remaining = bcsub((string) $lockedInvoice->total_amount, $paid, 4);
                if (bccomp($amount, $remaining, 4) > 0) throw new InvalidArgumentException(__('This Party payment exceeds the remaining invoice balance.'));
                $store = $lockedInvoice->booking->store;
                $receiptNumber = app(AllocateDocumentNumber::class)->executeForBranch('party_payment_receipt', (int) $store->branch_id);
                $payment = PartyPayment::query()->create([
                    'party_invoice_id' => $lockedInvoice->id, 'party_booking_id' => $lockedInvoice->party_booking_id,
                    'branch_id' => $lockedInvoice->booking->branch_id, 'store_id' => $lockedInvoice->booking->store_id,
                    'payment_method_id' => $method->id, 'method_code' => $method->code, 'method_type' => $method->type,
                    'amount' => $amount, 'reference' => filled($reference) ? trim($reference) : null, 'evidence_reference' => filled($evidenceReference) ? trim($evidenceReference) : null,
                    'receipt_number' => $receiptNumber, 'receipt_label' => 'Payment on Account for Party Invoice No. '.$lockedInvoice->invoice_number,
                    'status' => 'approved', 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
                ]);
                $newPaid = bcadd($paid, $amount, 4);
                $lockedInvoice->update(['paid_amount' => $newPaid, 'balance_due' => bcsub((string) $lockedInvoice->total_amount, $newPaid, 4), 'updated_by' => $actor->id, 'lock_version' => $lockedInvoice->lock_version + 1]);
                app(RecordAuditEvent::class)->execute('party', 'party_payment_recorded', $payment, ['paid_amount' => $paid, 'balance_due' => $remaining], ['paid_amount' => $newPaid, 'balance_due' => bcsub((string) $lockedInvoice->total_amount, $newPaid, 4), 'receipt_number' => $receiptNumber], (int) $store->branch_id, (int) $store->id, metadata: ['invoice_id' => $lockedInvoice->id, 'payment_id' => $payment->id, 'idempotency_key' => $idempotencyKey, 'wallet' => 'party_only']);

                return $payment;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PartyPayment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null && hash_equals((string) $existing->payload_hash, $payloadHash)) return $existing;
            throw $exception;
        }
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value) || bccomp($value, '0', 4) <= 0) throw new InvalidArgumentException(__('Party payment amount must be a positive decimal.'));
        return bcadd($value, '0', 4);
    }
}
