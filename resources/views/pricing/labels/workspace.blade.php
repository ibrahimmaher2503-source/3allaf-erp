<flux:card
    class="space-y-5"
    data-label-workspace
    x-data="labelWorkspace(@js($initial), @js(route('pricing.labels.search')))"
    x-init="init()"
>
    <div>
        <flux:heading size="lg">{{ __('Find and select products') }}</flux:heading>
        <flux:subheading>
            {{ __('Nothing is loaded until you search. Selected products and print settings remain while you continue searching.') }}
        </flux:subheading>
    </div>

    @include('pricing.labels.search')
    @include('pricing.labels.selected-products')
</flux:card>
