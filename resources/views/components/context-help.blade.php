@props([
    'title' => null,
    'label' => null,
    'sidebar' => false,
])

@php
    $title ??= __('Help');
    $label ??= __('Open contextual help');
@endphp

<span
    x-data="{ open: false, close() { this.open = false; this.$nextTick(() => this.$refs.trigger.focus()) } }"
    x-id="['context-help-title', 'context-help-panel']"
    class="context-help"
    x-on:keydown.escape.window="if (open) close()"
>
    <button
        x-ref="trigger"
        type="button"
        class="context-help__trigger {{ $sidebar ? 'context-help__trigger--sidebar' : '' }}"
        x-on:click="open = true; $nextTick(() => $refs.close.focus())"
        x-bind:aria-expanded="open.toString()"
        x-bind:aria-controls="$id('context-help-panel')"
        aria-haspopup="dialog"
        aria-label="{{ $label }}"
        title="{{ $label }}"
    >!</button>

    <template x-teleport="body">
        <div x-cloak x-show="open" class="context-help__layer" x-on:click.self="close()">
            <section
                x-show="open"
                x-transition.opacity.duration.150ms
                x-trap.inert.noscroll="open"
                x-bind:id="$id('context-help-panel')"
                role="dialog"
                aria-modal="true"
                x-bind:aria-labelledby="$id('context-help-title')"
                class="context-help__panel"
            >
                <header class="context-help__header">
                    <h2 x-bind:id="$id('context-help-title')" class="text-base font-bold text-text-primary">{{ $title }}</h2>
                    <button x-ref="close" type="button" class="context-help__close" x-on:click="close()" aria-label="{{ __('Close help') }}">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </header>
                <div class="context-help__content">{{ $slot }}</div>
            </section>
        </div>
    </template>
</span>
