@props([
    'title' => null,
    'description' => null,
    'requestId' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center p-8 text-center rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/20 my-4 space-y-4']) }}>
    <div class="flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400">
        <flux:icon name="exclamation-triangle" class="size-6 shrink-0" />
    </div>

    <div class="space-y-1.5 max-w-md">
        <flux:heading level="3" size="lg" class="font-semibold text-red-950 dark:text-red-200">
            {{ $title ?? __('System Error') }}
        </flux:heading>

        <flux:text class="text-sm text-red-700 dark:text-red-300">
            {{ $description ?? __('A temporary error occurred while processing your request. Safe inputs have been preserved.') }}
        </flux:text>

        @php
            $cid = $requestId ?? \Illuminate\Support\Facades\Context::get('request_id') ?? request()->header('X-Request-ID');
        @endphp

        @if ($cid)
            <div class="pt-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-red-100/80 dark:bg-red-900/40 font-mono text-xs text-red-800 dark:text-red-300">
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
            <flux:button icon="arrow-path" size="sm" variant="subtle" onclick="window.location.reload()">
                {{ __('Retry Request') }}
            </flux:button>
        </div>
    @endif
</div>
