<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class CheckoutPanel extends Component
{
    public string $mode = 'cash';

    /** @var array<int, array<string, mixed>> */
    public array $paymentLines = [];

    public string $notes = '';

    public function mount(PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.view');
        /** @var User $user */
        $user = auth()->user();
        $context = $contexts->resolve($user);
        $order = $orders->active(request(), $user, $context, ! is_numeric(request()->session()->get('pos.completed_sale_id')));
        if ($order === null) {
            return;
        }
        $this->mode = in_array($order->payment_mode, ['cash', 'card', 'split', 'other', 'credit'], true) ? $order->payment_mode : 'cash';
        $this->notes = (string) $order->notes;
        $this->paymentLines = $order->paymentDrafts()->orderBy('line_position')->get()->map(fn ($line): array => [
            'method_id' => $line->payment_method_id === null ? '' : (string) $line->payment_method_id,
            'amount' => (string) ($line->amount ?? ''),
            'tendered' => (string) ($line->tendered_amount ?? ''),
            'evidence_reference' => (string) ($line->safe_reference ?? ''),
            'gift_card_identifier' => (string) ($line->gift_card_identifier ?? ''),
        ])->values()->all();
        if ($this->paymentLines === []) {
            $this->prepareMode($this->mode);
        }
    }

    #[On('pos-cart-updated')]
    public function refreshCheckout(): void {}

    public function chooseMode(string $mode, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (! in_array($mode, ['cash', 'card', 'split', 'other', 'credit'], true)) {
            return;
        }
        if ($this->mode !== $mode || $this->paymentLines === []) {
            $this->mode = $mode;
            $this->prepareMode($mode);
        }
        $this->saveDraft($orders, $contexts);
    }
    
    public function startCheckout(string $mode, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (! in_array($mode, ['cash', 'card', 'split', 'other', 'credit'], true)) {
            return;
        }
        if ($this->mode !== $mode || $this->paymentLines === []) {
            $this->mode = $mode;
            $this->prepareMode($mode);
        }
        $this->saveDraft($orders, $contexts);
        $this->dispatch('open-pos-checkout');
    }

    public function addPaymentLine(PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (count($this->paymentLines) >= 12) {
            $this->addError('paymentLines', __('A split payment supports at most twelve portions.'));

            return;
        }
        if ($this->mode !== 'card') {
            $this->mode = 'split';
        }
        $this->paymentLines[] = $this->emptyLine();
        $this->saveDraft($orders, $contexts);
    }

    public function removePaymentLine(int $index, PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        Gate::authorize('pos_sales.create');
        if (count($this->paymentLines) <= 1) {
            return;
        }
        unset($this->paymentLines[$index]);
        $this->paymentLines = array_values($this->paymentLines);
        if ($this->mode !== 'card') {
            $this->mode = count($this->paymentLines) > 1 ? 'split' : $this->mode;
        }
        $this->saveDraft($orders, $contexts);
    }

    public function updatedPaymentLines(): void
    {
        $this->persistDraftUpdate();
    }

    public function updatedNotes(): void
    {
        $this->persistDraftUpdate();
    }

    private function persistDraftUpdate(): void
    {
        try {
            $this->saveDraft(app(PosOpenOrderManager::class), app(PosContextResolver::class));
        } catch (\Throwable $exception) {
            $this->addError('paymentLines', UserSafeError::message($exception));
        }
    }

    public function render(PosCartSnapshot $snapshot, PosContextResolver $contextResolver, PosOpenOrderManager $orders): View
    {
        /** @var User $user */
        $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $shift = $context->shift;
        $order = $orders->active(request(), $user, $context, ! is_numeric(request()->session()->get('pos.completed_sale_id')));
        $data = $snapshot->build($context);
        $orders->updateTotals($order, $data['preview'] ?? null);
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->limit(100)->get();
        $cashMethod = $methods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $electronicMethods = $methods->reject(fn (PaymentMethod $method): bool => $method->isCash() || (string) $method->type === 'gift_card')->values();
        $invoiceTotal = (string) (($data['preview']['total'] ?? null) ?: '0.00');
        $hasCashDraft = collect($this->paymentLines)->contains(fn (array $line): bool => $cashMethod !== null && (int) ($line['method_id'] ?? 0) === (int) $cashMethod->id);
        $cashPayable = null;
        $cashAdjustment = '0.00';
        $cashRoundingError = null;
        if (($this->mode === 'cash' || $hasCashDraft) && ($data['preview'] ?? null)) {
            try {
                $cashAdjustment = app(PosCalculationService::class)->cashRoundingAdjustment($invoiceTotal);
                $cashPayable = bcadd($invoiceTotal, $cashAdjustment, 2);
            } catch (PosFinancialConfigurationException $exception) {
                $cashRoundingError = UserSafeError::message($exception);
            }
        }
        $payable = (string) ($cashPayable ?? $invoiceTotal);

        return view('livewire.pos.checkout-panel', array_merge($data, compact(
            'context', 'store', 'shift', 'order', 'methods', 'cashMethod', 'electronicMethods',
            'invoiceTotal', 'cashPayable', 'cashAdjustment', 'cashRoundingError', 'payable',
        )));
    }

    private function prepareMode(string $mode): void
    {
        $methods = PaymentMethod::query()->where('status', 'active')->orderBy('code')->limit(100)->get();
        $cash = $methods->first(fn (PaymentMethod $method): bool => $method->isCash());
        $card = $methods->first(fn (PaymentMethod $method): bool => ! $method->isCash() && (string) $method->type !== 'gift_card');
        if ($mode === 'credit') {
            $this->paymentLines = [];
        } elseif ($mode === 'cash' && $cash !== null) {
            $this->paymentLines = [$this->emptyLine((string) $cash->id)];
        } elseif ($mode === 'card' && $card !== null) {
            $this->paymentLines = [$this->emptyLine((string) $card->id)];
        } elseif ($mode === 'split') {
            if (count($this->paymentLines) < 2) {
                $this->paymentLines = array_values(array_filter([
                    $cash ? $this->emptyLine((string) $cash->id) : null,
                    $card ? $this->emptyLine((string) $card->id) : null,
                ]));
            }
        } elseif ($this->paymentLines === []) {
            $this->paymentLines = [$this->emptyLine()];
        }
    }

    /** @return array<string, string> */
    private function emptyLine(string $methodId = ''): array
    {
        return ['method_id' => $methodId, 'amount' => '', 'tendered' => '', 'evidence_reference' => '', 'gift_card_identifier' => ''];
    }

    private function saveDraft(PosOpenOrderManager $orders, PosContextResolver $contexts): void
    {
        /** @var User $user */
        $user = auth()->user();
        $orders->savePaymentDraft(request(), $user, $contexts->resolve($user), $this->mode, $this->notes, $this->paymentLines);
    }
}
