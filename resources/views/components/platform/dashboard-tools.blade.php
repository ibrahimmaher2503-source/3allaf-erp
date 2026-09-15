@php
    use App\Modules\Platform\Data\PageGuideContext;

    $pageGuideContext = $pageGuide ?? PageGuideContext::fromRequest(auth()->user());
    $guide = $pageGuideContext?->toArray();
    $tutorialProgress = auth()->user()?->uiPreference?->tutorial_progress ?? [];
    $locale = app()->getLocale();
    $isArabic = str_starts_with($locale, 'ar');
    $fallback = app()->isLocale('ar-EG') ? 'ما تمّش نشر دليل تفصيلي للصفحة دي بعد. يمكنك استخدام الصفحة وفق صلاحياتك الحالية.' : ($isArabic ? 'لم يتم نشر دليل تفصيلي لهذه الصفحة بعد. يمكنك استخدام الصفحة وفق صلاحياتك الحالية.' : 'A detailed guide has not been published for this page yet. You can still use the page according to your current permissions.');
@endphp

<div
    x-data="dashboardAssistant({ context: @js($guide), locale: @js($locale), fallback: @js($fallback), preferences: window.__toyJoyUiPreferences || {}, progress: @js($tutorialProgress), preferencesUrl: @js(route('platform.ui-preferences')), progressUrl: @js(route('platform.tutorial-progress')) })"
    x-on:toggle-desktop-sidebar.window="toggleDesktopSidebar()"
    x-on:open-page-guide.window="openGuide($event)"
    x-on:open-appearance-customizer.window="openCustomizer($event)"
    x-on:keydown.escape.window="closeAll()"
    x-on:keydown.arrow-right.window="if (tour.active) nextTour()"
    x-on:keydown.arrow-left.window="if (tour.active) previousTour()"
    class="platform-assistant"
