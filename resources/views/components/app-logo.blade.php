@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="str_starts_with(app()->getLocale(), 'ar') ? 'نظام راجح' : 'Rajeh ERP'" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-accent-foreground" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="str_starts_with(app()->getLocale(), 'ar') ? 'نظام راجح' : 'Rajeh ERP'" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-accent-foreground" />
        </x-slot>
    </flux:brand>
@endif
