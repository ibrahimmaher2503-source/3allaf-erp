@props(['currentKey'])

@php
    $setupContext = app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
    $groupKeys = \App\Modules\Platform\Support\InitialSetupRouteMap::groups();
    $groupLabels = ['basics' => __('Basics'), 'settings' => __('Settings'), 'operations' => __('Operational readiness')];
    $authorizedSteps = collect($setupContext['steps'])->filter(fn (array $step) => $step['can_access'])->values();
    $currentIndex = $authorizedSteps->search(fn (array $step) => $step['key'] === $currentKey);
    $currentStep = $currentIndex !== false ? $authorizedSteps[$currentIndex] : null;
    $previousStep = $currentIndex !== false && $currentIndex > 0 ? $authorizedSteps[$currentIndex - 1] : null;
    $nextStep = $currentIndex !== false && $currentIndex < $authorizedSteps->count() - 1 ? $authorizedSteps[$currentIndex + 1] : null;
    $groupName = collect($groupKeys)->search(fn (array $keys) => in_array($currentKey, $keys, true));
    $groupSteps = $groupName !== false ? $authorizedSteps->whereIn('key', $groupKeys[$groupName])->values() : collect();
    $groupIndex = $groupSteps->search(fn (array $step) => $step['key'] === $currentKey);
    $continueStep = app(\App\Modules\Platform\Support\SetupContinuation::class)->next($authorizedSteps);
    $setupHref = static fn (array $step): string => $step['route'].(str_contains($step['route'], '?') ? '&' : '?').http_build_query(['setup' => 1, 'setup_step' => $step['key']]);
@endphp

@if ($currentStep)
    <aside {{ $attributes->class('setup-context rounded-2xl border border-primary/30 bg-surface/95 p-3 shadow-md backdrop-blur sm:p-4') }} aria-label="{{ __('Setup guidance') }}" data-setup-step="{{ $currentKey }}">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <a href="{{ route('initial-setup') }}" class="font-semibold text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" wire:navigate>{{ __('Setup Center') }}</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $groupLabels[$groupName] ?? __('Setup') }}</span>
                    <span aria-hidden="true">/</span>
                    <strong>{{ $currentStep['label'] }}</strong>
                    <flux:badge size="sm" color="{{ $currentStep['complete'] ? 'green' : ($currentStep['status'] === 'blocked' ? 'red' : 'amber') }}">{{ $currentStep['status_label'] }}</flux:badge>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-text-muted">
                    <span>{{ __('Step :current of :total', ['current' => $currentIndex + 1, 'total' => $authorizedSteps->count()]) }}</span>
                    @if ($groupIndex !== false)<span>{{ $groupLabels[$groupName] }} — {{ __('Step :current of :total', ['current' => $groupIndex + 1, 'total' => $groupSteps->count()]) }}</span>@endif
                    <span>{{ $currentStep['required'] ? __('Required') : __('Optional') }}</span>
                </div>
                @if (! $currentStep['complete'])<p class="max-w-3xl text-sm text-amber-800 dark:text-amber-200">{{ $currentStep['reason'] }}</p>@endif
                @if (in_array($currentKey, ['product-masters', 'prices'], true) && ! empty($currentStep['details']))
                    <x-setup.product-pricing-readiness :readiness="$currentStep['details']" :show-counts="$currentKey === 'prices'" compact class="max-w-4xl" />
                @endif
                <div class="h-1.5 max-w-2xl overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-label="{{ __('Setup completion progress') }}" aria-valuenow="{{ $setupContext['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"><span class="block h-full rounded-full bg-primary" style="width: {{ $setupContext['progress_percent'] }}%"></span></div>
            </div>
            <div class="flex flex-col gap-2">
                <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('Adjacent setup steps') }}">
                    @if ($previousStep)<flux:button :href="$setupHref($previousStep)" size="sm" variant="subtle" class="min-h-11" :icon="str_starts_with(app()->getLocale(), 'ar') ? 'arrow-right' : 'arrow-left'" aria-label="{{ __('Go to previous setup step: :step', ['step' => $previousStep['label']]) }}" wire:navigate>{{ __('Previous') }}</flux:button>@endif
                    @if ($nextStep)<flux:button :href="$setupHref($nextStep)" size="sm" variant="primary" class="min-h-11" :icon-trailing="str_starts_with(app()->getLocale(), 'ar') ? 'arrow-left' : 'arrow-right'" aria-label="{{ __('Go to next setup step: :step', ['step' => $nextStep['label']]) }}" wire:navigate>{{ __('Next') }}</flux:button>@endif
                    @if (! $currentStep['complete'])
                        <form method="POST" action="{{ route('setup.decisions.store') }}" class="inline-flex">@csrf<input type="hidden" name="step_key" value="{{ $currentKey }}"><input type="hidden" name="decision" value="{{ $currentStep['required'] ? 'deferred' : 'skipped' }}"><flux:button type="submit" size="sm" variant="subtle" class="min-h-11">{{ $currentStep['required'] ? __('Complete later') : __('Skip') }}</flux:button></form>
                    @endif
                </nav>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button :href="route('initial-setup')" size="sm" variant="subtle" class="min-h-11" wire:navigate>{{ __('Return to Setup Center') }}</flux:button>
                    @if ($continueStep && $continueStep['key'] !== $currentKey)<flux:button :href="$setupHref($continueStep)" size="sm" variant="subtle" class="min-h-11" wire:navigate>{{ __('Continue setup') }}</flux:button>@endif
                </div>
            </div>
        </div>
    </aside>
@endif
