@props([
    'semantic' => 'view',
    'label',
    'icon' => null,
    'href' => null,
    'size' => 'sm',
])

@php
    $icons = [
        'view' => 'eye', 'edit' => 'pencil-square', 'create' => 'plus', 'link' => 'link', 'assign' => 'user-plus',
        'approve' => 'check-circle', 'confirm' => 'check-circle', 'receive' => 'inbox-arrow-down', 'complete' => 'check-badge',
        'print' => 'printer', 'export' => 'arrow-down-tray', 'download' => 'arrow-down-tray', 'history' => 'clock',
        'pause' => 'pause', 'disable' => 'no-symbol', 'unavailable' => 'wrench-screwdriver',
        'reject' => 'x-circle', 'cancel' => 'x-mark', 'delete' => 'trash', 'archive' => 'archive-box-x-mark', 'restore' => 'arrow-path',
    ];
    $resolvedIcon = $icon ?: ($icons[$semantic] ?? 'ellipsis-horizontal');
@endphp

<flux:button
    :href="$href"
    :size="$size"
    :icon="$resolvedIcon"
    {{ $attributes->class(['app-action', 'app-action--'.$semantic]) }}
    title="{{ $label }}"
    aria-label="{{ $label }}"
>
    @if (! $slot->isEmpty())<span class="app-action__label">{{ $slot }}</span>@endif
</flux:button>
