<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}" class="overflow-x-hidden">
<head>@include('partials.head')</head>
<body class="app-layout min-h-screen overflow-x-hidden">
<style>
    @media (max-width: 1023px) {
        body.app-layout { display: block !important; grid-template-columns: none !important; }
        .app-layout > .app-sidebar { position: static !important; min-height: 100vh !important; height: auto !important; overflow: visible !important; align-self: stretch; }
        .app-layout > .app-layout__content { display: flex !important; flex-direction: column !important; grid-area: auto !important; width: 100% !important; min-width: 0 !important; margin: 0 !important; }
    }
    .app-sidebar [data-flux-sidebar-item], .app-sidebar [data-sidebar-expandable] > button { color: #d2dde7 !important; }
    .app-company-context strong,
    .app-brand-block [data-flux-sidebar-brand], .app-brand-block [data-flux-sidebar-brand] * { color: #f2f7fb !important; }
    .app-company-context small, .sidebar-status { color: #c7d4df; }
    .app-navigation__group summary { color: #d5e0e9; }
    .app-navigation__chevron { color: #aebfce; }
    .app-navigation__item { color: #cbd8e3; }
    .app-navigation__item:hover, .app-navigation__group summary:hover, .app-navigation__rail-link:hover { background: rgb(255 255 255 / .08); color: #f5f9fc; }
    .app-navigation__item.is-active { background: color-mix(in srgb, var(--color-primary) 24%, transparent) !important; color: #f7fffe; box-shadow: inset 3px 0 var(--color-primary); }
    [dir=rtl] .app-navigation__item.is-active { box-shadow: inset -3px 0 var(--color-primary); }
    .app-navigation__group.is-active > summary { background: color-mix(in srgb, var(--color-primary) 15%, transparent) !important; }
    .app-sidebar :focus-visible { outline-color: var(--color-primary) !important; }
    .app-company-context__dot { background: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent); }
    .app-navigation__rail-link { color: #d5e0e9; }
    .app-navigation__rail-link.is-active { color: #f7fffe !important; }
    .app-navigation__nested summary, .app-navigation__subcategory summary { display: flex; min-height: 44px; cursor: pointer; list-style: none; align-items: center; gap: .65rem; border-radius: .75rem; padding: .55rem .75rem; color: #cbd8e3; font-size: .875rem; font-weight: 650; }
    .app-navigation__nested summary::-webkit-details-marker, .app-navigation__subcategory summary::-webkit-details-marker { display: none; }
    .app-navigation__nested summary span, .app-navigation__subcategory summary span { min-width: 0; flex: 1; }
    .app-navigation__nested-content { display: grid; gap: .2rem; padding-inline-start: .65rem; }
    .app-navigation__subcategory-items { display: grid; gap: .15rem; padding-inline-start: .6rem; }
    .app-navigation__setup-item { min-height: 40px !important; padding-block: .45rem !important; }
    .app-navigation__setup-item span { white-space: normal !important; line-height: 1.35; }
    .app-navigation__overview { padding-inline-start: .75rem; }
    .app-navigation__nested[open] > summary > .app-navigation__chevron, .app-navigation__subcategory[open] > summary > .app-navigation__chevron { transform: rotate(180deg); }
    @media (max-width: 767px) {
        .app-topbar { display: flex; gap: .25rem; }
        .app-topbar__identity { min-width: 0; overflow: hidden; flex: 0 0 7.5rem; }
        .app-topbar__search { flex: 0 0 2.75rem; }
        .app-topbar__mobile-brand { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-topbar__actions { flex: none; }
        .app-topbar__actions > form { display: none !important; }
    }
</style>
@php
    $actor = auth()->user();
    $isArabic = in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true);
    $navigationGroups = app(\App\Modules\Platform\Support\ApplicationNavigation::class)->for($actor, app()->getLocale());
    $activeGroup = collect($navigationGroups)->firstWhere('active', true);
    $activeItem = collect($activeGroup['items'] ?? [])->firstWhere('active', true);
    $contextBranch = app(\App\Modules\Platform\Support\DefaultOperatingContext::class)->branch($actor);
    $contextName = $contextBranch
        ? ($isArabic ? $contextBranch->name_ar : $contextBranch->name_en)
        : ($isArabic ? 'الفرع الرئيسي' : 'Main branch');
    $companyName = config('app.name', '3allaf | علاف');
    $unreadNotifications = $actor->unreadNotifications()->count();
@endphp
<flux:sidebar
    collapsible="mobile"
    class="app-sidebar z-30! h-dvh max-h-dvh w-[272px] gap-2 overflow-hidden border-e p-3"
>
    <flux:sidebar.header class="app-brand-block">
        <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
        <flux:tooltip :content="$isArabic ? 'توسيع أو طي الشريط الجانبي' : 'Expand or collapse sidebar'" :position="$isArabic ? 'left' : 'right'">
            <button type="button" class="sidebar-desktop-toggle hidden size-11 items-center justify-center rounded-lg text-slate-300 transition hover:bg-white/10 hover:text-white lg:inline-flex" x-on:click="$dispatch('toggle-desktop-sidebar')" :aria-expanded="document.documentElement.dataset.sidebarMode !== 'collapsed'" aria-controls="application-navigation" aria-label="{{ $isArabic ? 'توسيع أو طي الشريط الجانبي' : 'Expand or collapse sidebar' }}">
                <flux:icon name="bars-3-center-left" class="size-5" />
            </button>
        </flux:tooltip>
        <flux:sidebar.collapse tooltip="{{ app()->isLocale('ar-EG') ? 'اقفل القائمة' : ($isArabic ? 'إغلاق القائمة' : 'Close menu') }}" class="lg:hidden" />
    </flux:sidebar.header>
    <div class="app-company-context" title="{{ $companyName }}"><span class="app-company-context__dot" aria-hidden="true"></span><span class="min-w-0"><strong>{{ $companyName }}</strong><small>{{ $contextName }}</small></span></div>
    <div class="flex items-center justify-between gap-2 px-1 text-xs text-slate-300">
        <span>{{ __('Navigation help') }}</span>
        <x-context-help :title="__('Navigation help')" :label="__('Open navigation help')" sidebar>
            <ul>
                <li>{{ __('Use Tab to reach links and section controls, then Enter or Space to activate them.') }}</li>
                <li>{{ __('Use Up and Down arrows to move between visible navigation controls. Use Left and Right arrows to close or open a section.') }}</li>
                <li>{{ __('The highlighted destination is your current screen. Your expanded sections and sidebar position are preserved during navigation.') }}</li>
                <li>{{ __('Supplier groups appear before customer groups. Product cards, categories, brands, filters, and inventory destinations are kept together.') }}</li>
            </ul>
        </x-context-help>
    </div>
    <x-app-navigation id="application-navigation" :groups="$navigationGroups" />
</flux:sidebar>
<div class="app-layout__content min-w-0">
    <header class="app-topbar" aria-label="{{ $isArabic ? 'شريط التطبيق' : 'Application header' }}">
        <div class="app-topbar__identity">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-3" aria-label="{{ $isArabic ? 'فتح قائمة التنقل' : 'Open navigation' }}" />
            <div class="hidden min-w-0 sm:block">
                <div class="flex items-center gap-1.5 text-[11px] font-medium text-text-muted"><span>{{ $activeGroup['label'] ?? ($isArabic ? 'نظام راجح' : 'Rajeh ERP') }}</span><flux:icon.chevron-left class="hidden size-3 rtl:block" /><flux:icon.chevron-right class="size-3 rtl:hidden" /></div>
                <p class="truncate text-sm font-bold text-text-primary">{{ $activeItem['label'] ?? ($title ?? ($isArabic ? 'لوحة القيادة' : 'Dashboard')) }}</p>
            </div>
            <span class="app-topbar__mobile-brand sm:hidden text-sm font-bold text-text-primary">{{ $isArabic ? 'نظام راجح' : 'Rajeh ERP' }}</span>
        </div>
        <button type="button" class="app-topbar__search" x-data x-on:click="$dispatch('open-navigation-search')" aria-label="{{ app()->isLocale('ar-EG') ? 'دوّر سريع في النظام' : ($isArabic ? 'بحث سريع في النظام' : 'Quick system search') }}"><flux:icon.magnifying-glass class="size-5" /><span>{{ app()->isLocale('ar-EG') ? 'ادوّر عن شاشة أو إجراء' : ($isArabic ? 'ابحث عن شاشة أو إجراء' : 'Search screens and actions') }}</span><kbd dir="ltr">Ctrl K</kbd></button>
        <div class="app-topbar__actions">
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip content="{{ app()->isLocale('ar-EG') ? 'إنشاء سريع' : ($isArabic ? 'إنشاء سريع' : 'Quick create') }}" position="bottom"><flux:button variant="ghost" square icon="plus" aria-label="{{ app()->isLocale('ar-EG') ? 'إنشاء سريع' : ($isArabic ? 'إنشاء سريع' : 'Quick create') }}" /></flux:tooltip>
                <flux:menu>
                    @can('pos_sales.create')<flux:menu.item :href="route('pos')" icon="shopping-cart" wire:navigate>{{ $isArabic ? 'عملية بيع' : 'New sale' }}</flux:menu.item>@endcan
                    @can('customers.create')<flux:menu.item :href="route('customers.create')" icon="user-plus" wire:navigate>{{ $isArabic ? 'عميل جديد' : 'New customer' }}</flux:menu.item>@endcan
                    @can('products_categories_brands.create')<flux:menu.item :href="route('catalog.products.create')" icon="cube" wire:navigate>{{ $isArabic ? 'منتج جديد' : 'New product' }}</flux:menu.item>@endcan
                    @can('transfers.create')<flux:menu.item :href="route('inventory.transfers.create')" icon="arrows-right-left" wire:navigate>{{ $isArabic ? 'تحويل مخزون' : 'Stock transfer' }}</flux:menu.item>@endcan
                    @can('inventory_stock_card.create')<flux:menu.item :href="route('inventory.adjustments.create')" icon="adjustments-horizontal" wire:navigate>{{ $isArabic ? 'تسوية مخزون' : 'Inventory adjustment' }}</flux:menu.item>@endcan
                </flux:menu>
            </flux:dropdown>
            <flux:tooltip content="{{ $isArabic ? 'الإشعارات' : 'Notifications' }}" position="bottom"><flux:button :href="route('notifications.index')" variant="ghost" square aria-label="{{ $isArabic ? 'الإشعارات، غير المقروءة: '.$unreadNotifications : 'Notifications, unread: '.$unreadNotifications }}" wire:navigate><span class="relative"><flux:icon.bell class="size-5" />@if($unreadNotifications > 0)<span class="absolute -end-2 -top-2 min-w-4 rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-4 text-white" dir="ltr">{{ min($unreadNotifications, 99) }}</span>@endif</span></flux:button></flux:tooltip>
            <flux:tooltip content="{{ $isArabic ? 'مساعدة الصفحة' : 'Page help' }}" position="bottom"><flux:button type="button" variant="ghost" square icon="question-mark-circle" aria-label="{{ $isArabic ? 'مساعدة الصفحة' : 'Page help' }}" x-data x-on:click="$dispatch('open-page-guide')" class="hidden md:inline-flex" /></flux:tooltip>
            <x-locale-switcher class="hidden md:inline-flex" compact />
            <flux:tooltip content="{{ $isArabic ? 'المظهر' : 'Appearance' }}" position="bottom"><flux:button type="button" variant="ghost" square icon="sun" aria-label="{{ $isArabic ? 'تخصيص المظهر' : 'Customize appearance' }}" x-data x-on:click="$dispatch('open-appearance-customizer')" class="hidden md:inline-flex" /></flux:tooltip>
            <flux:dropdown position="bottom" align="end"><flux:profile :initials="$actor->initials()" :name="$actor->name" icon-trailing="chevron-down" class="app-topbar__profile" /><flux:menu><div class="px-3 py-2"><strong class="block truncate text-sm">{{ $actor->name }}</strong><span class="block truncate text-xs text-text-muted" dir="ltr">{{ $actor->email }}</span></div><flux:menu.separator /><flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ $isArabic ? 'الملف والإعدادات' : 'Profile & settings' }}</flux:menu.item><flux:menu.item as="button" type="button" icon="question-mark-circle" class="md:hidden" x-data x-on:click="$dispatch('open-page-guide')">{{ $isArabic ? 'مساعدة الصفحة' : 'Page help' }}</flux:menu.item><flux:menu.item as="button" type="button" icon="sun" class="md:hidden" x-data x-on:click="$dispatch('open-appearance-customizer')">{{ $isArabic ? 'المظهر' : 'Appearance' }}</flux:menu.item><x-locale-switcher class="md:hidden" compact /><flux:menu.separator /><form method="POST" action="{{ route('logout') }}">@csrf<flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">{{ $isArabic ? 'تسجيل الخروج' : 'Log out' }}</flux:menu.item></form></flux:menu></flux:dropdown>
        </div>
    </header>
    <div x-data="navigationSearch(@js($navigationGroups))" x-on:open-navigation-search.window="open = true; $nextTick(() => $refs.query.focus())" x-on:keydown.meta.k.window.prevent="open = true; $nextTick(() => $refs.query.focus())" x-on:keydown.ctrl.k.window.prevent="open = true; $nextTick(() => $refs.query.focus())" x-on:keydown.escape.window="open = false">
        <div x-cloak x-show="open" class="navigation-search__backdrop" x-on:click="open = false"></div>
        <section x-cloak x-show="open" x-transition class="navigation-search" role="dialog" aria-modal="true" aria-labelledby="navigation-search-title" x-trap.noscroll="open"><div class="navigation-search__input"><flux:icon.magnifying-glass class="size-5" /><label id="navigation-search-title" class="sr-only" for="navigation-search-query">{{ app()->isLocale('ar-EG') ? 'دوّر في القايمة' : ($isArabic ? 'بحث التنقل' : 'Navigation search') }}</label><input id="navigation-search-query" x-ref="query" x-model="query" type="search" placeholder="{{ $isArabic ? 'اكتب اسم الشاشة أو الوحدة' : 'Type a screen or module name' }}"><flux:button type="button" variant="ghost" square icon="x-mark" aria-label="{{ app()->isLocale('ar-EG') ? 'اقفل البحث' : ($isArabic ? 'إغلاق البحث' : 'Close search') }}" x-on:click="open=false" /></div><div class="navigation-search__results"><template x-for="item in results" :key="item.url"><a :href="item.url" x-on:click="open = false"><span x-text="item.label"></span><small x-text="item.group"></small></a></template><p x-show="results.length === 0" class="p-5 text-center text-sm text-text-muted">{{ app()->isLocale('ar-EG') ? 'مفيش شاشة مطابقة ضمن صلاحياتك.' : ($isArabic ? 'لا توجد شاشة مطابقة ضمن صلاحياتك.' : 'No matching screen is available in your permissions.') }}</p></div></section>
    </div>
    @include('components.platform.dashboard-tools', ['pageGuide' => \App\Modules\Platform\Data\PageGuideContext::fromRequest($actor)])
    <flux:main class="app-main">{{ $slot }}</flux:main>
    <x-app-footer />
    @persist('toast')<flux:toast.group><flux:toast /></flux:toast.group>@endpersist
</div>
@fluxScripts
</body>
</html>
