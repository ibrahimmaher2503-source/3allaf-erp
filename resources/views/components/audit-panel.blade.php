@props([
    'createdByName' => null,
    'createdAt' => null,
    'updatedByName' => null,
    'updatedAt' => null,
    'approvedByName' => null,
    'approvedAt' => null,
    'requestId' => null,
    'version' => null,
    'status' => null,
])

<flux:card {{ $attributes->merge(['class' => 'space-y-4 bg-zinc-50/60 dark:bg-zinc-900/60 border-zinc-200 dark:border-zinc-800']) }}>
    <div class="flex items-center justify-between border-b border-zinc-200 pb-3 dark:border-zinc-800">
        <div class="flex items-center gap-2">
            <flux:icon name="shield-check" class="size-4 text-zinc-400 dark:text-zinc-500" />
            <flux:heading level="4" size="sm" class="font-semibold tracking-wide uppercase text-zinc-600 dark:text-zinc-400 text-xs">
                {{ __('Audit Context & Record Traceability') }}
            </flux:heading>
        </div>

        @if ($status)
            <x-status.badge :status="$status" size="sm" />
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
        <div>
            <span class="block text-zinc-400 dark:text-zinc-500 font-medium">{{ __('Created') }}</span>
            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $createdByName ?? __('System') }}</span>
            @if ($createdAt)
                <span class="block font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $createdAt }}</span>
            @endif
        </div>

        <div>
            <span class="block text-zinc-400 dark:text-zinc-500 font-medium">{{ __('Last Modified') }}</span>
            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $updatedByName ?? '-' }}</span>
            @if ($updatedAt)
                <span class="block font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $updatedAt }}</span>
            @endif
        </div>

        <div>
            <span class="block text-zinc-400 dark:text-zinc-500 font-medium">{{ __('Approval State') }}</span>
            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $approvedByName ?? __('N/A') }}</span>
            @if ($approvedAt)
                <span class="block font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $approvedAt }}</span>
            @endif
        </div>

        <div>
            <span class="block text-zinc-400 dark:text-zinc-500 font-medium">{{ __('Correlation & Version') }}</span>
            <div class="flex items-center gap-1.5 mt-0.5">
                @php
                    $cid = $requestId ?? \Illuminate\Support\Facades\Context::get('request_id') ?? request()->header('X-Request-ID') ?? 'REQ-LOCAL';
                @endphp
                <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-zinc-200/70 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                    {{ $cid }}
                </span>
                @if ($version)
                    <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-primary-soft text-primary">
                        v{{ $version }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    @if ($slot->isNotEmpty())
        <div class="pt-2 border-t border-zinc-200/60 dark:border-zinc-800/60 text-xs">
            {{ $slot }}
        </div>
    @endif
</flux:card>
