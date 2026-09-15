<?php

return [
    'route_names' => [
        0 => 'system.app',
    ],
    'title' => [
        'ar' => 'تطبيق النظام',
        'en' => 'System App Shell',
    ],
    'purpose' => [
        'ar' => 'تراجع حالة قابلية التثبيت والتحديث الآمن.',
        'en' => 'Review installability and safe update status.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'dashboard_reports.view',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'dashboard_reports.view',
            'label' => [
                'ar' => 'عرض مؤشرات لوحة التحكم والتقارير',
                'en' => 'View dashboard indicators and reports',
            ],
            'required_permission' => 'dashboard_reports.view',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-SYS-03',
    ],
    'acceptance_criteria' => [
        0 => 'AC-UI-08',
        1 => 'AC-UI-09',
        2 => 'AC-UI-12',
    ],
    'sections' => [
        'steps' => [
            0 => [
                'key' => 'step-1',
                'selector' => '[data-guide="system-app-header"]',
                'title' => [
                    'ar' => 'رأس تطبيق النظام وشاشة الجاهزية',
                    'en' => 'PWA Shell & Status Header',
                ],
                'body' => [
                    'ar' => 'استعراض حالة تطبيق النظام PWA ومؤشر الاتصال الحي بالشبكة.',
                    'en' => 'Overview PWA app shell status and live network online/offline badge.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="system-app-connectivity"]',
                'title' => [
                    'ar' => 'بطاقة حالة الاتصال بالشبكة',
                    'en' => 'Connectivity Status Card',
                ],
                'body' => [
                    'ar' => 'متابعة مؤشرات الاتصال بالشبكة وفق المعايير القياسية للمتصفح.',
                    'en' => 'Monitor browser-standard online and offline network status.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="system-app-cache"]',
                'title' => [
                    'ar' => 'بطاقة سياسة التخزين المؤقت المحمي',
                    'en' => 'Cache Policy Security Card',
                ],
                'body' => [
                    'ar' => 'التأكد من عدم تخزين أي استجابات حساسة أو محمية بالصلاحيات أوفلاين.',
                    'en' => 'Verify no sensitive or authenticated data is cached offline.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="system-app-installable"]',
                'title' => [
                    'ar' => 'بطاقة جاهزية القالب القابل للتثبيت',
                    'en' => 'Installable PWA Shell Card',
                ],
                'body' => [
                    'ar' => 'مراجعة ملف البيانات الوصفية ومُصادق الخدمة الساكن لتسريع التصفح.',
                    'en' => 'Review PWA manifest and static service worker for fast navigation.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="system-app-locale"]',
                'title' => [
                    'ar' => 'بطاقة اللغة واتجاه واجهة المستخدم',
                    'en' => 'Current Locale & Direction Card',
                ],
                'body' => [
                    'ar' => 'مراجعة كود اللغة الحالي واتجاه المستند المعين (RTL أو LTR).',
                    'en' => 'Inspect active application locale code and layout direction (RTL/LTR).',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'حقول النظرة العامة',
                    'en' => 'Overview Fields',
                ],
                'body' => [
                    'ar' => 'تعرض ملخصات حالة النظام والمؤشرات المتاحة لنطاقك الحالي.',
                    'en' => 'Displays system status summaries and indicators available for your current scope.',
                ],
            ],
        ],
        'notes' => [
            'ar' => 'اعتمد فقط ما يظهر ضمن نطاقك الحالي. لا تعرض أو تدخل بيانات خارج المهمة الحالية.',
            'en' => 'Use only what is visible within your current scope. Do not expose or enter data outside the current task.',
        ],
        'warnings' => [
            'ar' => 'الإجراءات غير المتاحة تبقى محكومة بالخادم والصلاحيات.',
            'en' => 'Unavailable actions remain enforced by the server and permissions.',
        ],
        'errors' => [
            'ar' => 'راجع رسالة التحقق الظاهرة وأصلح الحقل المطلوب قبل المتابعة.',
            'en' => 'Review the validation message and fix the required field before continuing.',
        ],
        'next_step' => [
            'ar' => 'انتقل إلى الخطوة التالية في دليل التدفق المرتبط.',
            'en' => 'Continue with the next step in the related flow.',
        ],
        'faq' => [
            'ar' => 'لا يوجد سؤال شائع منشور إضافي لهذه الشاشة.',
            'en' => 'No additional published FAQ is available for this screen.',
        ],
    ],
    'tour_steps' => [
        0 => [
            'key' => 'step-1',
            'selector' => '[data-guide="system-app-header"]',
            'title' => [
                'ar' => 'رأس تطبيق النظام وشاشة الجاهزية',
                'en' => 'PWA Shell & Status Header',
            ],
            'body' => [
                'ar' => 'استعراض حالة تطبيق النظام PWA ومؤشر الاتصال الحي بالشبكة.',
                'en' => 'Overview PWA app shell status and live network online/offline badge.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="system-app-connectivity"]',
            'title' => [
                'ar' => 'بطاقة حالة الاتصال بالشبكة',
                'en' => 'Connectivity Status Card',
            ],
            'body' => [
                'ar' => 'متابعة مؤشرات الاتصال بالشبكة وفق المعايير القياسية للمتصفح.',
                'en' => 'Monitor browser-standard online and offline network status.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="system-app-cache"]',
            'title' => [
                'ar' => 'بطاقة سياسة التخزين المؤقت المحمي',
                'en' => 'Cache Policy Security Card',
            ],
            'body' => [
                'ar' => 'التأكد من عدم تخزين أي استجابات حساسة أو محمية بالصلاحيات أوفلاين.',
                'en' => 'Verify no sensitive or authenticated data is cached offline.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="system-app-installable"]',
            'title' => [
                'ar' => 'بطاقة جاهزية القالب القابل للتثبيت',
                'en' => 'Installable PWA Shell Card',
            ],
            'body' => [
                'ar' => 'مراجعة ملف البيانات الوصفية ومُصادق الخدمة الساكن لتسريع التصفح.',
                'en' => 'Review PWA manifest and static service worker for fast navigation.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="system-app-locale"]',
            'title' => [
                'ar' => 'بطاقة اللغة واتجاه واجهة المستخدم',
                'en' => 'Current Locale & Direction Card',
            ],
            'body' => [
                'ar' => 'مراجعة كود اللغة الحالي واتجاه المستند المعين (RTL أو LTR).',
                'en' => 'Inspect active application locale code and layout direction (RTL/LTR).',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-SYS-002',
];
