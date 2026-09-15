<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<x-app.page :title="__('Settings')" :description="__('Manage your profile and account settings')" max-width="7xl">
    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="space-y-6">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>

            @php $isArabic = str_starts_with(app()->getLocale(), 'ar'); @endphp
            <div
                x-data="{
                    darkSidebar: false,
                    init() {
                        let isDark = false;
                        try {
                            isDark = localStorage.getItem('toyjoy_ui_dark_sidebar') === 'true';
                        } catch (e) {
                            isDark = document.documentElement.dataset.darkSidebar === 'true';
                        }
                        this.darkSidebar = isDark;
                    },
                    toggle() {
                        const isDark = Boolean(this.darkSidebar);
                        document.documentElement.dataset.darkSidebar = isDark ? 'true' : 'false';
                        try {
                            if (isDark) {
                                localStorage.setItem('toyjoy_ui_dark_sidebar', 'true');
                            } else {
                                localStorage.removeItem('toyjoy_ui_dark_sidebar');
                            }
                        } catch (e) {}
                    }
                }"
                class="pt-6 border-t border-border"
            >
                <label class="settings-dark-sidebar-control flex items-start gap-3 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        x-model="darkSidebar"
                        x-on:change="toggle()"
                        class="settings-dark-sidebar-checkbox mt-0.5 size-4 shrink-0 rounded border border-border bg-surface accent-primary transition focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-surface"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-medium text-text-primary">
                            {{ $isArabic ? 'شريط جانبي وخلفية داكنان' : 'Dark sidebar/background' }}
                        </span>
                        <span class="text-xs text-text-muted">
                            {{ $isArabic ? 'يغير الشريط الجانبي وخلفية التطبيق بشكل مستقل عن المظهر الفاتح/الداكن.' : 'Changes the sidebar and app background independently of light/dark appearance.' }}
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </x-pages::settings.layout>
</x-app.page>
