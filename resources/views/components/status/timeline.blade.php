@props([
    'items' => [],
])

<div {{ $attributes->merge(['class' => 'flow-root w-full']) }}>
    @if (empty($items) && $slot->isEmpty())
        <x-state.empty :title="__('No timeline entries')" icon="clock" />
    @else
        <ul role="list" class="-mb-8">
            @if (!empty($items))
                @foreach ($items as $index => $item)
                    @php
                        $isLast = $loop->last;
                        $status = $item['status'] ?? 'draft';
                        $title = $item['title'] ?? '';
                        $actor = $item['actor'] ?? null;
                        $timestamp = $item['timestamp'] ?? null;
                        $description = $item['description'] ?? null;
                    @endphp
                    <li>
                        <div class="relative pb-8">
                            @if (!$isLast)
                                <span class="absolute top-4 start-4 -ms-px h-full w-0.5 bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex items-start space-x-3 rtl:space-x-reverse">
                                <div class="flex size-8 items-center justify-center rounded-full border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs shrink-0">
                                    <x-status.badge :status="$status" size="sm" class="!px-1" />
                                </div>

                                <div class="flex-1 min-w-0 pt-0.5 space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $title }}
                                        </p>

                                        @if ($timestamp)
                                            <span class="text-xs font-mono text-zinc-400 dark:text-zinc-500 shrink-0">
                                                {{ $timestamp }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($actor || $description)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 space-y-0.5">
                                            @if ($actor)
                                                <p><span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('By:') }}</span> {{ $actor }}</p>
                                            @endif
                                            @if ($description)
                                                <p class="text-zinc-600 dark:text-zinc-300 leading-normal">{{ $description }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </ul>
    @endif
</div>
