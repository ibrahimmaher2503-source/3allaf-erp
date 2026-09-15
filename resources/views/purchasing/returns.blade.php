<?php

use App\Models\User;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\CancelPurchaseReturnAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\RejectPurchaseReturnAction;
use App\Modules\Purchasing\Actions\ReversePurchaseReturnAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Actions\UpdatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Supplier Returns')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showFormModal = false;

    public ?int $selectedInvoiceId = null;

    public ?int $selectedSupplierId = null;

    public string $returnProductEntry = '';

    public bool $supplierProductsOnly = true;

    public string $confirmedSupplierId = '';

    public string $pendingSupplierId = '';

    public bool $showSupplierChangeConfirmation = false;

    public ?int $selectedReturnId = null;

    public ?int $transitionReturnId = null;

    public bool $showTransitionModal = false;

    public string $transitionAction = '';

    public string $transitionReason = '';

    public ?int $selectedReasonId = null;

    /** @var array<int, array<string, string>> */
    public array $returnLines = [];

    public function mount(): void
    {
        Gate::authorize('purchase_returns.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('purchase_returns.create');
        if (! SupplierReturnReason::query()->active()->exists()) {
            Flux::toast(__('No active supplier return reasons are configured yet.'), variant: 'warning');

            return;
        }

        $this->resetValidation();
        $this->selectedInvoiceId = null;
        $this->selectedSupplierId = null;
        $this->confirmedSupplierId = '';
        $this->pendingSupplierId = '';
        $this->supplierProductsOnly = true;
        $this->returnProductEntry = '';
        $this->selectedReturnId = null;
        $this->selectedReasonId = null;
        $this->returnLines = [];
        $this->showFormModal = true;
    }

    public function editDraft(int $id): void
    {
        Gate::authorize('purchase_returns.edit');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $return = PurchaseReturn::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with('lines.product')
            ->where('status', 'draft')
            ->findOrFail($id);
        $this->selectedReturnId = $return->id;
        $this->selectedInvoiceId = $return->purchase_invoice_id;
        $this->selectedSupplierId = $return->supplier_id;
        $this->confirmedSupplierId = (string) $return->supplier_id;
        $this->selectedReasonId = $return->reason_id;
        $this->returnLines = $return->lines->map(fn ($line): array => ['purchase_invoice_line_id' => (string) $line->purchase_invoice_line_id, 'quantity' => (string) $line->quantity, 'unit_cost' => (string) $line->unit_cost, 'available' => '', 'product' => str_starts_with(app()->getLocale(), 'ar') ? ($line->product?->name_ar ?: $line->product?->name_en ?: '#'.$line->product_id) : ($line->product?->name_en ?: $line->product?->name_ar ?: '#'.$line->product_id)])->values()->all();
        $this->showFormModal = true;
    }

    public function updatedSelectedSupplierId(?int $supplierId): void
    {
        $next = $supplierId ? (string) $supplierId : '';
        if ($this->confirmedSupplierId !== '' && $next !== $this->confirmedSupplierId && $this->returnLines !== []) {
            $this->pendingSupplierId = $next;
            $this->selectedSupplierId = (int) $this->confirmedSupplierId;
            $this->showSupplierChangeConfirmation = true;

            return;
        }
        $this->confirmedSupplierId = $next;
        $this->selectedInvoiceId = null;
        $this->returnLines = [];
        $this->supplierProductsOnly = $next !== '';
    }

    public function confirmSupplierChange(): void
    {
        $this->selectedSupplierId = $this->pendingSupplierId === '' ? null : (int) $this->pendingSupplierId;
        $this->confirmedSupplierId = $this->pendingSupplierId;
        $this->pendingSupplierId = '';
        $this->selectedInvoiceId = null;
        $this->returnLines = [];
        $this->returnProductEntry = '';
        $this->supplierProductsOnly = $this->confirmedSupplierId !== '';
        $this->showSupplierChangeConfirmation = false;
    }

    public function cancelSupplierChange(): void
    {
        $this->pendingSupplierId = '';
        $this->showSupplierChangeConfirmation = false;
    }

    public function updatedSelectedInvoiceId(?int $invoiceId): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $this->returnLines = [];
        if ($invoiceId === null) {
            return;
        }

        $invoice = PurchaseInvoice::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with('lines.product')
            ->where('status', 'approved')
            ->where('supplier_id', $this->selectedSupplierId)
            ->findOrFail($invoiceId);
        $this->returnProductEntry = '';
        $this->dispatch('purchasing-product-focus');
    }

    public function selectReturnProduct(int $productId): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $this->selectedInvoiceId !== null && $this->selectedSupplierId !== null, 422, __('Select a supplier and approved invoice before adding products.'));
        $invoice = PurchaseInvoice::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->where('status', 'approved')
            ->where('supplier_id', $this->selectedSupplierId)
            ->with(['lines' => fn ($query) => $query->where('product_id', $productId)->with('product')])
            ->findOrFail($this->selectedInvoiceId);
        $line = $invoice->lines->firstOrFail();
        $entry = $this->returnLineEntry($invoice, $line);
        if ($entry === null) {
            Flux::toast(__('This invoice line has no quantity available to return.'), variant: 'warning');

            return;
        }
        if (collect($this->returnLines)->contains(fn (array $current): bool => (int) $current['purchase_invoice_line_id'] === $line->id)) {
            Flux::toast(__('This product is already in the supplier return.'), variant: 'warning');

            return;
        }
        $this->returnLines[] = $entry;
        $this->returnProductEntry = '';
        $this->dispatch('return-line-focus', index: array_key_last($this->returnLines));
    }

    public function removeReturnLine(int $index): void
    {
        if (array_key_exists($index, $this->returnLines)) {
            array_splice($this->returnLines, $index, 1);
        }
        $this->dispatch('purchasing-product-focus');
    }

    /** @return array<string, string>|null */
    private function returnLineEntry(PurchaseInvoice $invoice, PurchaseInvoiceLine $line): ?array
    {
        $returned = PurchaseReturnLine::query()
            ->where('purchase_invoice_line_id', $line->id)
            ->when($this->selectedReturnId !== null, fn ($query) => $query->where('purchase_return_id', '!=', $this->selectedReturnId))
            ->whereHas('purchaseReturn', fn ($query) => $query->whereNotIn('status', ['cancelled', 'rejected', 'reversed']))
            ->sum('quantity');
        $remaining = bcsub((string) $line->quantity_received, (string) $returned, 6);
        $onHand = (string) (StockBalance::query()->where('store_id', $invoice->store_id)->where('product_id', $line->product_id)->value('on_hand') ?? '0');
        $available = bccomp($remaining, $onHand, 6) <= 0 ? $remaining : $onHand;
        if (bccomp($available, '0', 6) <= 0) {
            return null;
        }

        return [
            'purchase_invoice_line_id' => (string) $line->id,
            'quantity' => bccomp($available, '1', 6) < 0 ? $available : '1',
            'unit_cost' => (string) $line->unit_cost,
            'available' => $available,
            'product' => str_starts_with(app()->getLocale(), 'ar') ? ($line->product?->name_ar ?: $line->product?->name_en ?: '#'.$line->product_id) : ($line->product?->name_en ?: $line->product?->name_ar ?: '#'.$line->product_id),
        ];
    }

    public function saveDraft(CreatePurchaseReturnDraftAction $create, UpdatePurchaseReturnDraftAction $update): void
    {
        Gate::authorize($this->selectedReturnId === null ? 'purchase_returns.create' : 'purchase_returns.edit');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
        $this->validate([
            'selectedInvoiceId' => [
                'required',
                'integer',
                Rule::exists('purchase_invoices', 'id')->where(
                    fn ($query) => $query->whereIn('store_id', Store::query()->visibleTo($actor)->select('id')),
                ),
            ],
            'selectedReasonId' => 'required|integer|exists:supplier_return_reasons,id',
            'returnLines' => 'required|array|min:1',
            'returnLines.*.purchase_invoice_line_id' => 'required|integer',
            'returnLines.*.quantity' => 'required|integer|min:1',
        ]);
        $existingReturn = $this->selectedReturnId === null
            ? null
            : PurchaseReturn::query()
                ->whereIn('store_id', $visibleStoreIds)
                ->findOrFail($this->selectedReturnId);

        try {
            $return = $this->selectedReturnId === null
                ? $create->execute($this->selectedInvoiceId, $this->selectedReasonId, $this->returnLines)
                : $update->execute($this->selectedReturnId, $this->selectedReasonId, $this->returnLines, $existingReturn->lock_version);
            $this->showFormModal = false;
            Flux::toast(__('Supplier return saved as draft.'), variant: 'success');
            $this->dispatch('supplier-return-created', id: $return->id);
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function submitReturn(int $id, SubmitPurchaseReturnAction $action): void
    {
        Gate::authorize('purchase_returns.edit');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $return = PurchaseReturn::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->findOrFail($id);
        try {
            $action->execute($return->id, $return->lock_version);
            Flux::toast(__('Supplier return submitted for approval.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function approveReturn(int $id, ApprovePurchaseReturnAction $action): void
    {
        Gate::authorize('purchase_returns.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $return = PurchaseReturn::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->findOrFail($id);
        try {
            $action->execute($return->id, $return->lock_version);
            Flux::toast(__('Supplier return approved and stock cost reversed.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function openTransitionModal(int $id, string $action): void
    {
        Gate::authorize(match ($action) {
            'cancel' => 'purchase_returns.cancel',
            'reject' => 'purchase_returns.reject',
            'reverse' => 'purchase_returns.reverse',
            default => throw new InvalidArgumentException(__('Unknown supplier return transition.')),
        });
        $this->resetValidation();
        $this->transitionReturnId = $id;
        $this->transitionAction = $action;
        $this->transitionReason = '';
        $this->showTransitionModal = true;
    }

    public function executeTransition(CancelPurchaseReturnAction $cancel, RejectPurchaseReturnAction $reject, ReversePurchaseReturnAction $reverse): void
    {
        $ability = match ($this->transitionAction) {
            'cancel' => 'purchase_returns.cancel',
            'reject' => 'purchase_returns.reject',
            'reverse' => 'purchase_returns.reverse',
            default => throw new InvalidArgumentException(__('Unknown supplier return transition.')),
        };
        Gate::authorize($ability);
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $this->validate(['transitionReason' => 'required|string|min:3|max:500']);
        $return = PurchaseReturn::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->findOrFail($this->transitionReturnId);

        try {
            $action = match ($this->transitionAction) {
                'cancel' => $cancel,
                'reject' => $reject,
                'reverse' => $reverse,
            };
            $action->execute($return->id, $this->transitionReason, $return->lock_version);
            $this->transitionReturnId = null;
            $this->transitionAction = '';
            $this->transitionReason = '';
            $this->showTransitionModal = false;
            Flux::toast(__('Supplier return transition completed.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function render(): View
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
        $returns = PurchaseReturn::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with(['supplier', 'store', 'reason', 'purchaseInvoice'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('return_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('purchaseInvoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        $sourceInvoices = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->where('status', 'approved')
            ->with(['supplier', 'lines.product'])
            ->latest()
            ->limit(100)
            ->get();
        $lineIds = $sourceInvoices->flatMap(fn ($invoice) => $invoice->lines->pluck('id'))->values();
        $returnedQuantities = PurchaseReturnLine::query()
            ->whereIn('purchase_invoice_line_id', $lineIds)
            ->whereHas('purchaseReturn', fn ($query) => $query->whereNotIn('status', ['cancelled', 'rejected', 'reversed']))
            ->selectRaw('purchase_invoice_line_id, SUM(quantity) as quantity')
            ->groupBy('purchase_invoice_line_id')
            ->pluck('quantity', 'purchase_invoice_line_id');
        $balances = StockBalance::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->whereIn('product_id', $sourceInvoices->flatMap(fn ($invoice) => $invoice->lines->pluck('product_id'))->unique())
            ->get()
            ->keyBy(fn ($balance) => $balance->store_id.':'.$balance->product_id);
        $sourceInvoices = $sourceInvoices->filter(fn ($invoice): bool => $invoice->lines->contains(function ($line) use ($invoice, $returnedQuantities, $balances): bool {
            $remaining = bcsub((string) $line->quantity_received, (string) ($returnedQuantities[$line->id] ?? '0'), 6);
            $onHand = (string) ($balances[$invoice->store_id.':'.$line->product_id]?->on_hand ?? '0');

            $available = bccomp($remaining, $onHand, 6) <= 0 ? $remaining : $onHand;

            return bccomp($available, '0', 6) > 0;
        }))->values();

        return view('purchasing.returns', [
            'returns' => $returns,
            'sourceInvoices' => $sourceInvoices,
            'reasons' => SupplierReturnReason::query()->active()->orderBy('code')->get(),
            'hasReasonCatalog' => SupplierReturnReason::query()->active()->exists(),
        ]);
    }
};
?>

<x-app.page
    :title="str_starts_with(app()->getLocale(), 'ar') ? 'مرتجعات الموردين' : __('Supplier returns')"
    :description="str_starts_with(app()->getLocale(), 'ar') ? 'تابع المرتجعات المرتبطة بفواتير الشراء وحالات الاعتماد والعكس.' : __('Track source-linked returns, approvals, posting, and reversal states.')"
    :breadcrumbs="str_starts_with(app()->getLocale(), 'ar') ? 'المشتريات والموردون' : 'Purchasing & suppliers'"
    max-width="7xl"
    class="space-y-6"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="supplier-returns-filters">
        <flux:button href="{{ route('purchasing.invoices') }}" variant="subtle" icon="arrow-left">{{ __('Purchase invoices') }}</flux:button>
        @can('company_settings.view')
            <flux:button href="{{ route('purchasing.returns.settings') }}" variant="subtle" icon="adjustments-horizontal">{{ __('Return settings') }}</flux:button>
        @endcan
        @can('purchase_returns.create')
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus" :disabled="!$hasReasonCatalog">{{ __('New supplier return') }}</flux:button>
        @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <flux:callout variant="info" icon="information-circle">
        {{ __('Phase 1 rule: every return line must reference an approved purchase invoice line. No WAC or fallback cost is accepted.') }}
    </flux:callout>

    <flux:card id="supplier-returns-filters" class="scroll-mt-24">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Return number or invoice number') }}" />
            </div>
            <div class="w-48">
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                    <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                    <flux:select.option value="reversed">{{ __('Reversed') }}</flux:select.option>
                </flux:select>
            </div>
        </div>
    </flux:card>

    <flux:card class="overflow-hidden p-0">
        <flux:table class="responsive-resource-table" aria-label="{{ __('Supplier returns') }}">
            <flux:table.columns>
                <flux:table.column>{{ __('Return') }}</flux:table.column>
                <flux:table.column>{{ __('Original invoice') }}</flux:table.column>
                <flux:table.column>{{ __('Supplier') }}</flux:table.column>
                <flux:table.column>{{ __('Reason') }}</flux:table.column>
                <flux:table.column>{{ __('Total cost') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($returns as $return)
                    <flux:table.row :key="$return->id">
                        <flux:table.cell data-primary><a class="font-medium underline" href="{{ route('purchasing.returns.show', $return) }}">{{ $return->return_number ?: '#'.$return->id }}</a></flux:table.cell>
                        <flux:table.cell data-label="{{ __('Original invoice') }}">{{ $return->purchaseInvoice?->invoice_number ?: '#'.$return->purchase_invoice_id }}</flux:table.cell>
                        <flux:table.cell data-label="{{ __('Supplier') }}">{{ str_starts_with(app()->getLocale(), 'ar') ? ($return->supplier?->name_ar ?: $return->supplier?->name_en) : ($return->supplier?->name_en ?: $return->supplier?->name_ar) }}</flux:table.cell>
                        <flux:table.cell data-label="{{ __('Reason') }}">{{ $return->reason?->code ?: __('Unavailable') }}</flux:table.cell>
                        <flux:table.cell data-label="{{ __('Total cost') }}"><x-money :amount="$return->total_amount" currency="EGP" /></flux:table.cell>
                        <flux:table.cell data-label="{{ __('Status') }}"><x-status.badge :status="$return->status" /></flux:table.cell>
                        <flux:table.cell data-label="{{ __('Actions') }}">
                            @if ($return->status === 'draft')
                                @can('purchase_returns.edit')
                                    <x-actions.button semantic="edit" :label="__('Edit')" wire:click="editDraft({{ $return->id }})">{{ __('Edit') }}</x-actions.button>
                                    <flux:button size="sm" variant="subtle" wire:click="submitReturn({{ $return->id }})">{{ __('Submit') }}</flux:button>
                                @endcan
                                @can('purchase_returns.cancel')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'cancel')">{{ __('Cancel') }}</flux:button>
                                @endcan
                            @elseif ($return->status === 'submitted')
                                @can('purchase_returns.approve')
                                    <x-actions.button semantic="approve" :label="__('Approve & post')" wire:click="approveReturn({{ $return->id }})">{{ __('Approve & post') }}</x-actions.button>
                                @endcan
                                @can('purchase_returns.reject')
                                    <x-actions.button semantic="reject" :label="__('Reject')" wire:click="openTransitionModal({{ $return->id }}, 'reject')">{{ __('Reject') }}</x-actions.button>
                                @endcan
                                @can('purchase_returns.cancel')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'cancel')">{{ __('Cancel') }}</flux:button>
                                @endcan
                            @elseif ($return->status === 'approved')
                                @can('purchase_returns.reverse')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'reverse')">{{ __('Reverse') }}</flux:button>
                                @endcan
                            @else
                                <flux:text>{{ __('No further actions') }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <div class="flex flex-col items-center gap-2 py-10 text-center">
                                <flux:icon name="arrow-uturn-left" class="size-8 text-text-muted" />
                                <flux:heading size="sm">{{ __('No supplier returns yet.') }}</flux:heading>
                                <flux:text>{{ __('Create a supplier return from an approved purchase invoice.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <x-tables.pagination :paginator="$returns" />
    </flux:card>

    @if ($showTransitionModal)
        <flux:modal wire:model.self="showTransitionModal" class="md:w-[min(96vw,600px)]">
            <form wire:submit="executeTransition" class="space-y-5">
                <flux:heading size="lg">{{ match ($transitionAction) { 'cancel' => __('Cancel supplier return'), 'reject' => __('Reject supplier return'), 'reverse' => __('Reverse supplier return') } }}</flux:heading>
                <flux:callout variant="warning">{{ __('This transition is audited and cannot be undone from this screen. A reason is required.') }}</flux:callout>
                <flux:textarea wire:model="transitionReason" :label="__('Reason')" required rows="4" />
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showTransitionModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Confirm transition') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($showFormModal)
        <flux:modal wire:model.self="showFormModal" class="md:w-[min(96vw,900px)]">
            <form wire:submit="saveDraft" class="space-y-5" data-product-line-editor>
                <flux:heading size="lg">{{ __('New supplier return draft') }}</flux:heading>
                <flux:callout variant="warning" icon="shield-check">
                    {{ __('The source invoice and line cost are server-authoritative. The cost field below is read-only and cannot be replaced with current WAC.') }}
                </flux:callout>
                <flux:select wire:model.live="selectedSupplierId" :label="__('Supplier')" :disabled="$selectedReturnId !== null">
                    <flux:select.option value="">{{ __('Select supplier') }}</flux:select.option>
                    @foreach($sourceInvoices->pluck('supplier')->filter()->unique('id') as $supplier)
                        <flux:select.option value="{{ $supplier->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->name_ar ?: $supplier->name_en) : ($supplier->name_en ?: $supplier->name_ar) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="selectedInvoiceId" :label="__('Approved purchase invoice')" :disabled="$selectedSupplierId === null || $selectedReturnId !== null">
                    <flux:select.option value="">{{ __('Select approved invoice') }}</flux:select.option>
                    @foreach ($sourceInvoices->where('supplier_id', $selectedSupplierId) as $invoice)
                        <flux:select.option value="{{ $invoice->id }}">{{ $invoice->invoice_number ?: '#'.$invoice->id }} — {{ str_starts_with(app()->getLocale(), 'ar') ? ($invoice->supplier?->name_ar ?: $invoice->supplier?->name_en) : ($invoice->supplier?->name_en ?: $invoice->supplier?->name_ar) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:heading size="base">{{ __('Return product lines') }}</flux:heading>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:checkbox wire:model.live="supplierProductsOnly" :label="__('Use supplier products only')" :disabled="$selectedSupplierId === null" />
                            <flux:button type="button" size="xs" variant="subtle" icon="plus" onclick="this.closest('[data-product-line-editor]').querySelector('[data-product-search]')?.focus()" :disabled="$selectedSupplierId === null || $selectedInvoiceId === null">{{ __('Add item') }}</flux:button>
                        </div>
                    </div>
                    <x-product-line-lookup wire:model="returnProductEntry" :label="__('Product')" :purchasing="true" :supplier-id="$selectedSupplierId" :supplier-only="$supplierProductsOnly" :purchase-invoice-id="$selectedInvoiceId" :disabled="$selectedSupplierId === null || $selectedInvoiceId === null" x-on:product-selected="$wire.selectReturnProduct($event.detail.id)" />
                    @if($selectedSupplierId !== null && $selectedInvoiceId === null)<p class="text-xs text-zinc-500">{{ __('Select an approved invoice before searching its returnable products.') }}</p>@endif
                </div>
                <flux:select wire:model="selectedReasonId" :label="__('Return reason')">
                    <flux:select.option value="">{{ __('Select required reason') }}</flux:select.option>
                    @foreach ($reasons as $reason)
                        <flux:select.option value="{{ $reason->id }}">{{ $reason->code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? ($reason->label_ar ?: $reason->label_en) : ($reason->label_en ?: $reason->label_ar) }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($returnLines !== [])
                    <div class="space-y-3">
                        <flux:heading size="base">{{ __('Invoice lines') }}</flux:heading>
                        @foreach ($returnLines as $index => $line)
                            <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-6" wire:key="return-line-{{ $line['purchase_invoice_line_id'] }}" data-product-line data-product-value="{{ $line['purchase_invoice_line_id'] }}" x-on:return-line-focus.window="if($event.detail.index==={{$index}})$nextTick(()=>$el.querySelector('input')?.focus())">
                                <div class="md:col-span-2">
                                    <flux:label>{{ __('Product') }}</flux:label>
                                    <flux:text class="mt-2 font-medium">{{ $line['product'] }}</flux:text>
                                    <flux:text size="sm">{{ __('Eligible quantity') }}: {{ $line['available'] }}</flux:text>
                                </div>
                                <flux:input wire:model="returnLines.{{ $index }}.quantity" type="number" min="1" step="1" :label="__('Quantity')" data-line-quantity />
                                <flux:input wire:model="returnLines.{{ $index }}.unit_cost" type="text" readonly :label="__('Unit cost')" data-line-price data-price-kind="cost" />
                                <div><flux:label>{{ __('Line total') }}</flux:label><output class="mt-2 flex h-10 items-center justify-end rounded-lg bg-surface-muted px-3 font-mono" data-line-total>0.00</output></div>
                                <div class="flex items-end justify-end"><flux:button type="button" variant="danger" size="sm" wire:click="removeReturnLine({{ $index }})">{{ __('Remove') }}</flux:button></div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="warning">{{ __('Select an approved invoice, then search and add at least one returnable product.') }}</flux:callout>
                @endif
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" :disabled="$returnLines === []">{{ __('Save draft') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <flux:modal wire:model.self="showSupplierChangeConfirmation" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Change supplier and clear product lines?') }}</flux:heading>
            <flux:callout variant="warning">{{ __('Changing the supplier clears the selected invoice and existing return lines.') }}</flux:callout>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="subtle" wire:click="cancelSupplierChange">{{ __('Keep current supplier') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="confirmSupplierChange">{{ __('Change supplier and clear lines') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-app.page>
