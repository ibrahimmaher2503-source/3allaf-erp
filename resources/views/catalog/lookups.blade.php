<x-layouts.app :title="__('Catalog lookup masters')">
    <div class="mx-auto max-w-6xl space-y-6 p-6" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
        <div class="flex items-center justify-between gap-4">
            <div><flux:heading size="xl">{{ __('Catalog lookup masters') }}</flux:heading><flux:text>{{ __('Maintain bilingual age labels, characters, colours, and genders used by product cards.') }}</flux:text></div>
            <div class="flex gap-2"><flux:button href="{{ route('catalog.reference-import') }}" variant="subtle">{{ __('Excel import') }}</flux:button><flux:button href="{{ route('catalog.products.create') }}" variant="primary">{{ __('Create product') }}</flux:button></div>
        </div>
        <flux:card class="p-6"><flux:text>{{ __('No lookup values are available yet. Create them through the approved catalog setup workflow.') }}</flux:text></flux:card>
    </div>
</x-layouts.app>