>
    <div class="platform-assistant__desktop-launchers" aria-label="{{ $isArabic ? 'أدوات الصفحة' : 'Page tools' }}">
        <button type="button" class="platform-assistant__launcher" x-on:click="openGuide($event)" aria-controls="page-guide-drawer" aria-label="{{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}" data-guide="page-guide-launcher">
            <flux:icon.question-mark-circle class="size-5" aria-hidden="true" /><span class="sr-only">{{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}</span>
        </button>
        <button type="button" class="platform-assistant__launcher platform-assistant__launcher--appearance" x-on:click="openCustomizer($event)" aria-controls="appearance-customizer" aria-label="{{ $isArabic ? 'تخصيص المظهر' : 'Appearance Customizer' }}">
            <flux:icon.paint-brush class="size-5" aria-hidden="true" /><span class="sr-only">{{ $isArabic ? 'تخصيص المظهر' : 'Appearance Customizer' }}</span>
        </button>
    </div>

    <button type="button" class="platform-assistant__mobile-launcher" x-on:click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-controls="mobile-dashboard-tools" aria-label="{{ $isArabic ? 'أدوات لوحة التحكم' : 'Dashboard tools' }}">
        <flux:icon.adjustments-horizontal class="size-5" aria-hidden="true" />
    </button>
    <div id="mobile-dashboard-tools" x-cloak x-show="mobileMenuOpen" x-transition class="platform-assistant__mobile-menu">
        <button type="button" x-on:click="mobileMenuOpen = false; openGuide($event)" class="platform-assistant__menu-action"><flux:icon.question-mark-circle class="size-4" /> {{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}</button>
        <button type="button" x-on:click="mobileMenuOpen = false; openCustomizer($event)" class="platform-assistant__menu-action"><flux:icon.paint-brush class="size-4" /> {{ $isArabic ? 'تخصيص المظهر' : 'Customize appearance' }}</button>
    </div>

    <div x-cloak x-show="guideOpen" x-transition.opacity class="platform-assistant__backdrop" x-on:click="closeGuide()"></div>
    <aside id="page-guide-drawer" x-cloak x-show="guideOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100" class="platform-assistant__drawer" role="dialog" aria-modal="true" aria-labelledby="page-guide-title" x-trap.noscroll="guideOpen" x-ref="guideDrawer">
        <header class="platform-assistant__header">
            <div>
                <p class="platform-assistant__eyebrow">{{ $isArabic ? 'دليل الصفحة' : 'Page Guide' }}</p>
                <h2 id="page-guide-title" class="platform-assistant__title" x-text="text(context?.title) || (locale.startsWith('ar') ? 'دليل الصفحة' : 'Page Guide')"></h2>
                <p class="platform-assistant__screen" x-show="context?.screen_id">{{ app()->isLocale('ar-EG') ? 'إرشاد مرتبط بالشاشة دي' : ($isArabic ? 'إرشاد مرتبط بهذه الشاشة' : 'Guidance for this screen') }}</p>
            </div>
            <button type="button" class="platform-assistant__close" x-on:click="closeGuide()" aria-label="{{ app()->isLocale('ar-EG') ? 'اقفل' : ($isArabic ? 'إغلاق' : 'Close') }}"><flux:icon.x-mark class="size-5" /></button>
        </header>

        <div class="platform-assistant__body">
            <template x-if="context">
                <div class="space-y-5">
                    <div class="platform-assistant__progress" role="status" aria-live="polite"><span x-text="tutorialStatusText()"></span><flux:icon.check-circle x-show="tutorialStatus === 'completed'" class="size-4" aria-hidden="true" /></div>
                    <section x-show="context.module" class="rounded-xl border border-border bg-surface-muted p-4"><h3>{{ app()->isLocale('ar-EG') ? 'عن الوحدة دي' : ($isArabic ? 'عن هذه الوحدة' : 'About this module') }}</h3><p x-text="text(context.module?.description)"></p></section>
                    <section><h3>{{ app()->isLocale('ar-EG') ? 'الصفحة دي بتعمل إيه؟' : ($isArabic ? 'ماذا تفعل هذه الصفحة؟' : 'What this page does') }}</h3><p x-text="text(context.purpose)"></p></section>
                    <section><h3>{{ $isArabic ? 'متى تستخدمها؟' : 'When to use it' }}</h3><p x-text="text(context.when_to_use)"></p></section>
                    <section><h3>{{ $isArabic ? 'ما يمكنك فعله' : 'What you can do here' }}</h3><ul class="platform-assistant__list"><template x-for="action in context.approved_actions" :key="action.key"><li x-text="text(action.label)"></li></template></ul><p x-show="!context.approved_actions?.length" class="platform-assistant__muted">{{ app()->isLocale('ar-EG') ? 'مفيش إجراءات متاحة للمستخدم ده هنا.' : ($isArabic ? 'لا توجد إجراءات متاحة لهذا المستخدم هنا.' : 'No actions are available for this user here.') }}</p></section>
                    <section><h3>{{ $isArabic ? 'خطوات الاستخدام' : 'Step-by-step usage' }}</h3><ol class="platform-assistant__steps"><template x-for="step in context.sections.steps" :key="step.key"><li><strong x-text="text(step.title)"></strong><span x-text="text(step.body)"></span></li></template></ol></section>
                    <details open><summary>{{ $isArabic ? 'شرح الحقول' : 'Field explanations' }}</summary><template x-for="field in context.sections.fields" :key="field.key"><p class="mt-3" x-text="text(field.body)"></p></template></details>
                    <details><summary>{{ $isArabic ? 'ملاحظات مهمة' : 'Important notes' }}</summary><p class="mt-3" x-text="text(context.sections.notes)"></p></details>
                    <details><summary>{{ $isArabic ? 'تحذيرات وأخطاء شائعة' : 'Warnings and common errors' }}</summary><p class="mt-3" x-text="text(context.sections.warnings)"></p><p class="mt-2" x-text="text(context.sections.errors)"></p></details>
                    <section><h3>{{ $isArabic ? 'الخطوة التالية' : 'Next step' }}</h3><p x-text="text(context.sections.next_step)"></p></section>
                    <details><summary>{{ $isArabic ? 'الأسئلة الشائعة' : 'Frequently asked questions' }}</summary><p class="mt-3" x-text="text(context.sections.faq)"></p></details>
                    <section class="platform-assistant__meta"><span>{{ $isArabic ? 'الإصدار' : 'Version' }} <b x-text="context.version"></b></span><span>{{ $isArabic ? 'آخر تحديث' : 'Updated' }} <b x-text="context.updated_at"></b></span></section>
                </div>
            </template>
            <template x-if="!context"><p class="platform-assistant__fallback" x-text="fallback"></p></template>
        </div>

        <footer class="platform-assistant__footer">
            <button type="button" class="platform-assistant__primary" x-show="context?.tour_steps?.length" x-on:click="startTour()" x-text="tourButtonLabel()"></button>
            <button type="button" class="platform-assistant__secondary" x-show="context?.tour_steps?.length && ['completed', 'dismissed'].includes(tutorialStatus)" x-on:click="startTour(true)">{{ $isArabic ? 'إعادة الجولة' : 'Restart tour' }}</button>
            <a x-show="context?.screen_id" :href="context?.screen_id ? '{{ url('/help/screens') }}/' + context.screen_id : '#'" class="platform-assistant__secondary">{{ $isArabic ? 'فتح الدليل الكامل' : 'Open Full Guide' }}</a>
            <a x-show="context?.flows?.length" :href="context?.flows?.length ? '{{ url('/help/flows') }}/' + context.flows[0] : '#'" class="platform-assistant__secondary">{{ $isArabic ? 'فتح تدفق المستخدم' : 'Open User Flow' }}</a>
        </footer>
    </aside>

    <div x-cloak x-show="customizerOpen" x-transition.opacity class="platform-assistant__backdrop" x-on:click="closeCustomizer()"></div>
    <aside id="appearance-customizer" x-cloak x-show="customizerOpen" x-transition class="platform-assistant__drawer platform-assistant__drawer--customizer" role="dialog" aria-modal="true" aria-labelledby="appearance-title" x-trap.noscroll="customizerOpen">
        <header class="platform-assistant__header"><div><p class="platform-assistant__eyebrow">{{ $isArabic ? 'تخصيص المظهر' : 'Appearance Customizer' }}</p><h2 id="appearance-title" class="platform-assistant__title">{{ app()->isLocale('ar-EG') ? 'إعدادات العرض' : ($isArabic ? 'إعدادات العرض' : 'Display settings') }}</h2></div><button type="button" class="platform-assistant__close" x-on:click="closeCustomizer()" aria-label="{{ app()->isLocale('ar-EG') ? 'اقفل' : ($isArabic ? 'إغلاق' : 'Close') }}"><flux:icon.x-mark class="size-5" /></button></header>
        <div class="platform-assistant__body platform-assistant__controls">
            <label>{{ $isArabic ? 'المظهر' : 'Appearance' }}<select x-model="preferences.appearance" x-on:change="applyPreferences(); savePreferences()"><option value="system">{{ $isArabic ? 'النظام' : 'System' }}</option><option value="light">{{ $isArabic ? 'فاتح' : 'Light' }}</option><option value="dark">{{ $isArabic ? 'داكن' : 'Dark' }}</option></select></label>
            <label>{{ $isArabic ? 'اللون المميز' : 'Accent color' }}<select x-model="preferences.accent_color" x-on:change="applyPreferences(); savePreferences()"><option value="teal">{{ $isArabic ? 'تركوازي' : 'Teal' }}</option><option value="indigo">{{ $isArabic ? 'نيلي' : 'Indigo' }}</option><option value="amber">{{ $isArabic ? 'كهرماني' : 'Amber' }}</option><option value="rose">{{ $isArabic ? 'وردي' : 'Rose' }}</option></select></label>
            <label>{{ $isArabic ? 'الشريط الجانبي' : 'Sidebar' }}<select x-model="preferences.sidebar_mode" x-on:change="applyPreferences(); savePreferences()"><option value="expanded">{{ $isArabic ? 'موسع' : 'Expanded' }}</option><option value="collapsed">{{ $isArabic ? 'مطوي' : 'Collapsed' }}</option></select></label>
            <label>{{ app()->isLocale('ar-EG') ? 'عرض المحتوى' : ($isArabic ? 'عرض المحتوى' : 'Content width') }}<select x-model="preferences.content_width" x-on:change="applyPreferences(); savePreferences()"><option value="wide">{{ $isArabic ? 'واسع' : 'Wide' }}</option><option value="compact">{{ $isArabic ? 'مضغوط' : 'Compact' }}</option></select></label>
            <label>{{ $isArabic ? 'كثافة الجداول' : 'Table density' }}<select x-model="preferences.table_density" x-on:change="applyPreferences(); savePreferences()"><option value="comfortable">{{ $isArabic ? 'مريح' : 'Comfortable' }}</option><option value="compact">{{ $isArabic ? 'مضغوط' : 'Compact' }}</option></select></label>
            <label>{{ $isArabic ? 'حجم الخط' : 'Font scale' }}<select x-model="preferences.font_scale" x-on:change="applyPreferences(); savePreferences()"><option value="small">{{ $isArabic ? 'صغير' : 'Small' }}</option><option value="normal">{{ $isArabic ? 'عادي' : 'Normal' }}</option><option value="large">{{ $isArabic ? 'كبير' : 'Large' }}</option></select></label>
            <label class="platform-assistant__check"><input type="checkbox" x-model="preferences.reduced_motion" x-on:change="applyPreferences(); savePreferences()"> {{ $isArabic ? 'تقليل الحركة' : 'Reduced motion' }}</label>
            <label class="platform-assistant__check items-start">
                <input type="checkbox" x-model="darkSidebar" x-on:change="updateDarkSidebar()" class="mt-0.5 shrink-0">
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-semibold text-text-primary">{{ $isArabic ? 'شريط جانبي وخلفية داكنان' : 'Dark sidebar/background' }}</span>
                    <span class="text-xs font-normal text-text-muted">{{ $isArabic ? 'يغير الشريط الجانبي وخلفية التطبيق بشكل مستقل عن المظهر الفاتح/الداكن.' : 'Changes the sidebar and app background independently of light/dark appearance.' }}</span>
                </span>
            </label>
            <button type="button" class="platform-assistant__secondary" x-on:click="resetPreferences()">{{ $isArabic ? 'إعادة الإعدادات الافتراضية' : 'Reset to defaults' }}</button>
            <p class="platform-assistant__save-status" x-show="saveStatus !== 'idle'" x-cloak role="status" aria-live="polite" :class="{ 'platform-assistant__save-status--error': saveStatus === 'error' }" x-text="saveStatusText()"></p>
        </div>
    </aside>

    <div x-cloak x-show="tour.active" x-transition.opacity class="platform-assistant__tour-backdrop" x-on:click="finishTour()"></div>
    <div
        x-cloak
        x-show="tour.active"
        x-transition
        x-ref="tourCard"
        class="platform-assistant__tour"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tour-title"
        x-trap.noscroll="tour.active"
        :style="tourCardStyle"
    >
        <p class="platform-assistant__eyebrow">{{ $isArabic ? 'جولة إرشادية' : 'Guided tour' }}</p>
        <h2 id="tour-title" x-text="text(tour.step?.title)"></h2>
        <p x-text="text(tour.step?.body)"></p>
        <p class="platform-assistant__muted" x-text="tour.visibleIndex + ' / ' + tour.steps.length"></p>
        <div class="platform-assistant__tour-actions">
            <button type="button" x-on:click="previousTour()" :disabled="tour.visibleIndex <= 1">{{ $isArabic ? 'السابق' : 'Previous' }}</button>
            <button type="button" x-on:click="skipTour()">{{ $isArabic ? 'تخطي' : 'Skip' }}</button>
            <button type="button" class="platform-assistant__primary" x-on:click="nextTour()" x-text="tour.visibleIndex === tour.steps.length ? (locale.startsWith('ar') ? 'إنهاء' : 'Finish') : (locale.startsWith('ar') ? 'التالي' : 'Next')"></button>
        </div>
    </div>
</div>
