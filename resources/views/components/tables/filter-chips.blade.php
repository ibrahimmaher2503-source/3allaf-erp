@props(['filters' => [], 'resetUrl' => null])

@if (collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty())
    <div {{ $attributes->class('filter-chips') }} aria-label="{{ __('Active filters') }}">
        <span class="filter-chips__label">{{ __('Active filters') }}</span>
        @foreach ($filters as $label => $value)
            @if (filled($value))
                <span class="filter-chip"><strong>{{ $label }}:</strong> <span dir="auto">{{ $value }}</span></span>
            @endif
        @endforeach
        @if ($resetUrl)
            <a href="{{ $resetUrl }}" class="filter-chip filter-chip--reset" wire:navigate>
                <flux:icon.x-mark class="size-4" /> {{ __('Reset') }}
            </a>
        @endif
    </div>
@endif
