@php
    $setup ??= app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
    $setupSteps = collect($setup['steps'])->filter(static fn (array $step): bool => $step['can_access']);
    $nextStep = app(\App\Modules\Platform\Support\SetupContinuation::class)->next($setupSteps->values());
    $statusClasses = ['not_started' => ['border-zinc-300/70', 'bg-zinc-500/10'], 'incomplete' => ['border-amber-500/35', 'bg-amber-500/10'], 'requires_completion' => ['border-amber-500/35', 'bg-amber-500/10'], 'ready' => ['border-sky-500/35', 'bg-sky-500/10'], 'blocked' => ['border-rose-500/35', 'bg-rose-500/10'], 'deferred' => ['border-amber-500/35', 'bg-amber-500/10'], 'skipped' => ['border-slate-400/35', 'bg-slate-500/10'], 'completed' => ['border-emerald-500/30', 'bg-emerald-500/10']];
    $stepGroups = [
        'foundation' => ['label' => __('Basics'), 'description' => __('Set the company context and the places where work happens.'), 'keys' => \App\Modules\Platform\Support\InitialSetupRouteMap::groups()['basics']],
        'configuration' => ['label' => __('Settings'), 'description' => __('Save the financial, numbering, printer-profile, and template-assignment rules used by operations.'), 'keys' => \App\Modules\Platform\Support\InitialSetupRouteMap::groups()['settings']],
        'master-data' => ['label' => __('Operational readiness'), 'description' => __('Prepare supplier, catalog, product, customer, and opening inventory data in dependency order.'), 'keys' => \App\Modules\Platform\Support\InitialSetupRouteMap::groups()['operations']],
    ];
    $groupStats = collect($stepGroups)->mapWithKeys(static function (array $group, string $key) use ($setupSteps): array {
        $steps = $setupSteps->whereIn('key', $group['keys']);

        return [$key => ['complete' => $steps->where('complete', true)->count(), 'total' => $steps->count()]];
    })->all();
    $visibleStepCount = collect($stepGroups)->sum(fn (array $group): int => $setupSteps->whereIn('key', $group['keys'])->count());
    $setupDestination = static fn (array $step): string => $step['route'].(str_contains($step['route'], '?') ? '&' : '?').http_build_query(['setup' => 1, 'setup_step' => $step['key']]);
@endphp

