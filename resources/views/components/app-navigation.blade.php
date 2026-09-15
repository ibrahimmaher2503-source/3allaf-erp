@props(['groups' => null])
@php
    $navigation = app(\App\Modules\Platform\Support\ApplicationNavigation::class);
    $groups ??= $navigation->for(auth()->user(), app()->getLocale());
    $groups = $navigation->normalizeForRendering(is_iterable($groups) ? $groups : [], app()->getLocale());
    $isArabic = in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true);
@endphp

<nav {{ $attributes->class('app-navigation') }} data-sidebar-scroll aria-label="{{ $isArabic ? 'التنقل الرئيسي' : 'Primary navigation' }}">
    @foreach ($groups as $group)
        @if ($group['type'] === 'separator')
            <div class="my-1 border-t border-white/10" role="separator"></div>
        @elseif ($group['type'] === 'heading')
            <p class="px-3 py-1 text-xs font-bold uppercase tracking-wide text-slate-400">{{ $group['label'] }}</p>
        @else
            <a class="app-navigation__rail-link {{ $group['active'] ? 'is-active' : '' }}" href="{{ $group['url'] }}" title="{{ $group['label'] }}" aria-label="{{ $group['label'] }}" @if($group['active']) aria-current="page" @endif wire:navigate><flux:icon :name="$group['icon']" class="size-5" /></a>
            <details class="app-navigation__group {{ $group['active'] ? 'is-active' : '' }}" data-navigation-group="{{ $group['key'] }}" data-navigation-state="group:{{ $group['key'] }}" @if($group['active']) data-active-group open @endif>
                <summary @if($group['active']) aria-current="true" @endif><flux:icon :name="$group['icon']" class="size-5" /><span>{{ $group['label'] }}</span><flux:icon.chevron-down class="app-navigation__chevron size-4" /></summary>
                <div class="app-navigation__items">
                    @foreach ($group['items'] as $item)
                        @if ($item['type'] === 'separator')
                            <div class="mx-3 my-1 border-t border-white/10" role="separator"></div>
                        @elseif ($item['type'] === 'heading')
                            <p class="px-3 py-1 text-xs font-semibold text-slate-400">{{ $item['label'] }}</p>
                        @elseif ($item['subgroups'] !== [])
                            <details class="app-navigation__nested {{ ($item['active'] || $item['children_active']) ? 'is-active' : '' }}" data-navigation-state="item:{{ $item['key'] }}" @if($item['active'] || $item['children_active']) open @endif>
                                <summary><flux:icon :name="$item['icon']" class="size-5" /><span>{{ $item['label'] }}</span><flux:icon.chevron-down class="app-navigation__chevron size-4" /></summary>
                                <div class="app-navigation__nested-content">
                                    <a href="{{ $item['url'] }}" class="app-navigation__item app-navigation__overview {{ $item['active'] ? 'is-active' : '' }}" @if($item['active']) aria-current="page" @endif wire:navigate><span>{{ __('Basic Data Overview') }}</span></a>
                                    @foreach ($item['subgroups'] as $subcategory)
                                        @if ($subcategory['type'] === 'separator')
                                            <div class="mx-3 my-1 border-t border-white/10" role="separator"></div>
                                        @elseif ($subcategory['type'] === 'heading')
                                            <p class="px-3 py-1 text-xs font-semibold text-slate-400">{{ $subcategory['label'] }}</p>
                                        @else
                                            <details class="app-navigation__subcategory" data-setup-subcategory="{{ $subcategory['key'] }}" data-navigation-state="subcategory:{{ $subcategory['key'] }}" @if($subcategory['active']) open @endif>
                                                <summary><span>{{ $subcategory['label'] }}</span><flux:icon.chevron-down class="app-navigation__chevron size-3.5" /></summary>
                                                <div class="app-navigation__subcategory-items">
                                                    @foreach ($subcategory['items'] as $setupItem)
                                                        @if ($setupItem['type'] === 'separator')
                                                            <div class="mx-3 my-1 border-t border-white/10" role="separator"></div>
                                                        @elseif ($setupItem['type'] === 'heading')
                                                            <p class="px-3 py-1 text-xs font-semibold text-slate-400">{{ $setupItem['label'] }}</p>
                                                        @else
                                                            <a href="{{ $setupItem['url'] }}" class="app-navigation__item app-navigation__setup-item {{ $setupItem['active'] ? 'is-active' : '' }}" data-setup-step="{{ $setupItem['key'] }}" @if($setupItem['active']) aria-current="page" @endif wire:navigate><span>{{ $setupItem['label'] }}</span></a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @else
                            <a href="{{ $item['url'] }}" class="app-navigation__item {{ $item['active'] ? 'is-active' : '' }}" @if($item['active']) aria-current="page" @endif wire:navigate><flux:icon :name="$item['icon']" class="size-5" /><span>{{ $item['label'] }}</span></a>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</nav>
