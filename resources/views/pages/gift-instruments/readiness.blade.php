<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="$boundary.' '.__('No card number, Gift Receipt reference, balance, price, holder data, payment, or print artifact is loaded in this readiness slice.')"
        :cards="$items"
        :empty="$emptyTitle.' — '.$emptyBody"
        :guide-prefix="$kind.'-readiness'"
        :card-status-label="__('Pending')"
    >
        <x-slot:actions>
            @if ($kind === 'gift-receipts')
                <flux:button href="{{ route('gift.cards') }}" variant="subtle">{{ __('Open Gift Cards') }}</flux:button>
            @else
                <flux:button href="{{ route('gift.receipts') }}" variant="subtle">{{ __('Open Gift Receipts') }}</flux:button>
            @endif
            <flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="subtle">{{ __('Pending value settings') }}</flux:button>
        </x-slot:actions>
    </x-state.capability-boundary>
</x-layouts::app>
