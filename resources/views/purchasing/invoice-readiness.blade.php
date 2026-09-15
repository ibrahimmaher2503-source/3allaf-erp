@php($locale = app()->getLocale())

<x-layouts::app :title="__('Purchase invoice readiness')">
    <x-app.page
        :title="__('Purchase invoice readiness')"
        :description="__('Review the decisions and blockers that must be resolved before enabling purchase invoice and receiving workflows.')"
        max-width="6xl"
        class="space-y-6 purchasing-screen"
    >
        <x-slot:actions>
            @can('company_settings.view')
                <flux:button href="{{ route('purchasing.invoices.settings') }}" variant="subtle" icon="adjustments-horizontal">
                    {{ __('Invoice settings') }}
                </flux:button>
            @endcan
            <flux:button href="{{ route('purchasing.orders') }}" variant="subtle" icon="arrow-left">
                {{ __('Back to purchase orders') }}
            </flux:button>
        </x-slot:actions>

        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading size="sm">{{ __('Pending prerequisites') }}</flux:heading>
            <flux:text>{{ __('These owner decisions and data prerequisites must be completed before purchase invoice and receiving workflows can be enabled.') }}</flux:text>
        </flux:callout>

        <section aria-labelledby="invoice-readiness-decisions">
            <div class="mb-4">
                <flux:heading id="invoice-readiness-decisions" size="lg">{{ __('Required decisions before operation') }}</flux:heading>
                <flux:text>{{ __('Decision groups and blockers show the work still needed before invoice and receiving operations are enabled.') }}</flux:text>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($decisionGroups as $group)
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="base">{{ $group['title'][str_starts_with($locale, 'ar') ? 'ar' : 'en'] }}</flux:heading>
                            <flux:badge color="zinc">{{ $group['items'] }}</flux:badge>
                        </div>
                        <flux:text size="sm" class="mt-2">{{ __('Decision items') }}</flux:text>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="invoice-readiness-blockers">
            <div class="mb-4">
                <flux:heading id="invoice-readiness-blockers" size="lg">{{ __('Current blockers') }}</flux:heading>
            </div>
            <div class="space-y-3">
                @foreach ($blockers as $blocker)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                        <div class="flex items-start gap-3">
                            <div>
                                <flux:heading size="sm">{{ $blocker['title'][str_starts_with($locale, 'ar') ? 'ar' : 'en'] }}</flux:heading>
                                <flux:text class="mt-1">{{ $blocker['detail'][str_starts_with($locale, 'ar') ? 'ar' : 'en'] }}</flux:text>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </x-app.page>
</x-layouts::app>
