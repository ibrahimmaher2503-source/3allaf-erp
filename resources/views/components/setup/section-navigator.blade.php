@props(['sections', 'current'])

<nav {{ $attributes->class('setup-section-nav') }} aria-label="{{ __('Settings Sections') }}" data-guide="settings-tabs">
    <ol class="setup-section-nav__list">
        @foreach ($sections as $key => $section)
            <li>
                <button type="button" @if($current === $key) aria-current="step" @endif wire:click="$set('activeTab', '{{ $key }}')" class="setup-section-nav__step {{ $current === $key ? 'is-active' : '' }}">
                    <span class="setup-section-nav__number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span>{{ $section['label'] }}</span>
                </button>
            </li>
        @endforeach
    </ol>
    <p class="mt-2 text-xs text-text-muted">{{ __('Section :current of :total', ['current' => array_search($current, array_keys($sections), true) + 1, 'total' => count($sections)]) }}</p>
</nav>
