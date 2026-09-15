@props(['visual'])

@php
    $labels = array_values($visual['labels'] ?? []);
    $series = array_values($visual['series'] ?? []);
    $type = $visual['type'] ?? 'bar';
    $unit = $visual['unit'] ?? 'number';
    $allValues = collect($series)->flatMap(fn ($item) => $item['data'] ?? [])->map(fn ($value) => (float) $value);
    $max = max(1, (float) $allValues->map(fn ($value) => abs($value))->max());
    $hasData = $allValues->contains(fn ($value) => abs($value) > 0.000001);
    $formatValue = function (float $value) use ($unit): string {
        return match ($unit) {
            'percent' => number_format($value, 1).'%',
            'integer', 'count' => number_format($value, 0),
            'points' => number_format($value, 0).' '.__('pts'),
            default => number_format($value, 2),
        };
    };
    $barClasses = ['bg-teal-500 dark:bg-teal-400', 'bg-sky-500 dark:bg-sky-400', 'bg-amber-500 dark:bg-amber-400', 'bg-violet-500 dark:bg-violet-400'];
    $dotClasses = ['bg-teal-500', 'bg-sky-500', 'bg-amber-500', 'bg-violet-500'];
    $strokeColors = ['oklch(0.58 0.12 194)', 'oklch(0.62 0.14 240)', 'oklch(0.72 0.13 75)', 'oklch(0.58 0.16 300)'];
    $chartId = 'report-visual-'.preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($visual['key'] ?? uniqid()));
@endphp

