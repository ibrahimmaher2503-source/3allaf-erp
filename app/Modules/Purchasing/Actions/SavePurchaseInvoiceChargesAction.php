<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SavePurchaseInvoiceChargesAction
{
    private const TYPES = ['transport', 'loading', 'unloading', 'freight', 'other'];

    /** @param array<int, array<string, mixed>> $charges */
    public function execute(int $invoiceId, array $charges): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.draft');

        return DB::transaction(function () use ($invoiceId, $charges): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->status !== 'draft') {
                throw new InvalidArgumentException(__('Purchase charges can only be changed while the invoice is a draft.'));
            }

            $normalized = [];
            $chargeTotal = '0.0000';
            foreach ($charges as $charge) {
                $type = trim((string) ($charge['charge_type'] ?? ''));
                $amount = trim((string) ($charge['amount'] ?? ''));
                if (! in_array($type, self::TYPES, true)) {
                    throw new InvalidArgumentException(__('Purchase charge type is not supported.'));
                }
                if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $amount) || bccomp($amount, '0', 4) <= 0) {
                    throw new InvalidArgumentException(__('Purchase charge amount must be greater than zero with at most four decimal places.'));
                }
                $amount = bcadd($amount, '0', 4);
                $treatment = trim((string) ($charge['accounting_treatment'] ?? 'landed_cost'));
                if (! in_array($treatment, ['landed_cost', 'period_expense'], true)) {
                    throw new InvalidArgumentException(__('Purchase charge accounting treatment is not supported.'));
                }
                $normalized[] = [
                    'charge_type' => $type,
                    'amount' => $amount,
                    'accounting_treatment' => $treatment,
                    'notes' => filled($charge['notes'] ?? null) ? trim((string) $charge['notes']) : null,
                ];
                $chargeTotal = bcadd($chargeTotal, $amount, 4);
            }

            $invoice->charges()->delete();
            $invoice->charges()->createMany($normalized);
            $goodsTotal = $invoice->lines->reduce(fn (string $total, $line): string => bcadd($total, (string) $line->line_total, 4), '0.0000');
            $invoice->update(['total_amount' => bcadd($goodsTotal, $chargeTotal, 4)]);

            return $invoice->fresh(['lines', 'charges']);
        });
    }
}
