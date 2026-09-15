<?php

use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\StagePurchaseInvoiceImportAction;
use App\Modules\Purchasing\Models\PurchaseInvoiceImportBatch;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Actions\RevokeAttachment;
use App\Modules\Platform\Models\Attachment;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Purchase Invoice Import')] class extends Component
{
    use WithFileUploads;

    public mixed $importFile = null;

    public ?int $selectedBatchId = null;

    public function mount(): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.view');
    }

    public function stage(StagePurchaseInvoiceImportAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $this->validate(['importFile' => 'required|file|mimes:xlsx,csv|max:10240']);

        try {
            $attachment = app(StoreAttachment::class)->execute($this->importFile, 'import_source');
            try {
                $batch = $action->stage($attachment, $this->importFile->getClientOriginalName(), (string) $this->importFile->getMimeType(), (int) $this->importFile->getSize(), (int) auth()->id());
            } catch (Throwable $exception) {
                app(RevokeAttachment::class)->execute(
                    $attachment,
                    __('The purchase-invoice import could not be staged.'),
                    fn (User $user, Attachment $candidate): bool => $candidate->uploaded_by === $user->id && $candidate->source_type === null,
                );
                throw $exception;
            }
            $this->selectedBatchId = $batch->id;
            $this->importFile = null;
            Flux::toast(__('File staged. Review every row before creating drafts.'), variant: 'success');
        } catch (Throwable $exception) {
            $this->addError('importFile', \App\Support\UserSafeError::message($exception));
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function createDrafts(StagePurchaseInvoiceImportAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.approve');
        $batch = PurchaseInvoiceImportBatch::query()->where('created_by', auth()->id())->findOrFail($this->selectedBatchId);

        try {
            $action->createDrafts($batch, app(SavePurchaseInvoiceAction::class));
            Flux::toast(__('Draft invoices created. No stock or WAC was posted.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function cancelBatch(StagePurchaseInvoiceImportAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $batch = PurchaseInvoiceImportBatch::query()->where('created_by', auth()->id())->findOrFail($this->selectedBatchId);

        try {
            $action->cancel($batch);
            Flux::toast(__('Import batch cancelled.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function selectBatch(int $batchId): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.view');
        $this->selectedBatchId = $batchId;
    }

    public function render(): View
    {
        $batches = PurchaseInvoiceImportBatch::query()
            ->where('created_by', auth()->id())
            ->latest()
            ->paginate(10, pageName: 'invoice-imports');
        $selectedBatch = $this->selectedBatchId
            ? PurchaseInvoiceImportBatch::query()->where('created_by', auth()->id())->with(['rows' => fn ($query) => $query->orderBy('row_number')->limit(100)])->find($this->selectedBatchId)
            : null;

        $sourceAttachment = $selectedBatch === null ? null : Attachment::query()
            ->where('source_type', PurchaseInvoiceImportBatch::class)
            ->where('source_id', (string) $selectedBatch->id)
            ->where('purpose', 'import_source')
            ->first();

        return view('purchasing.invoice-import', compact('batches', 'selectedBatch', 'sourceAttachment'));
    }
};
?>

<x-app.page
    :title="__('Purchase Invoice Import')"
    :description="__('Stage, validate, review, and create draft invoices from a private Excel or CSV upload.')"
    max-width="7xl"
    class="space-y-6"
>
    <x-slot:actions>
        <flux:button href="{{ route('purchasing.invoices') }}" variant="subtle" icon="arrow-left">{{ __('Back to invoices') }}</flux:button>
    </x-slot:actions>

    <flux:callout variant="warning" icon="shield-check">
        {{ __('Import never approves an invoice and never posts stock, receipt, cost, or WAC. Invalid rows block draft creation.') }}
    </flux:callout>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('1. Upload and stage') }}</flux:heading>
        <flux:text>{{ __('Accepted: .xlsx or .csv, maximum 10 MB and 5,000 rows. Formula-like cells and macros are rejected.') }}</flux:text>
        <form wire:submit="stage" class="flex flex-wrap items-end gap-4">
            <div class="min-w-72 flex-1">
                <flux:label>{{ __('Invoice import file') }}</flux:label>
                <flux:input type="file" wire:model="importFile" accept=".xlsx,.csv" />
                @error('importFile') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            </div>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="stage"><span wire:loading.remove wire:target="stage">{{ __('Stage file') }}</span><span wire:loading wire:target="stage">{{ __('Staging...') }}</span></flux:button>
        </form>
        <flux:text size="sm">{{ __('Required headers: supplier_code, supplier_invoice_number, invoice_date, receiving_store_code, item_code or barcode, quantity, unit_cost.') }}</flux:text>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">{{ __('2. Staged batches') }}</flux:heading>
        <div class="mt-4 overflow-x-auto">
            <flux:table aria-label="{{ __('Purchase invoice import batches') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('File') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Rows') }}</flux:table.column>
                    <flux:table.column>{{ __('Action') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($batches as $batch)
                        <flux:table.row :key="$batch->id">
                            <flux:table.cell>{{ $batch->original_filename }}</flux:table.cell>
                            <flux:table.cell><x-status.badge :status="$batch->status" /></flux:table.cell>
                            <flux:table.cell>{{ $batch->valid_rows }} / {{ $batch->total_rows }} {{ __('valid') }}</flux:table.cell>
                            <flux:table.cell><flux:button size="sm" variant="subtle" wire:click="selectBatch({{ $batch->id }})">{{ __('Review') }}</flux:button></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4">{{ __('No invoice import batches yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        <div class="mt-4">{{ $batches->links() }}</div>
    </flux:card>

    @if ($selectedBatch)
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('3. Review') }}: {{ $selectedBatch->original_filename }}</flux:heading>
                    <flux:text>{{ __('Valid') }}: {{ $selectedBatch->valid_rows }} · {{ __('Rejected') }}: {{ $selectedBatch->invalid_rows }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($sourceAttachment?->status->isDeliverable())
                        <flux:button href="{{ route('purchasing.invoices.import.source', [$selectedBatch, $sourceAttachment]) }}" variant="subtle" icon="arrow-down-tray">{{ __('Download source') }}</flux:button>
                    @endif
                    @if (in_array($selectedBatch->status, ['staging', 'ready_for_review'], true))
                        <x-actions.button semantic="cancel" :label="__('Cancel batch')" variant="danger" wire:click="cancelBatch">{{ __('Cancel batch') }}</x-actions.button>
                    @endif
                    @can('purchase_invoices_supplier_returns.approve')
                        <flux:button variant="primary" wire:click="createDrafts" :disabled="$selectedBatch->status !== 'ready_for_review' || $selectedBatch->invalid_rows > 0">{{ __('Create draft invoices') }}</flux:button>
                    @endcan
                </div>
            </div>
            <div class="overflow-x-auto">
                <flux:table aria-label="{{ __('Purchase invoice import row review') }}">
                    <flux:table.columns>
                        <flux:table.column>#</flux:table.column>
                        <flux:table.column>{{ __('Supplier invoice') }}</flux:table.column>
                        <flux:table.column>{{ __('Product') }}</flux:table.column>
                        <flux:table.column>{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column>{{ __('Unit cost') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Errors') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($selectedBatch->rows as $row)
                            <flux:table.row :key="$row->id">
                                <flux:table.cell>{{ $row->row_number }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'supplier_reference') }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'product_id') }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'quantity') }}</flux:table.cell>
                                <flux:table.cell>{{ data_get($row->mapped_data, 'unit_cost') }}</flux:table.cell>
                                <flux:table.cell><x-status.badge :status="$row->status" /></flux:table.cell>
                                <flux:table.cell>{{ implode(' ', $row->errors ?? []) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
            @if ($selectedBatch->invalid_rows > 0)
                <flux:callout variant="danger">{{ __('Draft creation is blocked while rejected rows remain. Invalid rows never write to invoices.') }}</flux:callout>
            @endif
        </flux:card>
    @endif
</x-app.page>
