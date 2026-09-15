@php
    $currentKey = app(\App\Modules\Platform\Support\InitialSetupRouteMap::class)->resolve(request());
@endphp

@if ($currentKey && auth()->user()?->can('company_settings.edit'))
    <div class="sticky top-16 z-30 mx-auto mb-5 w-full max-w-7xl px-1">
        <x-setup.context :current-key="$currentKey" />
    </div>
@endif
