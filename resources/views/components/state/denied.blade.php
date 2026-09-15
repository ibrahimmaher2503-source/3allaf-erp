@props([
    'title' => null,
    'description' => null,
    'requestId' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center p-8 text-center rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50/40 dark:bg-amber-950/20 my-4 space-y-4']) }}>
    <div class="flex items-center justify-center size-12 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400">
        <flux:icon name="lock-closed" class="size-6 shrink-0" />
    </div>

    <div class="space-y-1.5 max-w-md">
        <flux:heading level="3" size="lg" class="font-semibold text-amber-950 dark:text-amber-200">
            {{ $title ?? __('Access Denied') }}
        </flux:heading>

        <flux:text class="text-sm text-amber-800 dark:text-amber-300">
            {{ $description ?? __('You do not have authorization to view this resource or perform this action.') }}
        </flux:text>

        @php
            $cid = $requestId ?? \Illuminate\Support\Facades\Context::get('request_id') ?? request()->header('X-Request-ID');
        @endphp

        @if ($cid)
            <div class="pt-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-100/80 dark:bg-amber-900/40 font-mono text-xs text-amber-800 dark:text-amber-300">
                    <span class="opacity-65 select-none">{{ __('ID:') }}</span>
                    <span>{{ $cid }}</span>
                </span>
            </div>
        @endif
    </div>

    @if (isset($action))
        <div class="pt-2">
            {{ $action }}
        </div>
    @elseif ($slot->isNotEmpty())
        <div class="pt-2">
            {{ $slot }}
        </div>
    @else
        <div class="pt-2">
            <flux:button icon="home" size="sm" variant="subtle" :href="route('dashboard')" wire:navigate>
                {{ __('Return to Dashboard') }}
            </flux:button>
        </div>
    @endif
</div>