<x-layouts::app :title="__('Initial setup')">
    <x-app.page :title="__('Initial setup progress')" :description="__('Finish setup and master data definitions before daily operations and transactions.')" max-width="7xl" class="space-y-6" data-guide="initial-setup-header">
        <x-slot:actions>
            <x-locale-switcher />
            <flux:button :href="route('dashboard')" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
        </x-slot:actions>
        <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]" data-guide="initial-setup-summary">
            <div class="rounded-2xl border border-border bg-surface p-4 shadow-card sm:p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div class="text-xs font-semibold uppercase tracking-[0.14em] text-primary">{{ __('Setup / master data') }}</div><flux:heading size="lg" class="mt-1">{{ __('Configuration status') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('Each status below comes from persisted data and its readiness rule.') }}</flux:text></div><div class="text-start sm:text-end"><div class="text-2xl font-semibold tracking-tight text-primary"><span dir="ltr">{{ $setup['completed_count'] }} / {{ $setup['required_count'] }}</span></div><div class="text-xs font-medium text-text-muted">{{ __('Required complete') }}</div></div></div><div class="mt-4 flex items-center gap-3"><progress class="h-2.5 min-w-0 flex-1 accent-primary" value="{{ $setup['progress_percent'] }}" max="100" aria-label="{{ __('Setup progress') }}">{{ $setup['progress_percent'] }}%</progress><span class="shrink-0 text-xs font-semibold text-primary" dir="ltr">{{ $setup['progress_percent'] }}%</span></div><div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-muted"><span>{{ __('Not started') }}</span><span>{{ __('Incomplete') }}</span><span>{{ __('Requires completion') }}</span><span>{{ __('Ready') }}</span><span>{{ __('Completed') }}</span></div></div>
            @if ($nextStep)<div class="flex flex-col justify-between gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 shadow-sm sm:p-5" data-guide="initial-setup-next-step"><div><div class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-800 dark:text-amber-200">{{ __('Continue setup') }}</div><flux:heading size="base" class="mt-2">{{ $nextStep['label'] }}</flux:heading><flux:text class="mt-1 text-sm leading-6">{{ $nextStep['reason'] }}</flux:text></div>@if ($nextStep['route'] && $nextStep['can_access'])<flux:button :href="$setupDestination($nextStep)" class="w-full" data-setup-route="{{ $nextStep['route_name'] }}" variant="primary" icon="arrow-left" wire:navigate>{{ __('Continue setup') }}</flux:button>@endif</div>@else<flux:callout variant="success" icon="check-circle" title="{{ __('All required setup steps are complete') }}">{{ __('Review the saved definitions before opening daily operations.') }}</flux:callout>@endif
        </section>
        <nav aria-label="{{ __('Initial setup steps') }}" class="rounded-2xl border border-border bg-surface/70 p-3 shadow-sm sm:p-4">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($stepGroups as $groupKey => $group)
                    @php($stats = $groupStats[$groupKey])
                    <a href="#setup-{{ $groupKey }}" class="group inline-flex min-w-[9rem] flex-1 items-center justify-between gap-3 rounded-xl border border-border bg-surface px-3 py-2.5 text-start transition hover:border-primary/50 hover:bg-primary/5 focus:outline-none focus:ring-2 focus:ring-primary/40">
                        <span class="min-w-0"><span class="block truncate text-sm font-semibold text-text">{{ $group['label'] }}</span><span class="mt-0.5 block text-xs text-text-muted"><span dir="ltr">{{ $stats['complete'] }}/{{ $stats['total'] }}</span> {{ __('Completed') }}</span></span>
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary" dir="ltr">{{ $stats['complete'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
        <section aria-label="{{ __('Initial setup steps') }}" data-guide="initial-setup-steps">
            @php($stepNumber = 0)
            @foreach ($stepGroups as $groupKey => $group)
                <div id="setup-{{ $groupKey }}" class="scroll-mt-24 mt-6 first:mt-0" data-setup-section="{{ $groupKey }}"><div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><flux:heading size="base">{{ $group['label'] }}</flux:heading><flux:text class="max-w-2xl text-sm">{{ $group['description'] }}</flux:text></div>@php($stats = $groupStats[$groupKey])<span class="inline-flex w-fit items-center gap-2 rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-text-muted"><span dir="ltr">{{ $stats['complete'] }}/{{ $stats['total'] }}</span> {{ __('Completed') }}</span></div><div class="grid gap-3 md:grid-cols-2">
                    @php($groupSteps = $setupSteps->whereIn('key', $group['keys'])->sortBy(static fn (array $step): int => array_search($step['key'], $group['keys'], true))->values())
                    @foreach ($groupSteps as $groupStepIndex => $step)
                        @php($stepNumber++)
                        @php($classes = $statusClasses[$step['status']] ?? $statusClasses['incomplete'])
                        @php($guidedStatus = ! $step['required'] ? __('Optional') : ($step['complete'] ? __('Complete') : ($step['records'] > 0 ? __('Requires completion') : __('Needs setup'))))
                        <article class="group flex h-full flex-col gap-3 rounded-2xl border {{ $classes[0] }} bg-surface p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md sm:p-5" data-guide="initial-setup-step-{{ $step['key'] }}" data-setup-destination="{{ $step['destination_key'] }}"><div class="flex items-start justify-between gap-3"><div class="flex min-w-0 items-start gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $classes[1] }} text-sm font-semibold" dir="ltr">{{ str_pad((string) $stepNumber, 2, '0', STR_PAD_LEFT) }}</span><div class="min-w-0"><flux:heading size="base">{{ $step['label'] }}</flux:heading><p class="mt-1 text-xs text-text-muted">{{ __('Step :current of :total', ['current' => $stepNumber, 'total' => $visibleStepCount]) }} · {{ $group['label'] }} — {{ __('step :current of :total', ['current' => $groupStepIndex + 1, 'total' => $groupSteps->count()]) }}</p></div></div><div class="flex flex-wrap items-center justify-end gap-2"><flux:badge size="sm" color="{{ $step['status'] === 'completed' ? 'green' : ($step['status'] === 'blocked' ? 'red' : ($step['status'] === 'ready' ? 'blue' : 'amber')) }}"><span @if(! in_array($step['status'], ['completed', 'blocked', 'ready'], true)) style="color: light-dark(#78350f, #fde68a)" @endif>{{ $step['status_label'] }}</span></flux:badge></div></div><p class="text-sm leading-6 text-text-muted">{{ $step['reason'] }}</p><div class="mt-auto border-t border-border pt-3"><div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between"><div class="flex flex-wrap gap-2">@if ($step['route'] && $step['can_access'])<flux:button :href="$setupDestination($step)" class="w-full sm:w-auto" data-setup-route="{{ $step['route_name'] }}" data-setup-destination="{{ $step['destination_key'] }}" variant="{{ $step['complete'] ? 'subtle' : 'primary' }}" size="sm" icon="arrow-left" wire:navigate>{{ $step['complete'] ? __('Review') : __('Continue setup') }}</flux:button>@endif @if (! $step['complete'])<form method="POST" action="{{ route('setup.decisions.store') }}" class="inline-flex">@csrf<input type="hidden" name="step_key" value="{{ $step['key'] }}"><input type="hidden" name="decision" value="{{ $step['required'] ? 'deferred' : 'skipped' }}"><flux:button type="submit" variant="subtle" size="sm">{{ $step['required'] ? __('Complete later') : __('Skip') }}</flux:button></form>@endif</div></div></div></article>
                    @endforeach
                </div></div>
            @endforeach
        </section>
    </x-app.page>
</x-layouts::app>
