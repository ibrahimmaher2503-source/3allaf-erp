@props(['compact' => false])
@php
    $localeNames = config('app.locale_names', ['ar' => 'العربية', 'en' => 'English', 'ar-EG' => 'العربية المصرية']);
    $currentLocale = app()->getLocale();
@endphp

<form method="POST" action="{{ route('locale.switch') }}" {{ $attributes->class('inline-flex') }}>
    @csrf
    <label class="sr-only" for="locale-switcher-{{ $compact ? 'compact' : 'full' }}-{{ md5((string) ($attributes->get('class') ?? '')) }}">{{ __('Language') }}</label>
    <select
        id="locale-switcher-{{ $compact ? 'compact' : 'full' }}-{{ md5((string) ($attributes->get('class') ?? '')) }}"
        name="locale"
        aria-label="{{ __('Language') }}"
        class="min-h-10 rounded-lg border border-border bg-surface px-2 text-sm font-semibold text-text-primary shadow-sm focus:border-primary focus:ring-primary {{ $compact ? 'max-w-36' : 'min-w-44' }}"
        onchange="this.form.submit()"
    >
        @foreach ($localeNames as $locale => $name)
            <option value="{{ $locale }}" @selected($currentLocale === $locale)>{{ $name }}</option>
        @endforeach
    </select>
</form>
