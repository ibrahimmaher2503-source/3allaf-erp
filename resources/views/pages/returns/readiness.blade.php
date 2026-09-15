<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('No source return, refund, exchange, restock, payment reversal, customer, wallet, or Gift Card record is created from this screen.').' '.__('Invoice/Gift Receipt validation, tenant/store scope, quantity, condition, approval, and settlement remain PENDING.')"
        :cards="$items"
        :empty="__('This empty state is intentional. Configure and approve the required return policy and source contracts before any return, refund, exchange, or stock action is introduced.')"
        guide-prefix="returns-readiness"
        :card-status-label="__('Pending')"
    >
        <x-slot:actions>
            <flux:button :href="route('admin.settings.customer-loyalty')" variant="primary">{{ __('Open pending policy settings') }}</flux:button>
            <flux:button :href="route('sales.index')" variant="subtle">{{ __('Review sales sources') }}</flux:button>
        </x-slot:actions>
    </x-state.capability-boundary>
</x-layouts::app>
