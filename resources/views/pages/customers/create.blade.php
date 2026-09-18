<x-layouts::app :title="__('New customer')">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Register customer')" :description="__('Add a customer profile with contact details and consent.')">
            <x-slot:actions><flux:button href="{{ route('customers.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to customers') }}</flux:button></x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif
        @if (session('duplicate_candidate'))
            @php($candidate = session('duplicate_candidate'))
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="space-y-2">
                    <p>{{ __('A visible customer profile already uses this phone number or email address. Review it before creating another profile; the system never merges profiles automatically.') }}</p>
                    <p class="text-sm"><span class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $candidate['name_ar'] : $candidate['name_en'] }}</span> · <span dir="ltr" class="font-mono">{{ $candidate['phone_display'] }}</span></p>
                    <flux:button href="{{ route('customers.show', $candidate['id']) }}" variant="primary" size="sm">{{ __('Review matching customer profile') }}</flux:button>
                </div>
            </flux:callout>
        @endif
        @if ($consentPolicyError)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="space-y-2">
                    <p>{{ str_starts_with(app()->getLocale(), 'ar') ? 'لا يمكن إنشاء ملف عميل لأن إعداد الموافقة التلقائية لتقديم الخدمة غير مكتمل. يلزم حفظ سجل موافقة صحيح مع كل ملف عميل.' : 'Customer creation is unavailable because the automatic service-delivery consent setting is incomplete. A valid consent record is required for every customer profile.' }}</p>
                    @can('company_settings.edit')
                        <flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="primary" size="sm">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إعداد نطاق أغراض الموافقة' : 'Configure consent purposes' }}</flux:button>
                    @else
                        <p class="text-xs">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اطلب من مسؤول إعدادات الشركة تهيئة هذا الإعداد.' : 'Ask a company-settings administrator to configure this setting.' }}</p>
                    @endcan
                </div>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('customers.store') }}" class="space-y-5" data-customer-form>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <div class="border-b border-border pb-4">
                    <flux:heading size="lg">{{ __('Identity and contact') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Enter the customer name and the contact details needed to reach them.') }}</flux:text>
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,.9fr)]">
                    <div class="space-y-5">
                        <div class="rounded-xl border border-border bg-surface p-4">
                            <p class="mb-4 text-sm font-semibold text-text-primary">{{ __('Customer name') }}</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input name="first_name_ar" :label="__('First name (Arabic)')" :value="old('first_name_ar')" required dir="rtl" />
                                <flux:input name="last_name_ar" :label="__('Last name (Arabic)')" :value="old('last_name_ar')" required dir="rtl" />
                                <flux:input name="first_name_en" :label="__('First name (English, optional)')" :value="old('first_name_en')" dir="ltr" />
                                <flux:input name="last_name_en" :label="__('Last name (English, optional)')" :value="old('last_name_en')" dir="ltr" />
                            </div>
                        </div>

                        <div class="rounded-xl border border-border bg-surface p-4" x-data="{ governorate: @js((string) old('governorate_id', '')), city: @js((string) old('city_id', '')), cityCounts: @js($cities->countBy('governorate_id')->mapWithKeys(fn($count,$id)=>[(string)$id=>$count])) }">
                            <p class="mb-4 text-sm font-semibold text-text-primary">{{ __('Address') }}</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select name="governorate_id" x-model="governorate" x-on:change="city = ''" :label="__('Governorate')" required><flux:select.option value="">{{ __('Select governorate') }}</flux:select.option>@foreach($governorates as $governorate)<flux:select.option value="{{ $governorate->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $governorate->name_ar : $governorate->name_en }}</flux:select.option>@endforeach</flux:select>
                                <flux:select name="city_id" x-model="city" :label="__('City / locality')" required><flux:select.option value="">{{ __('Select city / locality') }}</flux:select.option>@foreach($cities as $city)<flux:select.option value="{{ $city->id }}" x-show="governorate === '{{ $city->governorate_id }}'">{{ str_starts_with(app()->getLocale(), 'ar') ? $city->name_ar : $city->name_en }}</flux:select.option>@endforeach</flux:select>
                                <p class="sm:col-span-2 text-sm text-amber-700" x-cloak x-show="governorate && !cityCounts[governorate]">{{ __('No active city/locality exists for this governorate.') }} @can('company_settings.edit')<a class="font-semibold underline" href="{{ route('admin.settings.cities') }}">{{ __('Manage cities') }}</a>@endcan</p>
                                <flux:textarea name="address_ar" :label="__('Arabic address')" :value="old('address_ar')" dir="rtl" />
                                <flux:textarea name="address_en" :label="__('English address')" :value="old('address_en')" dir="ltr" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-border bg-surface p-4">
                        <p class="mb-4 text-sm font-semibold text-text-primary">{{ __('Contact details') }}</p>
                        <div class="grid gap-4">
                            <flux:input name="phone" :label="__('Primary phone')" :value="old('phone')" autocomplete="tel" :placeholder="__('e.g. 01012345678 or +20 1012345678')" :description="__('Egyptian phone numbers are accepted in local or international format.')" dir="ltr" />
                            <flux:input name="email" :label="__('Email')" :value="old('email')" type="email" autocomplete="email" />
                            <flux:input name="secondary_phone" :label="__('Secondary phone')" :value="old('secondary_phone')" autocomplete="tel" :placeholder="__('Optional Egyptian phone number')" dir="ltr" />
                            <flux:select name="customer_group_id" :label="__('Customer group')" :description="__('Optional hierarchical group for customer search and reporting.')">
                                <flux:select.option value="">{{ __('No group assigned') }}</flux:select.option>
                                @foreach ($groupOptions as $group)
                                    <flux:select.option value="{{ $group->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $group->hierarchy_path : collect(explode(' / ', $group->hierarchy_path))->map(fn($name) => $groupOptions->first(fn($candidate) => ($candidate->name_ar ?: $candidate->name_en) === $name)?->name_en ?: $name)->implode(' / ') }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:text class="text-xs text-slate-500">{{ __('The selected selling store is') }}: <span class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</span></flux:text>
                <flux:button type="submit" variant="primary" :disabled="$consentPurposes === []">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
