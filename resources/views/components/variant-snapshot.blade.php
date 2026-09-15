@props(['snapshot' => null, 'class' => ''])

@if (is_array($snapshot) && count($snapshot) > 0)
    <span {{ $attributes->class(['text-xs text-zinc-500 dark:text-zinc-400', $class]) }}>
        @foreach ($snapshot as $choice)
            @php
                $group = str_starts_with(app()->getLocale(), 'ar') ? ($choice['group_ar'] ?? $choice['group_en'] ?? '') : ($choice['group_en'] ?? $choice['group_ar'] ?? '');
                $value = str_starts_with(app()->getLocale(), 'ar') ? ($choice['value_ar'] ?? $choice['value_en'] ?? '') : ($choice['value_en'] ?? $choice['value_ar'] ?? '');
            @endphp
            @if (! $loop->first) <span aria-hidden="true">·</span> @endif
            <span>{{ $group }}: {{ $value }}</span>
        @endforeach
    </span>
@endif
