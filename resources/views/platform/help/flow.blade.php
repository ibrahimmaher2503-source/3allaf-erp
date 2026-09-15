@php($locale = app()->getLocale())
@php($text = fn ($value): string => is_array($value) ? ($value[str_starts_with($locale, 'ar') ? 'ar' : 'en'] ?? $value['ar'] ?? $value['en'] ?? '') : (string) $value)
@php($guideLinks = $guideLinks ?? [])

<x-layouts::app :title="$text($flow['title'])">
    <x-app.page
        :title="$text($flow['title'])"
        :description="$text($flow['actor'])"
        :eyebrow="str_starts_with($locale, 'ar') ? 'مسار تشغيلي' : 'Operational workflow'"
        max-width="4xl"
    >
        <section class="rounded-xl border border-border bg-surface p-5 shadow-card">
            <flux:heading size="lg">{{ str_starts_with($locale, 'ar') ? 'الشروط المسبقة' : 'Preconditions' }}</flux:heading>
            <p class="mt-3 text-sm text-text-muted">{{ $text($flow['preconditions']) }}</p>
        </section>
        <section class="rounded-xl border border-border bg-surface p-5 shadow-card">
            <flux:heading size="lg">{{ str_starts_with($locale, 'ar') ? 'الخطوات' : 'Steps' }}</flux:heading>
            <ol class="mt-4 space-y-4">
                @foreach($flow['steps'] as $step)
                    <li class="flex gap-3">
                        <span class="size-7 shrink-0 rounded-full bg-primary-soft text-center leading-7 text-primary">{{ $step['number'] }}</span>
                        <p class="text-sm">{{ $text($step['body']) }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
        @if($guideLinks)
            <section class="border-y border-border py-5" aria-labelledby="flow-guides-title">
                <flux:heading id="flow-guides-title" size="lg">{{ str_starts_with($locale, 'ar') ? 'الأدلة المتاحة ضمن نطاقك' : 'Guides available in your scope' }}</flux:heading>
                <p class="mt-2 text-sm text-text-muted">{{ str_starts_with($locale, 'ar') ? 'تفتح الروابط أدلة مرجعية محمية. لا تنفذ إجراءً ولا تمنح صلاحيات إضافية.' : 'These links open protected reference guides. They perform no action and grant no additional access.' }}</p>
                <ul class="mt-4 divide-y divide-border border-y border-border">
                    @foreach($guideLinks as $guideLink)
                        <li>
                            <a href="{{ $guideLink['href'] }}" class="flex items-center justify-between gap-4 py-3 text-start transition hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                <span>
                                    <strong class="block text-sm">{{ $text($guideLink['title']) }}</strong>
                                    <span class="mt-1 block text-xs text-text-muted"><code>{{ $guideLink['screen_id'] }}</code> · <code>{{ $guideLink['route_name'] }}</code> · {{ $guideLink['is_readiness'] ? (str_starts_with($locale, 'ar') ? 'مرجع جاهزية فقط' : 'readiness reference only') : (str_starts_with($locale, 'ar') ? 'دليل تشغيلي' : 'operational guide') }}</span>
                                </span>
                                <span aria-hidden="true">{{ str_starts_with($locale, 'ar') ? '←' : '→' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        <div class="grid gap-5 md:grid-cols-2">
            <section class="rounded-xl border border-border bg-surface p-5">
                <flux:heading size="lg">{{ str_starts_with($locale, 'ar') ? 'المسارات البديلة' : 'Alternate paths' }}</flux:heading>
                <p class="mt-3 text-sm text-text-muted">{{ $text($flow['alternate_paths']) }}</p>
            </section>
            <section class="rounded-xl border border-border bg-surface p-5">
                <flux:heading size="lg">{{ str_starts_with($locale, 'ar') ? 'مسارات الفشل' : 'Failure paths' }}</flux:heading>
                <p class="mt-3 text-sm text-text-muted">{{ $text($flow['failure_paths']) }}</p>
            </section>
        </div>
        <a class="platform-assistant__secondary" href="{{ url()->previous() }}">{{ str_starts_with($locale, 'ar') ? 'العودة' : 'Back' }}</a>
    </x-app.page>
</x-layouts::app>
