<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\RetailRefundCalculator;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

final class RefundWizard extends Component
{
    public bool $open = false;
    public int $step = 1;
    public string $lookupTerm = '';
    public ?int $saleId = null;
    public ?int $giftReceiptId = null;

    /** @var array<int, string> */
    public array $quantities = [];
    /** @var array<int, string> */
    public array $conditions = [];
    /** @var array<int, string> */
    public array $dispositions = [];
    /** @var array<int, string> */
    public array $returnStoreIds = [];
    /** @var array<int, array<string, string|int|null>> */
    public array $allocations = [];
    /** @var array<string, string> */
    public array $totals = [];

    public string $refundMethod = 'original_tender';
    public string $reason = '';
    public string $idempotencyKey = '';
    public bool $submitting = false;
    public ?int $resultReturnId = null;
    public string $resultStatus = '';

    public function mount(): void
    {
        Gate::authorize('returns.create');
        $this->idempotencyKey = (string) Str::uuid();
    }

    #[On('open-pos-refund')]
    public function openWizard(): void
    {
        Gate::authorize('returns.create');
        Gate::authorize('returns.submit');
        $this->reset([
            'lookupTerm', 'saleId', 'giftReceiptId', 'quantities', 'conditions', 'dispositions',
            'returnStoreIds', 'allocations', 'totals', 'refundMethod', 'reason', 'resultReturnId',
            'resultStatus', 'submitting',
        ]);
        $this->open = true;
        $this->step = 1;
        $this->idempotencyKey = (string) Str::uuid();
        $this->dispatch('focus-refund-lookup');
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    public function lookup(RetailRefundCalculator $calculator): void
    {
        Gate::authorize('returns.create');
        $term = trim($this->lookupTerm);
        if ($term === '') {
            $this->addError('lookupTerm', __('Enter an invoice, order, receipt, or barcode code.'));

            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        $receipt = GiftReceipt::query()->visibleTo($actor)->where('reference', $term)->where('status', 'active')->first();
        $sale = Sale::query()->visibleTo($actor)->approved()
            ->where(function ($query) use ($term, $receipt): void {
                $query->where('document_number', $term);
                if (ctype_digit($term)) {
                    $query->orWhereKey((int) $term);
                }
                if ($receipt !== null) {
                    $query->orWhereKey($receipt->sale_id);
                }
            })
            ->with(['store.company', 'lines.product', 'payments.paymentMethod'])
            ->first();
        if ($sale === null) {
            app(RecordAuditEvent::class)->execute('retail', 'pos_refund_lookup_rejected', Sale::class, metadata: ['lookup_hash' => hash('sha256', $term)], explicitSourceId: 'unresolved');
            $this->addError('lookupTerm', __('No posted sale matching this code is available in your authorized scope.'));

            return;
        }

        $context = app(PosContextResolver::class)->resolve($actor);
        if ($context->isReady() && ((int) $context->branch?->id !== (int) $sale->branch_id || (int) $context->store?->id !== (int) $sale->store_id)) {
            app(RecordAuditEvent::class)->execute('retail', 'pos_refund_lookup_rejected', $sale, branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id, metadata: ['reason' => 'active_outlet_mismatch']);
            $this->addError('lookupTerm', __('The sale belongs to another outlet and cannot be refunded from this POS context.'));

            return;
        }

        $this->saleId = (int) $sale->id;
        $this->giftReceiptId = $receipt?->id;
        $this->quantities = [];
        $this->conditions = [];
        $this->dispositions = [];
        $this->returnStoreIds = [];
        foreach ($sale->lines as $line) {
            $remaining = $calculator->remainingQuantity($line);
            $this->quantities[$line->id] = '0';
            $this->conditions[$line->id] = 'sellable';
            $this->dispositions[$line->id] = 'restock';
            $this->returnStoreIds[$line->id] = (string) $sale->store_id;
            if (bccomp($remaining, '0', 6) < 0) {
                $this->quantities[$line->id] = '0';
            }
        }
        $this->refundMethod = 'original_tender';
        $this->allocations = [];
        $this->totals = [];
        $this->step = 2;
        $this->resetErrorBag();
        app(RecordAuditEvent::class)->execute('retail', 'pos_refund_sale_verified', $sale, branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id, metadata: ['gift_receipt_id' => $receipt?->id]);
    }

    public function setAllRefundable(RetailRefundCalculator $calculator): void
    {
        $sale = $this->sale();
        if ($sale === null) {
            return;
        }
        foreach ($sale->lines as $line) {
            $this->quantities[$line->id] = $calculator->remainingQuantity($line);
        }
    }

    public function next(RetailRefundCalculator $calculator): void
    {
        $this->resetErrorBag();
        if ($this->step === 1) {
            $this->lookup($calculator);

            return;
        }
        if ($this->step === 2) {
            $this->step = 3;
        
            return;
        }
        if ($this->step === 3 || $this->step === 4) {
            if (! $this->refreshPreview($calculator)) {
                return;
            }
            $this->step++;
            if ($this->step === 5) {
                $this->prepareAllocations($calculator);
            }
        
            return;
        }
        if ($this->step === 5) {
            if (! $this->refreshPreview($calculator) || ! $this->validateRefundMethod()) {
                return;
            }
            $this->step = 6;
        }
    }

    public function back(): void
    {
        if ($this->step > 1 && $this->resultReturnId === null) {
            $this->step--;
            $this->resetErrorBag();
        }
    }

    public function chooseRefundMethod(string $method, RetailRefundCalculator $calculator): void
    {
        if (! in_array($method, ['original_tender', 'cash_refund', 'gift_card'], true)) {
            return;
        }
        $this->refundMethod = $method;
        $this->prepareAllocations($calculator);
    }

    public function submit(RetailRefundCalculator $calculator, RetailReturnAction $action): void
    {
        Gate::authorize('returns.create');
        Gate::authorize('returns.submit');
        if ($this->submitting) {
            return;
        }
        $this->submitting = true;
        $this->resetErrorBag();

        try {
            $sale = $this->sale();
            if ($sale === null || ! $this->refreshPreview($calculator) || ! $this->validateRefundMethod()) {
                return;
            }
            /** @var User $actor */
            $actor = auth()->user();
            $document = $action->create($actor, [
                'source_sale_id' => $sale->id,
                'source_gift_receipt_id' => $this->giftReceiptId,
                'settlement_type' => $this->refundMethod,
                'reason' => $this->reason,
                'lines' => $this->selectedLines(),
            ], $this->idempotencyKey);
            $document = $action->submit($actor, $document);

            if ($actor->canBypassApproval() && Gate::forUser($actor)->allows('returns.approve') && Gate::forUser($actor)->allows('returns.complete')) {
                $document = $action->approve($actor, $document);
                $document = $action->complete($actor, $document, $this->idempotencyKey.':complete', allocations: $this->refundMethod === 'gift_card' ? [] : $this->allocations);
            }

            $this->resultReturnId = (int) $document->id;
            $this->resultStatus = (string) $document->status;
            $this->step = 7;
        } catch (\Throwable $exception) {
            $this->addError('refund', UserSafeError::message($exception));
        } finally {
            $this->submitting = false;
        }
    }

    public function render(RetailRefundCalculator $calculator): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $sale = $this->sale();
        $stores = $sale === null ? collect() : $calculator->returnStores($actor, $sale);
        $cashMethod = PaymentMethod::query()->where('status', 'active')->get()->first(fn (PaymentMethod $method): bool => $method->isCash());
        $context = app(PosContextResolver::class)->resolve($actor);
        $cashAvailable = $sale !== null && $cashMethod !== null && $context->isReady()
            && (int) $context->branch?->id === (int) $sale->branch_id
            && (int) $context->store?->id === (int) $sale->store_id;
        $remaining = [];
        if ($sale !== null) {
            foreach ($sale->lines as $line) {
                $remaining[$line->id] = $calculator->remainingQuantity($line);
            }
        }
        $result = $this->resultReturnId === null ? null : RetailReturn::query()->visibleTo($actor)->whereKey($this->resultReturnId)->first();

        return view('livewire.pos.refund-wizard', compact('sale', 'stores', 'cashMethod', 'cashAvailable', 'remaining', 'result'));
    }

    private function refreshPreview(RetailRefundCalculator $calculator): bool
    {
        $sale = $this->sale();
        if ($sale === null) {
            $this->addError('refund', __('Find and verify the original sale first.'));

            return false;
        }
        try {
            /** @var User $actor */
            $actor = auth()->user();
            $receipt = $this->giftReceiptId === null ? null : GiftReceipt::query()->visibleTo($actor)->whereKey($this->giftReceiptId)->first();
            $prepared = $calculator->prepare($actor, $sale, $this->selectedLines(), $receipt);
            $this->totals = $prepared['totals'];

            return true;
        } catch (\Throwable $exception) {
            $this->addError('refund', UserSafeError::message($exception));

            return false;
        }
    }

    private function prepareAllocations(RetailRefundCalculator $calculator): void
    {
        $sale = $this->sale();
        $total = (string) ($this->totals['settlement_value'] ?? '0.00');
        if ($sale === null || bccomp($total, '0.00', 2) <= 0) {
            $this->allocations = [];

            return;
        }
        if ($this->refundMethod === 'original_tender') {
            try {
                $this->allocations = $calculator->defaultAllocations($sale, $total);
            } catch (\Throwable $exception) {
                $this->allocations = [];
                $this->addError('refundMethod', UserSafeError::message($exception));
            }
        } elseif ($this->refundMethod === 'cash_refund') {
            $cash = PaymentMethod::query()->where('status', 'active')->get()->first(fn (PaymentMethod $method): bool => $method->isCash());
            $this->allocations = $cash === null ? [] : [['method_id' => $cash->id, 'original_payment_id' => null, 'amount' => $total, 'safe_reference' => '']];
        } else {
            $this->allocations = [];
        }
    }

    private function validateRefundMethod(): bool
    {
        if (trim($this->reason) === '') {
            $this->addError('reason', __('A refund reason is required.'));

            return false;
        }
        /** @var User $actor */
        $actor = auth()->user();
        $sale = $this->sale();
        $containsCash = $this->refundMethod === 'cash_refund';
        if ($sale !== null && $this->refundMethod === 'original_tender') {
            $cashPaymentIds = $sale->payments->filter(fn ($payment): bool => $payment->paymentMethod?->isCash() ?? false)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $containsCash = collect($this->allocations)->contains(fn (array $line): bool => in_array((int) ($line['original_payment_id'] ?? 0), $cashPaymentIds, true));
        }
        if ($containsCash) {
            $context = app(PosContextResolver::class)->resolve($actor);
            if ($sale === null || ! $context->isReady() || (int) $context->store?->id !== (int) $sale->store_id || (int) $context->branch?->id !== (int) $sale->branch_id) {
                $this->addError('refundMethod', __('Cash refund is unavailable without an open authorized shift and drawer at the original outlet. Choose an eligible non-cash refund method.'));
        
                return false;
            }
        }
        if ($this->refundMethod !== 'gift_card') {
            $sum = collect($this->allocations)->reduce(fn (string $carry, array $line): string => bcadd($carry, (string) ($line['amount'] ?? '0'), 2), '0.00');
            if (bccomp($sum, (string) ($this->totals['settlement_value'] ?? '0.00'), 2) !== 0) {
                $this->addError('allocations', __('Refund payment allocations must equal the final refundable amount exactly.'));

                return false;
            }
        }

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    private function selectedLines(): array
    {
        $lines = [];
        foreach ($this->quantities as $lineId => $quantity) {
            if (! is_numeric($quantity) || bccomp((string) $quantity, '0', 6) <= 0) {
                continue;
            }
            $condition = (string) ($this->conditions[$lineId] ?? 'sellable');
            $lines[] = [
                'sale_line_id' => (int) $lineId,
                'quantity' => (string) $quantity,
                'condition' => $condition,
                'disposition' => (string) ($this->dispositions[$lineId] ?? ($condition === 'sellable' ? 'restock' : 'quarantine')),
                'return_store_id' => (int) ($this->returnStoreIds[$lineId] ?? 0),
            ];
        }

        return $lines;
    }

    private function sale(): ?Sale
    {
        if ($this->saleId === null) {
            return null;
        }
        /** @var User $actor */
        $actor = auth()->user();

        return Sale::query()->visibleTo($actor)->approved()->whereKey($this->saleId)
            ->with(['store.company', 'branch', 'customer', 'lines.product', 'payments.paymentMethod'])
            ->first();
    }
}
