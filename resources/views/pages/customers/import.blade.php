<x-layouts::app :title="__('Customer Import')">
    <x-app.page :title="__('Customer Import')" :description="__('Upload, validate, review errors, and obtain independent approval before importing customers.')" max-width="7xl" class="space-y-6">
        <x-slot:actions><flux:button href="{{ route('customers.index') }}" variant="subtle" wire:navigate>{{ __('Back to customers') }}</flux:button></x-slot:actions>
        <flux:callout variant="info" title="{{ __('Reviewed customer import') }}">
            <p>{{ __('Valid rows are imported after independent approval. Rejected rows remain downloadable and no customer is overwritten or merged automatically.') }}</p>
            @can('customers.create')<flux:button href="{{ route('customers.import.template') }}" variant="subtle" icon="arrow-down-tray">{{ __('Download Excel template') }}</flux:button>@endcan
        </flux:callout>
        @can('customers.create')
            <flux:card>
                <flux:heading size="lg">{{ __('Upload and validate') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The Excel workbook validates stable group and residence codes, Arabic identity, consent fields, and duplicate normalized phones before writing any customer.') }}</flux:text>
                <form method="POST" action="{{ route('customers.import.stage') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <flux:input type="file" name="import_file" accept=".xlsx" :label="__('Excel file')" required />
                    <flux:select name="mode" :label="__('Import mode')"><flux:select.option value="create_only">{{ __('Create Only') }}</flux:select.option>@can('customers.edit')<flux:select.option value="update_existing">{{ __('Update Existing') }}</flux:select.option>@endcan</flux:select>
                    <flux:button type="submit" variant="primary">{{ __('Upload and validate') }}</flux:button>
                </form>
                @error('import_file')<flux:text class="mt-2 text-red-600">{{ $message }}</flux:text>@enderror
                @if(session('success'))<flux:callout class="mt-4" variant="success">{{ session('success') }}</flux:callout>@endif
            </flux:card>
        @endcan
        <flux:card>
            <flux:heading size="lg">{{ __('Staged batches and row errors') }}</flux:heading>
            <div class="mt-4 overflow-x-auto"><flux:table>
                <flux:table.columns><flux:table.column>{{ __('File') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column><flux:table.column>{{ __('Summary') }}</flux:table.column><flux:table.column>{{ __('Rejected') }}</flux:table.column><flux:table.column>{{ __('Approval') }}</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @forelse($batches as $batch)
                        <flux:table.row>
                            <flux:table.cell>{{ $batch->original_filename }}</flux:table.cell>
                            <flux:table.cell>{{ __($batch->status) }}</flux:table.cell>
                            <flux:table.cell>{{ __('Total') }}: {{ $batch->total_rows }} · {{ __('Added') }}: {{ $batch->added_rows }} · {{ __('Valid') }}: {{ $batch->valid_rows }} · {{ __('Duplicate existing') }}: {{ $batch->duplicate_existing_rows }} · {{ __('Duplicate in file') }}: {{ $batch->duplicate_file_rows }}</flux:table.cell>
                            <flux:table.cell>{{ $batch->invalid_rows }} @if($batch->invalid_rows>0)<flux:button size="sm" href="{{ route('customers.import.rejections',$batch) }}">{{ __('Download rejection report') }}</flux:button>@endif</flux:table.cell>
                            <flux:table.cell>@can('customers.import.approve') @if($batch->status==='ready_for_review' && $batch->valid_rows>0 && $batch->created_by !== auth()->id())<form method="POST" action="{{ route('customers.import.approve',$batch) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Approve valid rows')">{{ __('Approve valid rows') }}</x-actions.button></form>@endif @endcan</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="5">{{ __('No staged customer imports yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table></div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
