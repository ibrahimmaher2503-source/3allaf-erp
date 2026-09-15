<?php

use App\Modules\Catalog\Actions\StageCatalogReferenceImportAction;
use App\Modules\Catalog\Models\CatalogReferenceImportBatch;
use Flux\Flux;
use Livewire\Attributes\{Title, Url};
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Catalog reference import')] class extends Component {
    use WithFileUploads;
    public mixed $importFile = null;
    public string $type = 'category';
    public string $mode = 'create_only';
    #[Url(as: 'batch')]
    public ?int $selectedBatchId = null;

    public function stage(StageCatalogReferenceImportAction $action): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,csv,ods|max:10240', 'type' => 'required|in:category,brand,age,character,colour,gender', 'mode' => 'required|in:create_only,update_existing']);
        $path = $this->importFile->store('imports/catalog-reference', 'local');
        $batch = $action->stage($path, $this->importFile->getClientOriginalName(), $this->type, $this->mode, auth()->id());
        $this->selectedBatchId = $batch->id; $this->importFile = null; Flux::toast(variant: 'success', text: __('File staged.'));
    }
    public function validateBatch(StageCatalogReferenceImportAction $action): void { $action->validate(CatalogReferenceImportBatch::query()->findOrFail($this->selectedBatchId)); Flux::toast(variant: 'success', text: __('Rows validated.')); }
    public function approve(StageCatalogReferenceImportAction $action): void { $action->approve(CatalogReferenceImportBatch::query()->findOrFail($this->selectedBatchId)); Flux::toast(variant: 'success', text: __('Import approved.')); }
    public function render() {
        $canReview = auth()->user()->can('products_categories_brands.approve');
        $visible = CatalogReferenceImportBatch::query()->where(function ($query) use ($canReview): void {
            $query->where('created_by', auth()->id());
            if ($canReview) $query->orWhere('status', 'ready_for_review');
        });
        $selectedBatch = $this->selectedBatchId ? (clone $visible)->with('rows')->find($this->selectedBatchId) : null;
        abort_if($this->selectedBatchId !== null && $selectedBatch === null, 404);

        return view('catalog.reference-import', ['batches' => $visible->latest()->paginate(10), 'selectedBatch' => $selectedBatch]);
    }
}; ?>

@php($referenceParent = match ($type) {
    'category' => route('catalog.categories'),
    'brand' => route('catalog.brands'),
    default => route('catalog.lookups'),
})
<x-app.page :title="__('Catalog reference import')" :description="__('Stage bilingual master data, review validation, then approve from a different account.')" max-width="7xl" class="space-y-6">
    <x-slot:actions><flux:button href="{{ $referenceParent }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to catalog master') }}</flux:button><flux:button href="{{ route('catalog.reference-import.template', $type) }}">{{ __('Download template') }}</flux:button></x-slot:actions>
    <flux:callout variant="info" title="{{ __('Safe staged import') }}">{{ __('Staging never changes master data. The requester cannot approve their own batch.') }}</flux:callout>
    <flux:card class="space-y-4"><flux:heading size="lg">{{ __('Upload and stage') }}</flux:heading><form wire:submit="stage" class="grid gap-4 md:grid-cols-4"><flux:select wire:model.live="type" :label="__('Master data type')"><flux:select.option value="category">{{ __('Categories') }}</flux:select.option><flux:select.option value="brand">{{ __('Brands') }}</flux:select.option><flux:select.option value="age">{{ __('Age labels') }}</flux:select.option><flux:select.option value="character">{{ __('Characters') }}</flux:select.option><flux:select.option value="colour">{{ __('Colours') }}</flux:select.option><flux:select.option value="gender">{{ __('Genders') }}</flux:select.option></flux:select><flux:select wire:model="mode" :label="__('Import mode')"><flux:select.option value="create_only">{{ __('Create Only') }}</flux:select.option><flux:select.option value="update_existing">{{ __('Update Existing') }}</flux:select.option></flux:select><flux:input type="file" wire:model="importFile" accept=".xlsx,.csv,.ods" required :label="__('Spreadsheet')" /><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="stage,importFile">{{ __('Stage file') }}</flux:button></form><div wire:loading.flex wire:target="stage,importFile" role="status" class="text-sm text-text-muted">{{ __('Uploading and staging…') }}</div></flux:card>
    <flux:card><flux:heading size="lg">{{ __('Staged batches') }}</flux:heading>@forelse($batches as $batch)<div class="mt-3 flex flex-wrap items-center gap-3 rounded border p-3"><span>{{ $batch->original_filename }}</span><flux:badge>{{ $batch->type }}</flux:badge><span>{{ __($batch->mode) }}</span><span>{{ __($batch->status) }}</span><span>{{ $batch->valid_rows }}/{{ $batch->total_rows }}</span><flux:button size="sm" wire:click="$set('selectedBatchId', {{ $batch->id }})">{{ __('Review') }}</flux:button></div>@empty<x-state.empty :title="__('No staged catalog imports yet')" :description="__('Download the template, then upload a CSV, XLSX, or ODS file for review.')" icon="arrow-up-tray" />@endforelse</flux:card>
    @if($selectedBatch)<flux:card class="space-y-3"><flux:heading size="lg">{{ __('Review validation') }}</flux:heading>@if($selectedBatch->invalid_rows > 0)<flux:button href="{{ route('catalog.reference-import.rejections', $selectedBatch) }}" icon="arrow-down-tray">{{ __('Download rejected rows') }}</flux:button>@endif@if($selectedBatch->status === 'staged' && $selectedBatch->created_by === auth()->id())<flux:button wire:click="validateBatch">{{ __('Validate rows') }}</flux:button>@endif @foreach($selectedBatch->rows as $row)<div class="rounded border p-2 text-sm">{{ __('Row') }} {{ $row->row_number }} · {{ $row->raw_data['code'] ?? '—' }} @if($row->errors)<span class="text-red-600">{{ implode('; ', $row->errors) }}</span>@else<span class="text-emerald-700">{{ __($row->status) }}</span>@endif</div>@endforeach @if($selectedBatch->status === 'ready_for_review' && $selectedBatch->valid_rows > 0 && $selectedBatch->created_by !== auth()->id())<flux:button wire:click="approve" variant="primary">{{ __('Approve and import') }}</flux:button>@endif</flux:card>@endif
</x-app.page>
