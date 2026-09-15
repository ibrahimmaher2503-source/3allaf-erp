<?php

use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('UI Pattern Showcase')] class extends Component {
    public string $activeSection = 'overview';
    public bool $isSimulatingLoading = false;
    public bool $showDialog = false;
    public string $demoInput = '';
    public string $demoSelect = 'active';

    public function mount(): void
    {
        Gate::authorize('view-ui-showcase');
    }

    public function toggleLoading(): void
    {
        $this->isSimulatingLoading = ! $this->isSimulatingLoading;

        Flux::toast(
            variant: 'info',
            text: $this->isSimulatingLoading ? __('Loading state enabled.') : __('Loading state cleared.')
        );
    }

    public function triggerToast(): void
    {
        Flux::toast(variant: 'success', text: __('The shared feedback pattern is working.'));
    }
}; ?>

<x-app.page
    :title="__('UI Pattern Showcase')"
    :description="__('The shared visual contract for current Platform screens.')"
    :badge="__('Platform')"
    max-width="7xl"
    class="space-y-5"
>
    <x-slot:actions>
        <flux:button icon="check-circle" size="sm" variant="primary" wire:click="triggerToast">
            {{ __('Show feedback') }}
        </flux:button>
        <flux:button icon="arrow-path" size="sm" variant="subtle" wire:click="toggleLoading">
            {{ $isSimulatingLoading ? __('Clear loading') : __('Show loading') }}
        </flux:button>
    </x-slot:actions>

    @if ($isSimulatingLoading)
        <x-state.loading :title="__('Refreshing the showcase')" :description="__('The page keeps its structure while an isolated interaction is in progress.')" />
    @endif

    <div class="flex gap-1 overflow-x-auto border-b border-border pb-2" role="tablist" aria-label="{{ __('Showcase sections') }}">
        @foreach (['overview' => ['squares-2x2', __('Overview')], 'forms' => ['adjustments-horizontal', __('Forms')], 'data' => ['table-cells', __('Data')], 'states' => ['exclamation-circle', __('States')], 'audit' => ['clock', __('Audit')], 'print' => ['printer', __('Print')]] as $section => [$icon, $label])
            <flux:button
                size="sm"
                :icon="$icon"
                :variant="$activeSection === $section ? 'primary' : 'subtle'"
                wire:click="$set('activeSection', '{{ $section }}')"
                role="tab"
                :id="'showcase-tab-'.$section"
                :aria-controls="'showcase-panel-'.$section"
                :aria-selected="$activeSection === $section ? 'true' : 'false'"
            >
                {{ $label }}
            </flux:button>
        @endforeach
    </div>

    @if ($activeSection === 'overview')
        <section id="showcase-panel-overview" role="tabpanel" tabindex="0" class="rounded-lg border border-primary/20 bg-primary-soft px-5 py-6 sm:px-6" aria-labelledby="showcase-tab-overview">
            <div class="max-w-3xl space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-primary">{{ __('Shared foundation') }}</p>
                <flux:heading id="showcase-overview-title" size="xl" class="text-text-primary">{{ __('Compact, operational, and readable') }}</flux:heading>
                <flux:text class="max-w-2xl text-text-muted">{{ __('Platform screens use a small set of stable surfaces, logical spacing, and server-driven interactions. Arabic RTL and English LTR use the same patterns.') }}</flux:text>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-cards.stat-card :label="__('Active users')" value="128" :description="__('Operational snapshot')" icon="users" />
            <x-cards.stat-card :label="__('Open reviews')" value="6" :description="__('Requires attention')" icon="clipboard-document-check" tone="warning" />
            <x-cards.stat-card :label="__('Healthy services')" value="4 / 4" :description="__('Local environment')" icon="heart" tone="success" />
            <x-cards.stat-card :label="__('Scoped locations')" value="12" :description="__('Branch and store access')" icon="map-pin" tone="info" />
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <x-cards.section-card :title="__('Dashboard composition')" :description="__('Use summary cards for high-signal operational information, not decoration.')">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md border border-border bg-surface-muted/70 p-4">
                        <p class="text-xs font-medium text-text-muted">{{ __('Priority queue') }}</p>
                        <p class="mt-1 text-lg font-semibold text-text-primary">{{ __('No urgent items') }}</p>
                        <x-status.badge status="healthy" class="mt-3" />
                    </div>
                    <div class="rounded-md border border-border bg-surface-muted/70 p-4">
                        <p class="text-xs font-medium text-text-muted">{{ __('Recent activity') }}</p>
                        <p class="mt-1 text-lg font-semibold text-text-primary">{{ __('Settings reviewed') }}</p>
                        <p class="mt-2 text-sm text-text-muted">{{ __('Append-only audit records remain available to authorized users.') }}</p>
                    </div>
                </div>
            </x-cards.section-card>

            <x-cards.section-card :title="__('Typography and status')" :description="__('Hierarchy should make scanning predictable.')">
                <div class="space-y-3">
                    <div><p class="text-xs font-medium text-text-muted">{{ __('Section heading') }}</p><p class="text-lg font-semibold text-text-primary">{{ __('Operational details') }}</p></div>
                    <div class="flex flex-wrap gap-2"><x-status.badge status="active" /><x-status.badge status="pending" /><x-status.badge status="disabled" /></div>
                </div>
            </x-cards.section-card>
        </div>
    @endif

    @if ($activeSection === 'forms')
        <div class="grid gap-4 xl:grid-cols-[1.35fr_0.65fr]">
            <x-forms.form-section :title="__('Grouped form fields')" :description="__('Use Flux controls, concise help text, and validation close to the field.')">
                <flux:field>
                    <flux:label>{{ __('Display name') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="demoInput" :placeholder="__('Enter a sample value')" />
                    <flux:description>{{ __('Input is retained during a server update.') }}</flux:description>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Current status') }}</flux:label>
                    <flux:select wire:model.live="demoSelect">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </flux:select>
                    <flux:description>{{ __('Use a select for a finite approved value set.') }}</flux:description>
                </flux:field>
                <div class="sm:col-span-2 flex flex-wrap items-center gap-4 border-t border-border pt-4">
                    <flux:checkbox :label="__('Notify the assigned owner')" />
                    <flux:switch :label="__('Enable local setting')" />
                </div>
                <div class="sm:col-span-2 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" icon="check">{{ __('Save changes') }}</flux:button>
                </div>
            </x-forms.form-section>

            <x-cards.section-card :title="__('Feedback and dialog')" :description="__('Use feedback sparingly and keep commands explicit.')">
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" icon="check-circle" variant="primary" wire:click="triggerToast">{{ __('Success feedback') }}</flux:button>
                    <flux:button size="sm" icon="window" variant="subtle" wire:click="$set('showDialog', true)">{{ __('Open dialog') }}</flux:button>
                </div>
                <flux:callout variant="info" icon="information-circle" title="{{ __('Inline guidance') }}">
                    {{ __('Reserve callouts for context that helps a user act safely.') }}
                </flux:callout>
            </x-cards.section-card>
        </div>
    @endif

    @if ($activeSection === 'data')
        <x-tables.data-panel :title="__('Server-driven data panel')" :description="__('Search and actions stay above compact, horizontally safe tables.')">
            <x-slot:toolbar>
                <x-tables.filter-bar>
                    <flux:input icon="magnifying-glass" :label="__('Search records')" :placeholder="__('Name, code, or email')" />
                    <x-slot:actions>
                        <flux:button size="sm" icon="funnel" variant="subtle">{{ __('Filters') }}</flux:button>
                        <flux:button size="sm" icon="plus" variant="primary">{{ __('New record') }}</flux:button>
                    </x-slot:actions>
                </x-tables.filter-bar>
            </x-slot:toolbar>

            <table class="data-table w-full min-w-[42rem]">
                <caption class="sr-only">{{ __('Data table pattern') }}</caption>
                <thead class="bg-surface-muted/70 text-xs text-text-muted"><tr><th class="px-3 py-2.5 text-start font-semibold">{{ __('Reference') }}</th><th class="px-3 py-2.5 text-start font-semibold">{{ __('Name') }}</th><th class="px-3 py-2.5 text-start font-semibold">{{ __('Scope') }}</th><th class="px-3 py-2.5 text-start font-semibold">{{ __('Status') }}</th><th class="px-3 py-2.5 text-end font-semibold">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @foreach ([['PLT-104', __('Central branch'), __('Branch scope'), 'active'], ['PLT-105', __('Store drawer'), __('Store scope'), 'pending'], ['PLT-106', __('Review queue'), __('Global scope'), 'inactive']] as [$reference, $name, $scope, $status])
                        <tr class="data-table-row"><td class="px-3 py-3 font-mono text-xs text-text-muted">{{ $reference }}</td><td class="px-3 py-3 font-medium text-text-primary">{{ $name }}</td><td class="px-3 py-3 text-text-muted">{{ $scope }}</td><td class="px-3 py-3"><x-status.badge :status="$status" /></td><td class="px-3 py-3 text-end"><x-actions.button semantic="view" :label="__('View record')" size="xs" /></td></tr>
                    @endforeach
                </tbody>
            </table>

            <x-slot:footer><div class="flex items-center justify-between gap-3 text-sm text-text-muted"><span>{{ __('Showing 1 to 3 of 3 records') }}</span><flux:button size="xs" variant="subtle" disabled>{{ __('Previous') }}</flux:button></div></x-slot:footer>
        </x-tables.data-panel>
    @endif

    @if ($activeSection === 'states')
        <div class="grid gap-4 md:grid-cols-2">
            <section class="space-y-2"><flux:heading size="sm">{{ __('Empty') }}</flux:heading><x-state.empty :title="__('No matching locations')" :description="__('Change the filter or create a permitted record.')" /></section>
            <section class="space-y-2"><flux:heading size="sm">{{ __('Loading') }}</flux:heading><x-state.loading :title="__('Loading scoped records')" :description="__('The table remains available when the request completes.')" /></section>
            <section class="space-y-2"><flux:heading size="sm">{{ __('Recoverable error') }}</flux:heading><x-state.error :title="__('Request could not be completed')" :description="__('Review the request and retry without losing safe input.')" requestId="REQ-DEMO-503" /></section>
            <section class="space-y-2"><flux:heading size="sm">{{ __('Permission denied') }}</flux:heading><x-state.denied :title="__('Authorization required')" :description="__('The server does not expose the restricted record or action.')" requestId="REQ-DEMO-403" /></section>
        </div>
    @endif

    @if ($activeSection === 'audit')
        <div class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <x-cards.section-card :title="__('Status timeline')" :description="__('A compact chronological pattern for approved state changes.')">
                <x-status.timeline :items="[
                    ['title' => __('Created'), 'status' => 'draft', 'timestamp' => '2026-08-03 10:15', 'actor' => __('Platform administrator'), 'description' => __('Initial record saved.')],
                    ['title' => __('Review requested'), 'status' => 'pending', 'timestamp' => '2026-08-03 10:18', 'actor' => __('Branch manager'), 'description' => __('Awaiting an authorized decision.')],
                    ['title' => __('Approved'), 'status' => 'approved', 'timestamp' => '2026-08-03 10:25', 'actor' => __('System administrator'), 'description' => __('The final state is protected.')],
                ]" />
            </x-cards.section-card>
            <section class="space-y-2">
                <div><flux:heading size="lg">{{ __('Audit panel') }}</flux:heading><flux:text size="sm" class="text-text-muted">{{ __('Surface protected before and after values only to authorized users.') }}</flux:text></div>
                <x-audit-panel
                    :created-by-name="__('Platform administrator')"
                    created-at="2026-08-03 09:48"
                    :updated-by-name="__('System administrator')"
                    updated-at="2026-08-03 10:25"
                    :approved-by-name="__('System administrator')"
                    approved-at="2026-08-03 10:25"
                    request-id="REQ-DEMO-112"
                    version="2"
                    status="approved"
                />
            </section>
        </div>
    @endif

    @if ($activeSection === 'print')
        <x-cards.section-card :title="__('Print and export structure')" :description="__('Print surfaces omit application chrome and never mutate their source document.')">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ([['printer', __('Thermal'), '80mm'], ['document-text', __('A4 document'), '210 x 297mm'], ['qr-code', __('Label'), '50 x 30mm']] as [$icon, $label, $format])
                    <div class="space-y-3 rounded-md border border-border bg-surface-muted/60 p-4 text-center">
                        <span class="mx-auto flex size-9 items-center justify-center rounded-md bg-surface text-primary"><flux:icon :name="$icon" class="size-4" /></span>
                        <p class="font-medium text-text-primary">{{ $label }}</p><p class="text-sm text-text-muted">{{ $format }}</p>
                        <div class="h-20 rounded-sm border border-dashed border-border bg-surface"></div>
                    </div>
                @endforeach
            </div>
        </x-cards.section-card>
    @endif

    <flux:modal wire:model="showDialog" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Confirmation pattern') }}</flux:heading>
            <flux:text>{{ __('Use a dialog only when a consequential action needs an explicit confirmation.') }}</flux:text>
            <div class="flex justify-end gap-2"><flux:button variant="subtle" wire:click="$set('showDialog', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" wire:click="$set('showDialog', false)">{{ __('Confirm') }}</flux:button></div>
        </div>
    </flux:modal>
</x-app.page>