<article data-report-visual class="min-w-0 overflow-hidden rounded-2xl border border-border/80 bg-surface shadow-card">
    <header class="flex flex-col gap-3 border-b border-border/70 bg-surface-muted/35 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 id="{{ $chartId }}-title" class="text-base font-semibold text-text-primary">{{ $visual['title'] ?? __('Report visual') }}</h3>
            @if(filled($visual['description'] ?? null))<p id="{{ $chartId }}-description" class="mt-1 max-w-2xl text-sm leading-5 text-text-muted">{{ $visual['description'] }}</p>@endif
        </div>
        <div class="flex shrink-0 flex-wrap gap-3" aria-label="{{ __('Chart legend') }}">
            @foreach($series as $seriesIndex => $item)
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-text-muted"><span class="size-2.5 rounded-full {{ $dotClasses[$seriesIndex % count($dotClasses)] }}"></span>{{ $item['label'] ?? $item['name'] ?? __('Value') }}</span>
            @endforeach
        </div>
    </header>

    <div class="p-5 sm:p-6">
        <div data-report-chart role="img" aria-label="{{ $visual['title'] ?? __('Report visual') }}" aria-labelledby="{{ $chartId }}-title" @if(filled($visual['description'] ?? null)) aria-describedby="{{ $chartId }}-description" @endif>
            @if(!$hasData)
                <div class="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-surface-muted/25 px-6 text-center">
                    <span class="flex size-11 items-center justify-center rounded-full bg-primary-soft text-primary"><flux:icon name="chart-bar" class="size-5" /></span>
                    <p class="mt-3 text-sm font-semibold text-text-primary">{{ __('No chart activity in this range') }}</p>
                    <p class="mt-1 text-xs text-text-muted">{{ __('Adjust the filters or date range to compare activity.') }}</p>
                </div>
            @elseif($type === 'line')
                <div class="min-h-56" dir="ltr">
                    <svg class="h-56 w-full overflow-visible" viewBox="0 0 100 52" preserveAspectRatio="none" aria-hidden="true">
                        @foreach([8, 20, 32, 44] as $gridY)<line x1="0" y1="{{ $gridY }}" x2="100" y2="{{ $gridY }}" stroke="currentColor" class="text-zinc-200 dark:text-zinc-700" stroke-width="0.35" stroke-dasharray="2 2" />@endforeach
                        @foreach($series as $seriesIndex => $item)
                            @php
                                $data = array_values($item['data'] ?? []);
                                $count = max(1, count($data) - 1);
                                $points = collect($data)->map(fn ($value, $index) => (($index / $count) * 96 + 2).','.round(47 - ((float) $value / $max) * 39, 2))->implode(' ');
                            @endphp
                            <polyline points="{{ $points }}" fill="none" stroke="{{ $strokeColors[$seriesIndex % count($strokeColors)] }}" stroke-width="1.7" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach($data as $index => $value)<circle cx="{{ ($index / $count) * 96 + 2 }}" cy="{{ round(47 - ((float) $value / $max) * 39, 2) }}" r="1.2" fill="{{ $strokeColors[$seriesIndex % count($strokeColors)] }}"><title>{{ $labels[$index] ?? '' }}: {{ $formatValue((float) $value) }}</title></circle>@endforeach
                        @endforeach
                    </svg>
                    <div class="mt-2 flex justify-between gap-2 text-[11px] text-text-muted"><span>{{ $labels[0] ?? '' }}</span><span>{{ $labels[count($labels) - 1] ?? '' }}</span></div>
                </div>
            @elseif($type === 'donut')
                @php
                    $values = array_values($series[0]['data'] ?? []);
                    $total = max(0.000001, array_sum(array_map('abs', $values)));
                    $colors = ['oklch(0.58 0.12 194)', 'oklch(0.62 0.14 240)', 'oklch(0.72 0.13 75)', 'oklch(0.58 0.16 300)', 'oklch(0.63 0.18 25)'];
                    $cursor = 0;
                    $stops = [];
                    foreach($values as $index => $value) {
                        $start = $cursor;
                        $cursor += abs((float) $value) / $total * 100;
                        $stops[] = $colors[$index % count($colors)].' '.$start.'% '.$cursor.'%';
                    }
                @endphp
                <div class="grid min-h-56 items-center gap-6 sm:grid-cols-[12rem_1fr]">
                    <div class="relative mx-auto size-44 rounded-full" style="background: conic-gradient({{ implode(', ', $stops) }})">
                        <div class="absolute inset-7 flex items-center justify-center rounded-full bg-surface text-center shadow-inner"><div><span class="block text-xs text-text-muted">{{ __('Total') }}</span><strong class="mt-1 block text-lg tabular-nums text-text-primary" dir="ltr">{{ $formatValue(array_sum($values)) }}</strong></div></div>
                    </div>
                    <ol class="space-y-3">
                        @foreach($labels as $index => $label)
                            <li class="grid grid-cols-[auto_1fr_auto] items-center gap-3"><span class="size-3 rounded-full" style="background: {{ $colors[$index % count($colors)] }}"></span><span class="truncate text-sm text-text-muted">{{ $label }}</span><strong class="text-sm tabular-nums text-text-primary" dir="ltr">{{ $formatValue((float) ($values[$index] ?? 0)) }}</strong></li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="space-y-5">
                    @foreach($labels as $labelIndex => $label)
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-4"><span class="truncate text-sm font-medium text-text-primary">{{ $label }}</span><span class="text-xs tabular-nums text-text-muted" dir="ltr">{{ collect($series)->map(fn ($item) => $formatValue((float) ($item['data'][$labelIndex] ?? 0)))->implode(' · ') }}</span></div>
                            <div class="space-y-1.5">
                                @foreach($series as $seriesIndex => $item)
                                    @php($value = (float) ($item['data'][$labelIndex] ?? 0))
                                    <div class="h-2.5 overflow-hidden rounded-full bg-surface-muted" title="{{ $item['label'] ?? $item['name'] ?? __('Value') }}: {{ $formatValue($value) }}">
                                        <div class="h-full rounded-full {{ $barClasses[$seriesIndex % count($barClasses)] }} transition-[width] duration-200 motion-reduce:transition-none" style="width: {{ min(100, abs($value) / $max * 100) }}%"></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <details class="group mt-5 border-t border-border/70 pt-4">
            <summary class="cursor-pointer select-none text-xs font-semibold text-primary hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('View chart data') }}</summary>
            <div class="mt-3 overflow-x-auto">
                <table data-report-visual-table class="data-table min-w-[520px] w-full text-sm">
                    <thead><tr><th>{{ __('Category') }}</th>@foreach($series as $item)<th>{{ $item['label'] ?? $item['name'] ?? __('Value') }}</th>@endforeach</tr></thead>
                    <tbody>@foreach($labels as $labelIndex => $label)<tr><td>{{ $label }}</td>@foreach($series as $item)<td class="tabular-nums" dir="ltr">{{ $formatValue((float) ($item['data'][$labelIndex] ?? 0)) }}</td>@endforeach</tr>@endforeach</tbody>
                </table>
            </div>
        </details>
    </div>
</article>
