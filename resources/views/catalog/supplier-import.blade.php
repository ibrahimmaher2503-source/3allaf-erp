<?php

use App\Modules\Catalog\Actions\StageSupplierImportAction;
use App\Modules\Catalog\Models\SupplierImportBatch;
use App\Modules\Platform\Models\Store;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Supplier Import')] class extends Component
{
    use WithFileUploads;

    public mixed $importFile = null;
    public string $mode = 'create_only';

    #[Url(as: 'batch')]
    public ?int $selectedBatchId = null;

    private function activeCompanyId(): int
    {
        $companyId = Store::query()->visibleTo(auth()->user())->where('status', 'active')->orderBy('id')->value('company_id');
        abort_unless($companyId !== null, 403);

        return (int) $companyId;
    }

    public function stage(StageSupplierImportAction $action): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx|max:10240', 'mode' => 'in:create_only,update_existing']);
        $name = $this->importFile->getClientOriginalName();
        $batch = $action->stage($this->importFile->store('imports/suppliers', 'local'), $name, $this->mode, auth()->id());
        $this->selectedBatchId = $batch->id;
        $this->importFile = null;
        Flux::toast(variant: 'success', text: __('File staged.'));
    }

    public function map(StageSupplierImportAction $action): void
    {
        $batch = SupplierImportBatch::where('company_id', $this->activeCompanyId())->where('created_by', auth()->id())->findOrFail($this->selectedBatchId);
        $action->applyMapping($batch, array_combine($batch->headers, $batch->headers));
    }

    public function approve(StageSupplierImportAction $action): void
    {
        $action->approve(SupplierImportBatch::where('company_id', $this->activeCompanyId())->findOrFail($this->selectedBatchId));
    }

    public function render()
    {
        $canReview = auth()->user()->can('suppliers.edit');
        $visible = SupplierImportBatch::query()->where('company_id', $this->activeCompanyId())->where(function ($query) use ($canReview) {
            $query->where('created_by', auth()->id());
            if ($canReview) {
                $query->orWhere('status', 'ready_for_review');
            }
        });
        $selectedBatch = $this->selectedBatchId ? (clone $visible)->with('rows')->find($this->selectedBatchId) : null;
        abort_if($this->selectedBatchId !== null && $selectedBatch === null, 404);

        return view('catalog.supplier-import', ['batches' => $visible->latest()->paginate(10), 'selectedBatch' => $selectedBatch]);
    }
};
?>
<x-app.page :title="__('Supplier Import')" :description="__('Upload, validate, preview, and independently approve supplier master data.')" max-width="7xl" class="space-y-6">
    <x-slot:actions><flux:button href="{{ route('catalog.suppliers') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to suppliers') }}</flux:button><flux:button href="{{ route('catalog.suppliers.import.template') }}">{{ __('Download template') }}</flux:button></x-slot:actions>
    <flux:callout variant="info" title="{{ __('Safe staged import') }}">{{ __('Valid rows may be approved while rejected rows remain downloadable. Existing suppliers are never overwritten in Create Only mode.') }}</flux:callout>
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Upload and stage') }}</flux:heading>
        <form wire:submit="stage" class="grid gap-4 md:grid-cols-3">
            <flux:input type="file" wire:model="importFile" accept=".xlsx" :label="__('Excel file')" required />
            <flux:select wire:model="mode" :label="__('Import mode')"><flux:select.option value="create_only">{{ __('Create Only') }}</flux:select.option><flux:select.option value="update_existing">{{ __('Update Existing') }}</flux:select.option></flux:select>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="stage"><span wire:loading.remove wire:target="stage">{{ __('Stage file') }}</span><span wire:loading wire:target="stage">{{ __('Uploading...') }}</span></flux:button>
        </form>
    </flux:card>
    <flux:card>
        <flux:heading size="lg">{{ __('Staged batches and validation preview') }}</flux:heading>
        @forelse($batches as $batch)<div class="mt-3 flex flex-wrap items-center gap-3 rounded border p-3"><span>{{ $batch->original_filename }}</span><span>{{ __($batch->mode) }}</span><span>{{ __($batch->status) }}</span><span>{{ __('Valid') }}: {{ $batch->valid_rows }} / {{ $batch->total_rows }}</span><span>{{ __('Rejected') }}: {{ $batch->invalid_rows }}</span><flux:button size="sm" wire:click="$set('selectedBatchId',{{ $batch->id }})">{{ __('Review') }}</flux:button></div>@empty<x-state.empty :title="__('No staged supplier imports yet')" :description="__('Upload the downloaded Excel template to preview validation results.')" icon="arrow-up-tray" />@endforelse
    </flux:card>
    @if($selectedBatch)
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Row errors and approval') }}</flux:heading>
            @if($selectedBatch->status==='mapping_required' && $selectedBatch->created_by===auth()->id())<flux:button wire:click="map">{{ __('Validate rows') }}</flux:button>@endif
            @foreach($selectedBatch->rows as $row)<div class="rounded border p-2 text-sm">{{ __('Row') }} {{ $row->row_number }} · {{ $row->raw_data['code'] ?? '—' }} @if($row->errors)<span class="text-red-600">{{ implode('; ', $row->errors) }}</span>@else<span class="text-emerald-700">{{ __($row->status) }}</span>@endif</div>@endforeach
            @if($selectedBatch->invalid_rows>0)<flux:button href="{{ route('catalog.suppliers.import.rejections',$selectedBatch) }}">{{ __('Download rejection report') }}</flux:button>@endif
            @if($selectedBatch->status==='ready_for_review' && $selectedBatch->valid_rows>0 && $selectedBatch->created_by!==auth()->id())<flux:button wire:click="approve" variant="primary" wire:loading.attr="disabled" wire:target="approve"><span wire:loading.remove wire:target="approve">{{ __('Approve valid rows') }}</span><span wire:loading wire:target="approve">{{ __('Approving...') }}</span></flux:button>@endif
        </flux:card>
    @endif
</x-app.page>
