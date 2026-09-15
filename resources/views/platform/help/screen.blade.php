@php
    $locale = app()->getLocale();
    $isArabic = str_starts_with($locale, 'ar');
    $text = fn ($value): string => is_array($value) ? ($value[$isArabic ? 'ar' : 'en'] ?? $value['ar'] ?? $value['en'] ?? '') : (string) $value;
    $title = $text($context['title']);
    $sections = $context['sections'];
    $relatedFlows = $context['related_flows'] ?? [];
@endphp

<x-layouts::app :title="$title">
    <x-app.page class="guide-page" max-width="6xl" aria-labelledby="guide-page-title">
        <div class="guide-page__topbar">
            <a class="guide-page__back" href="{{ url()->previous() }}">
                <span aria-hidden="true">{{ $isArabic ? '→' : '←' }}</span>
                {{ $isArabic ? 'العودة إلى الشاشة' : 'Back to screen' }}
            </a>
            <div class="guide-page__topbar-actions">
                <span class="guide-page__kicker">{{ $isArabic ? 'مركز المساعدة' : 'Help center' }}</span>
                <button type="button" class="guide-page__print" onclick="window.print()">
                    <span aria-hidden="true">↗</span>
                    {{ app()->isLocale('ar-EG') ? 'اطبع الدليل' : ($isArabic ? 'طباعة الدليل' : 'Print guide') }}
                </button>
            </div>
        </div>

        <section class="guide-page__hero">
            <div class="guide-page__hero-glow" aria-hidden="true"></div>
            <div class="guide-page__hero-copy">
                <div class="guide-page__eyebrow-row">
                    <span class="guide-page__screen-id">{{ $isArabic ? 'دليل تشغيلي' : 'Operational guide' }}</span>
                    <span class="guide-page__published"><span aria-hidden="true">●</span> {{ $isArabic ? 'دليل منشور' : 'Published guide' }}</span>
                </div>
                <h1 id="guide-page-title">{{ $title }}</h1>
                <p>{{ $text($context['purpose']) }}</p>
                <div class="guide-page__hero-actions">
                    <a class="guide-page__hero-primary" href="#guide-steps">{{ $isArabic ? 'ابدأ بالخطوات' : 'Start with the steps' }} <span aria-hidden="true">{{ $isArabic ? '←' : '→' }}</span></a>
                    @if($relatedFlows)
                        <a class="guide-page__hero-secondary" href="#guide-flows">{{ app()->isLocale('ar-EG') ? 'استعرض التدفقات' : ($isArabic ? 'استعرض التدفقات' : 'Review workflows') }}</a>
                    @endif
                </div>
            </div>
            <div class="guide-page__hero-meta">
                <span>{{ $isArabic ? 'آخر تحديث' : 'Updated' }}</span>
                <strong>{{ $context['updated_at'] }}</strong>
                <small>{{ $isArabic ? 'الإصدار' : 'Version' }} {{ $context['version'] }}</small>
            </div>
        </section>

        @if (!empty($context['module']))
            <section class="guide-module-intro" aria-labelledby="guide-module-title">
                <div class="guide-module-intro__icon" aria-hidden="true">◎</div>
                <div>
                    <p class="guide-card__eyebrow">{{ $isArabic ? 'عن الوحدة' : 'ABOUT THIS MODULE' }}</p>
                    <h2 id="guide-module-title">{{ $text($context['module']['title']) }}</h2>
                    <p>{{ $text($context['module']['description']) }}</p>
                </div>
            </section>
        @endif

        <section class="guide-page__quick-grid" aria-label="{{ $isArabic ? 'ملخص سريع' : 'Quick summary' }}">
            <article class="guide-quick-card guide-quick-card--accent">
                <span class="guide-quick-card__icon" aria-hidden="true">◎</span>
                <div><p>{{ $isArabic ? 'متى تستخدمها' : 'When to use it' }}</p><strong>{{ $text($context['when_to_use']) }}</strong></div>
            </article>
            <article class="guide-quick-card">
                <span class="guide-quick-card__icon" aria-hidden="true">✓</span>
                <div><p>{{ $isArabic ? 'الإجراءات المتاحة' : 'Available actions' }}</p><strong>{{ count($context['approved_actions']) }} {{ $isArabic ? 'إجراء مصرح' : 'approved actions' }}</strong></div>
            </article>
            <article class="guide-quick-card">
                <span class="guide-quick-card__icon" aria-hidden="true">01</span>
                <div><p>{{ $isArabic ? 'مسار التعلم' : 'Learning path' }}</p><strong>{{ count($context['sections']['steps']) }} {{ $isArabic ? 'خطوات إرشادية' : 'guided steps' }}</strong></div>
            </article>
            <article class="guide-quick-card">
                <span class="guide-quick-card__icon" aria-hidden="true">↗</span>
                <div><p>{{ $isArabic ? 'التغطية' : 'Coverage' }}</p><strong>{{ count($context['stories']) + count($context['acceptance_criteria']) }} {{ $isArabic ? 'نقاط موثقة' : 'documented points' }}</strong></div>
            </article>
        </section>

        <div class="guide-page__layout">
            <div class="guide-page__content">
                <section id="guide-overview" class="guide-card guide-card--intro">
                    <div class="guide-card__heading"><span class="guide-card__icon">◎</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'ابدأ من هنا' : 'START HERE' }}</p><h2>{{ app()->isLocale('ar-EG') ? 'هتتعلم إيه؟' : ($isArabic ? 'ما الذي ستتعلمه؟' : 'What will you learn?') }}</h2></div></div>
                    <p>{{ $text($context['purpose']) }}</p>
                    <div class="guide-overview-grid">
                        <div><span class="guide-label">{{ $isArabic ? 'الغرض' : 'Purpose' }}</span><p>{{ $text($context['purpose']) }}</p></div>
                        <div><span class="guide-label">{{ $isArabic ? 'الاستخدام الصحيح' : 'Correct use' }}</span><p>{{ $text($context['when_to_use']) }}</p></div>
                    </div>
                </section>

                <section id="guide-actions" class="guide-card">
                    <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--blue">✓</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'نطاقك الحالي' : 'YOUR CURRENT SCOPE' }}</p><h2>{{ $isArabic ? 'ما يمكنك فعله هنا' : 'What you can do here' }}</h2></div></div>
                    <p class="guide-section-intro">{{ app()->isLocale('ar-EG') ? 'تظهر هنا الإجراءات المتاحة لدورك الحالي بس. ظهور الدليل لا يعني أن كل إجراء متاح للتنفيذ.' : ($isArabic ? 'تظهر هنا الإجراءات المتاحة لدورك الحالي فقط. ظهور الدليل لا يعني أن كل إجراء متاح للتنفيذ.' : 'Only actions available to your current role appear here. Seeing the guide does not grant additional access.') }}</p>
                    <div class="guide-action-grid">
                        @forelse($context['approved_actions'] as $action)
                            <div class="guide-action-card"><span class="guide-action-card__check" aria-hidden="true">✓</span><strong>{{ $text($action['label']) }}</strong><p>{{ $isArabic ? 'متاح ضمن نطاق الصلاحيات الحالي.' : 'Available within your current access scope.' }}</p></div>
                        @empty
                            <div class="guide-empty-state">{{ app()->isLocale('ar-EG') ? 'مفيش إجراءات متاحة للدور ده على الشاشة دي.' : ($isArabic ? 'لا توجد إجراءات متاحة لهذا الدور على هذه الشاشة.' : 'No actions are available for this role on this screen.') }}</div>
                        @endforelse
                    </div>
                </section>

                <section id="guide-steps" class="guide-card">
                    <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--blue">01</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'التنفيذ' : 'EXECUTION' }}</p><h2>{{ $isArabic ? 'خطوات الاستخدام' : 'Step-by-step usage' }}</h2></div></div>
                    <ol class="guide-steps">
                        @foreach($sections['steps'] as $step)
                            <li><span class="guide-steps__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $text($step['title']) }}</h3><p>{{ $text($step['body']) }}</p></div></li>
                        @endforeach
                    </ol>
                </section>

                <section id="guide-fields" class="guide-card">
                    <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--purple">02</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'التفاصيل' : 'DETAILS' }}</p><h2>{{ $isArabic ? 'شرح الحقول والعناصر' : 'Fields and interface elements' }}</h2></div></div>
                    <div class="guide-fields">
                        @forelse($sections['fields'] as $field)
                            <div class="guide-field"><strong>{{ $text($field['title']) }}</strong><p>{{ $text($field['body']) }}</p></div>
                        @empty
                            <div class="guide-empty-state">{{ app()->isLocale('ar-EG') ? 'مفيش حقول خاصة بالشاشة دي.' : ($isArabic ? 'لا توجد حقول خاصة بهذه الشاشة.' : 'This screen has no additional field notes.') }}</div>
                        @endforelse
                    </div>
                </section>

                <div class="guide-page__split">
                    <section id="guide-notes" class="guide-card guide-card--note"><p class="guide-card__eyebrow">{{ $isArabic ? 'معلومة تشغيلية' : 'OPERATING NOTE' }}</p><h2>{{ $isArabic ? 'اعمل ضمن نطاقك' : 'Work within your scope' }}</h2><p>{{ $text($sections['notes']) }}</p></section>
                    <section id="guide-warnings" class="guide-card guide-card--warning"><p class="guide-card__eyebrow">{{ app()->isLocale('ar-EG') ? 'قبل ما تكمل' : ($isArabic ? 'قبل المتابعة' : 'BEFORE YOU CONTINUE') }}</p><h2>{{ $isArabic ? 'تنبيهات مهمة' : 'Important warnings' }}</h2><p>{{ $text($sections['warnings']) }}</p></section>
                </div>

                <section id="guide-errors" class="guide-card guide-card--errors">
                    <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--danger">!</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'عند حدوث مشكلة' : 'WHEN SOMETHING GOES WRONG' }}</p><h2>{{ $isArabic ? 'الأخطاء وكيف تتعامل معها' : 'Errors and recovery' }}</h2></div></div>
                    <p>{{ $text($sections['errors']) }}</p>
                    <div class="guide-recovery-list"><span>1</span><p>{{ $isArabic ? 'اقرأ رسالة التحقق كاملة ولا تعاود الإرسال قبل تصحيح السبب.' : 'Read the full validation message and correct the cause before retrying.' }}</p><span>2</span><p>{{ app()->isLocale('ar-EG') ? 'لو كان الإجراء ممنوعًا، ارجع إلى مسؤول الصلاحيات بدل محاولة تجاوزه.' : ($isArabic ? 'إذا كان الإجراء ممنوعًا، ارجع إلى مسؤول الصلاحيات بدل محاولة تجاوزه.' : 'If an action is denied, contact an authorized administrator instead of bypassing it.') }}</p></div>
                </section>

                <section id="guide-faq" class="guide-card">
                    <div class="guide-card__heading"><span class="guide-card__icon">?</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'مساعدة سريعة' : 'QUICK HELP' }}</p><h2>{{ $isArabic ? 'أسئلة شائعة' : 'Frequently asked questions' }}</h2></div></div>
                    <details class="guide-faq" open><summary>{{ $isArabic ? 'هل أستطيع تنفيذ كل ما يظهر في الدليل؟' : 'Can I perform everything shown in the guide?' }}</summary><p>{{ app()->isLocale('ar-EG') ? 'لا. يعرض الدليل الإجراءات المصرح بها لدورك الحالي بس، وقد تمنع حالة السجل أو قواعد المجال تنفيذ بعضها.' : ($isArabic ? 'لا. يعرض الدليل الإجراءات المصرح بها لدورك الحالي فقط، وقد تمنع حالة السجل أو قواعد المجال تنفيذ بعضها.' : 'No. The guide reflects actions approved for your current role, while record state and domain rules may still prevent an action.') }}</p></details>
                    <details class="guide-faq"><summary>{{ app()->isLocale('ar-EG') ? 'أعمل إيه لو ملقتش عنصر في الجولة؟' : ($isArabic ? 'ماذا أفعل إذا لم أجد عنصرًا في الجولة؟' : 'What if a tour element is missing?') }}</summary><p>{{ app()->isLocale('ar-EG') ? 'قد يكون العنصر مش متاح بسبب الدور أو حالة الشاشة أو التصفية. تستمر الجولة بأمان، ويمكنك استخدام خطوات الدليل المكتوبة.' : ($isArabic ? 'قد يكون العنصر غير متاح بسبب الدور أو حالة الشاشة أو التصفية. تستمر الجولة بأمان، ويمكنك استخدام خطوات الدليل المكتوبة.' : 'The element may be unavailable because of role, page state, or filtering. The tour safely continues; use the written steps as the source of guidance.') }}</p></details>
                    <details class="guide-faq"><summary>{{ $isArabic ? 'متى أستخدم الدليل الكامل بدل الجولة؟' : 'When should I use the full guide instead of the tour?' }}</summary><p>{{ $isArabic ? 'استخدم الدليل الكامل لفهم الغرض والقيود والأخطاء والحقول قبل تنفيذ مهمة، واستخدم الجولة للتعرف السريع على أماكن العناصر.' : 'Use the full guide to understand purpose, limits, errors, and fields before acting; use the tour for a quick orientation to element locations.' }}</p></details>
                </section>

                @if($relatedFlows)
                    <section id="guide-flows" class="guide-card">
                        <div class="guide-card__heading"><span class="guide-card__icon guide-card__icon--purple">↗</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'العمل المتصل' : 'CONNECTED WORK' }}</p><h2>{{ $isArabic ? 'تدفقات المستخدم المرتبطة' : 'Related user workflows' }}</h2></div></div>
                        <div class="guide-flow-list">
                            @foreach($relatedFlows as $flow)
                                <a class="guide-flow-card" href="{{ route('platform.help.flow', $flow['flow_id']) }}"><span><strong>{{ $text($flow['title']) }}</strong><small>{{ $text($flow['actor']) }}</small></span><span aria-hidden="true">{{ $isArabic ? '←' : '→' }}</span></a>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section id="guide-next" class="guide-card guide-card--next">
                    <div><p class="guide-card__eyebrow">{{ $isArabic ? 'الخطوة التالية' : 'NEXT STEP' }}</p><h2>{{ $text($sections['next_step']) }}</h2><p>{{ $text($sections['faq']) }}</p></div>
                    <a class="guide-page__flow-link" href="{{ url()->previous() }}">{{ $isArabic ? 'العودة للتنفيذ' : 'Return to the task' }} <span aria-hidden="true">{{ $isArabic ? '←' : '→' }}</span></a>
                </section>
            </div>

            <aside class="guide-page__aside" aria-label="{{ $isArabic ? 'ملخص الدليل' : 'Guide summary' }}">
                <section class="guide-summary-card guide-summary-card--toc">
                    <p class="guide-card__eyebrow">{{ app()->isLocale('ar-EG') ? 'في الدليل ده' : ($isArabic ? 'في هذا الدليل' : 'IN THIS GUIDE') }}</p>
                    <nav aria-label="{{ $isArabic ? 'أقسام الدليل' : 'Guide sections' }}"><a href="#guide-overview">{{ $isArabic ? 'نظرة عامة' : 'Overview' }}</a><a href="#guide-actions">{{ $isArabic ? 'الإجراءات المتاحة' : 'Available actions' }}</a><a href="#guide-steps">{{ $isArabic ? 'الخطوات' : 'Steps' }}</a><a href="#guide-fields">{{ $isArabic ? 'الحقول والعناصر' : 'Fields and elements' }}</a><a href="#guide-errors">{{ $isArabic ? 'الأخطاء والتعافي' : 'Errors and recovery' }}</a><a href="#guide-faq">FAQ</a>@if($relatedFlows)<a href="#guide-flows">{{ $isArabic ? 'التدفقات المرتبطة' : 'Related workflows' }}</a>@endif</nav>
                </section>
                <section class="guide-summary-card guide-summary-card--tour"><span class="guide-summary-card__spark" aria-hidden="true">✦</span><div><p class="guide-card__eyebrow">{{ $isArabic ? 'تعلم تفاعلي' : 'INTERACTIVE LEARNING' }}</p><h2>{{ $isArabic ? 'تحتاج إلى جولة؟' : 'Need a walkthrough?' }}</h2><p>{{ $isArabic ? 'ارجع إلى الشاشة وابدأ الجولة للتعرف على العناصر المرئية خطوة بخطوة.' : 'Return to the screen and start the tour to learn visible elements step by step.' }}</p></div></section>
                <section class="guide-summary-card guide-summary-card--trust"><p class="guide-card__eyebrow">{{ $isArabic ? 'نطاق الدليل' : 'GUIDE SCOPE' }}</p><p>{{ app()->isLocale('ar-EG') ? 'المحتوى إرشادي بس. الصلاحيات وقواعد المجال وحالة السجل هي المرجع النهائي قبل أي إجراء.' : ($isArabic ? 'المحتوى إرشادي فقط. الصلاحيات وقواعد المجال وحالة السجل هي المرجع النهائي قبل أي إجراء.' : 'This content is guidance only. Permissions, domain rules, and record state remain authoritative before any action.') }}</p></section>
            </aside>
        </div>
    </x-app.page>
</x-layouts::app>
