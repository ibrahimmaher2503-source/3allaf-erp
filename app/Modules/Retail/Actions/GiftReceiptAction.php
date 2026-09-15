<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\GiftReceiptPrintEvent;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class GiftReceiptAction
{
    /** @param array<int, int> $saleLineIds */
    public function issue(User $actor, Sale $sale, string $idempotencyKey, array $saleLineIds = []): GiftReceipt
    {
        $this->authorize($actor, 'gift_receipts.issue');
        $sale = Sale::query()->visibleTo($actor)->approved()->with(['lines', 'store'])->whereKey($sale->id)->first();
        if ($sale === null) throw (new ModelNotFoundException)->setModel(Sale::class, [$sale?->id]);
        if ($sale->lines->isEmpty()) throw ValidationException::withMessages(['sale' => __('Only a completed sale with eligible lines can issue a Gift Receipt.')]);

        return DB::transaction(function () use ($actor, $sale, $idempotencyKey, $saleLineIds): GiftReceipt {
            $existing = GiftReceipt::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->sale_id !== (int) $sale->id) throw ValidationException::withMessages(['idempotency_key' => __('This issue token belongs to another sale.')]);
                return $existing->load('lines', 'sale');
            }
            $selected = $saleLineIds === [] ? $sale->lines : $sale->lines->whereIn('id', $saleLineIds)->values();
            if (($saleLineIds !== [] && $selected->count() !== count($saleLineIds)) || $selected->isEmpty()) throw ValidationException::withMessages(['lines' => __('Select at least one eligible sale line.')]);

            $receipt = GiftReceipt::query()->create([
                'sale_id' => $sale->id, 'branch_id' => $sale->branch_id, 'store_id' => $sale->store_id,
                'issued_by' => $actor->id, 'reference' => $this->reference((int) $sale->branch_id), 'status' => 'active',
                'idempotency_key' => $idempotencyKey, 'lock_version' => 1,
            ]);
            foreach ($selected as $line) {
                $receipt->lines()->create([
                    'sale_line_id' => $line->id, 'product_id' => $line->product_id, 'line_number' => $line->line_number,
                    'item_code' => $line->item_code, 'name_ar' => $line->name_ar, 'name_en' => $line->name_en,
                    'quantity' => $line->quantity,
                ]);
            }
            app(RecordAuditEvent::class)->execute('retail', 'gift_receipt_issued', $receipt, null, [
                'reference' => $receipt->reference, 'sale_id' => $sale->id, 'line_count' => $selected->count(), 'prices_suppressed' => true,
            ], (int) $sale->branch_id, (int) $sale->store_id, metadata: ['actor_id' => $actor->id]);
            return $receipt->load('lines', 'sale');
        }, 5);
    }

    public function print(User $actor, GiftReceipt $receipt, string $idempotencyKey, string $format = 'thermal', ?string $reason = null, bool $requestedReprint = false): GiftReceiptPrintEvent
    {
        $receipt = GiftReceipt::query()->visibleTo($actor)->with('sale')->whereKey($receipt->id)->firstOrFail();
        return DB::transaction(function () use ($actor, $receipt, $idempotencyKey, $format, $reason, $requestedReprint): GiftReceiptPrintEvent {
            $receipt = GiftReceipt::query()->visibleTo($actor)->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $existing = GiftReceiptPrintEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) return $existing;
            $isReprint = $requestedReprint || $receipt->printEvents()->exists();
            $this->authorize($actor, $isReprint ? 'gift_receipts.reprint' : 'gift_receipts.print');
            $event = GiftReceiptPrintEvent::query()->create([
                'gift_receipt_id' => $receipt->id, 'printed_by' => $actor->id, 'format' => in_array($format, ['thermal', 'a4'], true) ? $format : 'thermal',
                'is_reprint' => $isReprint, 'reason' => $reason, 'idempotency_key' => $idempotencyKey, 'printed_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute('retail', $isReprint ? 'gift_receipt_reprinted' : 'gift_receipt_printed', $receipt, null, [
                'reference' => $receipt->reference, 'is_reprint' => $isReprint, 'prices_suppressed' => true,
            ], (int) $receipt->branch_id, (int) $receipt->store_id, reasonText: $reason, metadata: ['actor_id' => $actor->id]);
            return $event;
        }, 5);
    }

    public function validate(User $actor, string $reference): GiftReceipt
    {
        $this->authorize($actor, 'gift_receipts.validate');
        $receipt = GiftReceipt::query()->visibleTo($actor)->with(['sale', 'lines'])->where('reference', trim($reference))->first();
        if ($receipt === null) {
            app(RecordAuditEvent::class)->execute('retail', 'gift_receipt_validation_denied', null, null, null, reasonCode: 'unknown_reference', metadata: ['actor_id' => $actor->id]);
            throw ValidationException::withMessages(['reference' => __('This Gift Receipt is not valid in your scope.')]);
        }
        if ($receipt->status !== 'active') {
            app(RecordAuditEvent::class)->execute('retail', 'gift_receipt_validation_denied', $receipt, ['status' => $receipt->status], null, (int) $receipt->branch_id, (int) $receipt->store_id, reasonCode: 'not_active', metadata: ['actor_id' => $actor->id]);
            throw ValidationException::withMessages(['reference' => __('This Gift Receipt has already been used or voided.')]);
        }
        app(RecordAuditEvent::class)->execute('retail', 'gift_receipt_validated', $receipt, null, ['reference' => $receipt->reference], (int) $receipt->branch_id, (int) $receipt->store_id, metadata: ['actor_id' => $actor->id]);
        return $receipt;
    }

    private function authorize(User $actor, string $permission): void
    {
        abort_unless($actor->is_super_admin || $actor->can($permission), 403);
    }

    private function reference(int $branchId): string
    {
        if (DocumentSequence::query()->where('document_type', 'gift_receipt')->exists()) return app(AllocateDocumentNumber::class)->executeForBranch('gift_receipt', $branchId);
        return 'GR-'.strtoupper(Str::random(24));
    }
}
