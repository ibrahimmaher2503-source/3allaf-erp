<x-layouts::app :title="__('Purchase invoice settings')">
    <x-app.page
        :title="__('Purchase invoice settings')"
        :description="__('Review purchase invoice rules when configuration is available.')"
        max-width="6xl"
        class="purchasing-screen"
    >
        <x-slot:actions>
            <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="arrow-left">
                {{ __('Back to purchase invoices') }}
            </flux:button>
        </x-slot:actions>

        <x-state.capability-boundary
            :title="__('Purchase invoice settings')"
            :description="__('Purchase invoice settings are not available in this workspace yet.')"
            :boundary="__('Configured invoice rules will appear here when available.')"
            :empty-title="__('No invoice settings are available yet.')"
            :empty="__('Return to purchase invoices when configuration is available.')"
            guide-prefix="purchase-invoice-settings"
        />
    </x-app.page>
</x-layouts::app>
