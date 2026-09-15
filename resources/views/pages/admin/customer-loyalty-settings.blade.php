@php
    $arabic = str_starts_with(app()->getLocale(), 'ar');
    $purposeOptions = [
        'service_delivery' => $arabic ? 'تقديم الخدمة للعميل' : 'Service delivery',
        'loyalty' => $arabic ? 'برنامج نقاط الولاء' : 'Loyalty programme',
        'marketing' => $arabic ? 'العروض والتواصل التسويقي' : 'Marketing communications',
    ];
    $selectedPurposes = array_values(array_unique(['service_delivery', ...old('purposes', $customer['purposes'] ?: [])]));
@endphp

<x-layouts::app :title="$arabic ? 'إعدادات العملاء والولاء' : 'Customer and loyalty settings'">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6" x-data="{ tab: '{{ old('section', 'customer') }}' }">
        <x-page-header :title="$arabic ? 'إعدادات العملاء والولاء' : 'Customer and loyalty settings'" :description="app()->isLocale('ar-EG') ? 'اضبط ما يحتاجه فريق العمل بس، ثم احفظ التغييرات.' : ($arabic ? 'اضبط ما يحتاجه فريق العمل فقط، ثم احفظ التغييرات.' : 'Set only the rules your team needs, then save your changes.')">
            <x-slot:actions>
                <flux:button href="{{ route('customers.index') }}" variant="subtle" icon="users">{{ $arabic ? 'فتح سجل العملاء' : 'Open customer records' }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" role="status">{{ session('status') }}</flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif

        <div class="rounded-2xl border border-border bg-surface p-2" role="tablist" aria-label="{{ $arabic ? 'أقسام الإعدادات' : 'Settings sections' }}">
            <button type="button" role="tab" x-on:click="tab = 'customer'" x-bind:aria-selected="tab === 'customer'" x-bind:class="tab === 'customer' ? 'bg-primary text-white shadow-sm' : 'text-text-muted hover:bg-surface-muted hover:text-text-primary'" class="min-h-11 rounded-xl px-4 text-sm font-semibold transition sm:px-6">{{ $arabic ? 'بيانات العملاء والموافقة' : 'Customer data and consent' }}</button>
            <button type="button" role="tab" x-on:click="tab = 'loyalty'" x-bind:aria-selected="tab === 'loyalty'" x-bind:class="tab === 'loyalty' ? 'bg-primary text-white shadow-sm' : 'text-text-muted hover:bg-surface-muted hover:text-text-primary'" class="min-h-11 rounded-xl px-4 text-sm font-semibold transition sm:px-6">{{ $arabic ? 'نقاط الولاء' : 'Loyalty points' }}</button>
        </div>

        <section x-show="tab === 'customer'" x-cloak role="tabpanel" class="space-y-5">
            <form method="POST" action="{{ route('admin.settings.customer-loyalty.save') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="section" value="customer">

                <section class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                    <div class="border-b border-border pb-4">
                        <h2 class="text-lg font-semibold text-text-primary">{{ $arabic ? 'موافقة العميل' : 'Customer consent' }}</h2>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ app()->isLocale('ar-EG') ? 'يُسجل إنشاء العميل موافقة تقديم الخدمة تلقائيًا، مع حفظ سجل الموافقة ومراجعته.' : ($arabic ? 'يُسجل إنشاء العميل موافقة تقديم الخدمة تلقائيًا، مع حفظ سجل الموافقة ومراجعته.' : 'Customer creation automatically records service-delivery consent with a retained, auditable consent record.') }}</p>
                    </div>

                    <details class="mt-5 rounded-xl border border-border bg-surface-muted/40 p-4" @if($errors->has('purposes') || $errors->has('wording_version') || $errors->has('wording_text') || $errors->has('retention_days')) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-text-primary">{{ $arabic ? 'إعدادات الموافقة المتقدمة' : 'Advanced consent settings' }}</summary>
                        <p class="mt-2 text-sm text-text-muted">{{ app()->isLocale('ar-EG') ? 'تقديم الخدمة مفعّل دائمًا لإنشاء العميل. يمكنك إدارة الأغراض الإضافية ونص الموافقة ومدة الاحتفاظ هنا.' : ($arabic ? 'تقديم الخدمة مفعّل دائمًا لإنشاء العميل. يمكنك إدارة الأغراض الإضافية ونص الموافقة ومدة الاحتفاظ هنا.' : 'Service delivery stays enabled for customer creation. Manage additional purposes, consent wording, and retention here.') }}</p>
                        <fieldset class="mt-4">
                            <legend class="text-sm font-semibold text-text-primary">{{ app()->isLocale('ar-EG') ? 'أغراض حفظ البيانات' : ($arabic ? 'أغراض حفظ البيانات' : 'Data collection purposes') }}</legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                @foreach ($purposeOptions as $value => $label)
                                    <label class="flex min-h-12 items-center gap-3 rounded-xl border border-border px-4 text-sm font-medium text-text-primary">
                                        @if ($value === 'service_delivery')
                                            <input type="hidden" name="purposes[]" value="service_delivery">
                                            <input type="checkbox" checked disabled class="size-4 rounded border-border text-primary focus:ring-primary">
                                        @else
                                            <input type="checkbox" name="purposes[]" value="{{ $value }}" @checked(in_array($value, $selectedPurposes, true)) class="size-4 rounded border-border text-primary focus:ring-primary">
                                        @endif
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('purposes')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                        </fieldset>

                        <div class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,.55fr)_minmax(0,1.45fr)]">
                            <flux:input name="wording_version" :label="$arabic ? 'رقم إصدار نص الموافقة' : 'Consent wording version'" :value="old('wording_version', $customer['wording_version'])" maxlength="80" required />
                            <flux:textarea name="wording_text" :label="app()->isLocale('ar-EG') ? 'نص الموافقة اللي يراه العميل' : ($arabic ? 'نص الموافقة الذي يراه العميل' : 'Consent text shown to the customer')" :value="old('wording_text', $customer['wording_text'])" rows="3" maxlength="2000" required />
                            <flux:input name="retention_days" type="number" min="0" max="36500" :label="$arabic ? 'مدة الاحتفاظ بسجل الموافقة بالأيام' : 'Consent record retention in days'" :value="old('retention_days', $customer['retention_days'])" required />
                        </div>
                    </details>
                </section>

                <section class="rounded-2xl border border-border bg-surface-muted/40 p-5 text-sm sm:p-6">
                    <h2 class="font-semibold text-text-primary">{{ $arabic ? 'قواعد ثابتة لحماية البيانات' : 'Fixed data-protection rules' }}</h2>
                    <ul class="mt-3 space-y-2 leading-6 text-text-muted">
                        <li>{{ app()->isLocale('ar-EG') ? 'يُوحَّد رقم الهاتف بالأرقام بس لمنع تكرار الملف نفسه.' : ($arabic ? 'يُوحَّد رقم الهاتف بالأرقام فقط لمنع تكرار الملف نفسه.' : 'Phone numbers are normalized to digits only to prevent duplicate profiles.') }}</li>
                        <li>{{ $arabic ? 'بيانات الأطفال تستخدم أغراض موافقة العميل نفسها تلقائيًا.' : 'Child profiles automatically use the same customer-consent purposes.' }}</li>
                        <li>{{ app()->isLocale('ar-EG') ? 'عرض سجل العميل يتبع صلاحيات المستخدم، ولا يحتاج إعدادًا منفصلًا هنا.' : ($arabic ? 'عرض سجل العميل يتبع صلاحيات المستخدم، ولا يحتاج إعدادًا منفصلًا هنا.' : 'Customer-history access follows user permissions and needs no separate setup here.') }}</li>
                    </ul>
                </section>

                @can('company_settings.edit')
                    <div class="flex flex-wrap items-end justify-between gap-4 border-t border-border pt-5">
                        <div class="max-w-lg flex-1"><flux:input name="notes" :label="$arabic ? 'ملاحظة داخلية (اختياري)' : 'Internal note (optional)'" :value="old('section') === 'customer' ? old('notes') : null" maxlength="1000" /></div>
                        <flux:button type="submit" variant="primary" icon="check">{{ app()->isLocale('ar-EG') ? 'احفظ إعدادات العملاء' : ($arabic ? 'حفظ إعدادات العملاء' : 'Save customer settings') }}</flux:button>
                    </div>
                @else
                    <flux:callout variant="warning" icon="lock-closed">{{ app()->isLocale('ar-EG') ? 'لديك صلاحية عرض الإعدادات بس.' : ($arabic ? 'لديك صلاحية عرض الإعدادات فقط.' : 'You have view-only access to these settings.') }}</flux:callout>
                @endcan
                @if ($customer['version'] > 0)<p class="text-xs text-text-muted">{{ $arabic ? 'آخر إصدار محفوظ' : 'Latest saved version' }}: {{ $customer['version'] }}</p>@endif
            </form>
        </section>

        <section x-show="tab === 'loyalty'" x-cloak role="tabpanel" class="space-y-5">
            <form method="POST" action="{{ route('admin.settings.customer-loyalty.save') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="section" value="loyalty">

                <section class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
                    <div class="border-b border-border pb-4">
                        <h2 class="text-lg font-semibold text-text-primary">{{ $arabic ? 'قاعدة نقاط البيع' : 'Retail points rule' }}</h2>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ $arabic ? 'حدد كسب النقاط وقيمتها عند الاستبدال للشراء بالتجزئة.' : 'Set earning and redemption values for retail purchases.' }}</p>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <flux:input name="earn_points_per_currency" inputmode="decimal" :label="$arabic ? 'النقاط المكتسبة لكل جنيه' : 'Points earned per currency unit'" :value="old('earn_points_per_currency', $loyalty['earn_points_per_currency'])" required />
                        <flux:input name="redeem_currency_per_point" inputmode="decimal" :label="$arabic ? 'قيمة النقطة عند الاستبدال بالجنيه' : 'Currency value per redeemed point'" :value="old('redeem_currency_per_point', $loyalty['redeem_currency_per_point'])" required />
                        <flux:input name="expiry_days" type="number" min="1" max="36500" :label="$arabic ? 'انتهاء النقاط بعد عدد أيام' : 'Points expire after days'" :value="old('expiry_days', $loyalty['expiry_days'])" required />
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-surface-muted/40 p-5 text-sm sm:p-6">
                    <h2 class="font-semibold text-text-primary">{{ $arabic ? 'حماية تلقائية للنقاط' : 'Automatic loyalty safeguards' }}</h2>
                    <ul class="mt-3 space-y-2 leading-6 text-text-muted">
                        <li>{{ $arabic ? 'تُقرب النقاط لأسفل دائمًا، فلا تظهر كسور في رصيد العميل.' : 'Points are always rounded down, so customer balances contain no fractions.' }}</li>
                        <li>{{ app()->isLocale('ar-EG') ? 'أي تعديل يدوي على النقاط يحتاج موافقة منفصلة.' : ($arabic ? 'أي تعديل يدوي على النقاط يحتاج موافقة منفصلة.' : 'Every manual points adjustment requires separate approval.') }}</li>
                        <li>{{ app()->isLocale('ar-EG') ? 'سجل النقاط محمي من التكرار والتعديل المباشر.' : ($arabic ? 'سجل النقاط محمي من التكرار والتعديل المباشر.' : 'The loyalty ledger is protected from duplicates and direct edits.') }}</li>
                    </ul>
                </section>

                @can('company_settings.edit')
                    <div class="flex flex-wrap items-end justify-between gap-4 border-t border-border pt-5">
                        <div class="max-w-lg flex-1"><flux:input name="notes" :label="$arabic ? 'ملاحظة داخلية (اختياري)' : 'Internal note (optional)'" :value="old('section') === 'loyalty' ? old('notes') : null" maxlength="1000" /></div>
                        <flux:button type="submit" variant="primary" icon="check">{{ app()->isLocale('ar-EG') ? 'احفظ إعدادات الولاء' : ($arabic ? 'حفظ إعدادات الولاء' : 'Save loyalty settings') }}</flux:button>
                    </div>
                @else
                    <flux:callout variant="warning" icon="lock-closed">{{ app()->isLocale('ar-EG') ? 'لديك صلاحية عرض الإعدادات بس.' : ($arabic ? 'لديك صلاحية عرض الإعدادات فقط.' : 'You have view-only access to these settings.') }}</flux:callout>
                @endcan
                @if ($loyalty['version'] > 0)<p class="text-xs text-text-muted">{{ $arabic ? 'آخر إصدار محفوظ' : 'Latest saved version' }}: {{ $loyalty['version'] }}</p>@endif
            </form>
        </section>
    </div>
</x-layouts::app>
