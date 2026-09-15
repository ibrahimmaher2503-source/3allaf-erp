<?php

return [
    'route_names' => [
        0 => 'system.health',
    ],
    'title' => [
        'ar' => 'صحة النظام',
        'en' => 'System Health',
    ],
    'purpose' => [
        'ar' => 'تعرض فحوصات صحية محلية آمنة دون أسرار.',
        'en' => 'Shows safe local health checks without secrets.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'audit_logs.view',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'audit_logs.view',
            'label' => [
                'ar' => 'عرض سجلات التدقيق وصحة النظام',
                'en' => 'View audit logs and system health',
            ],
            'required_permission' => 'audit_logs.view',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-SYS-02',
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
                'selector' => '[data-guide="health-header"]',
                'title' => [
                    'ar' => 'رأس مراقبة صحة النظام',
                    'en' => 'System Health Header',
                ],
                'body' => [
                    'ar' => 'استعراض المؤشرات الصحية المباشرة للبيئة المحلية ومعرف الطلب.',
                    'en' => 'Monitor local system readiness status, PHP/Laravel versions, and request ID.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="health-refresh-action"]',
                'title' => [
                    'ar' => 'زر تحديث حالة الجاهزية',
                    'en' => 'Refresh Health Status Action',
                ],
                'body' => [
                    'ar' => 'إعادة الفحص الآمن لجميع المكونات التشغيلية وتحديث النتائج.',
                    'en' => 'Re-run system health checks to fetch current operational status.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="health-banner"]',
                'title' => [
                    'ar' => 'مؤشر الحالة العامة للنظام',
                    'en' => 'Overall Operational Health Banner',
                ],
                'body' => [
                    'ar' => 'تنبيه مباشر يوضح هل المنصة تعمل بكفاءة كاملة أم بها أي تعثر.',
                    'en' => 'Live callout indicating operational, degraded, or critical platform status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="health-grid"]',
                'title' => [
                    'ar' => 'بطاقات فحوصات المكونات الرئيسية',
                    'en' => 'Component Health Cards Grid',
                ],
                'body' => [
                    'ar' => 'فحص قاعدة بيانات MySQL عبر phpMyAdmin، ونظام الملفات، والذاكرة المؤقتة، والبيئة.',
                    'en' => 'Inspect Database, Storage, Cache, and Application environment statuses.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="health-table"]',
                'title' => [
                    'ar' => 'جدول البيانات التشغيلية الوصفية',
                    'en' => 'Platform Overview & Metadata Table',
                ],
                'body' => [
                    'ar' => 'مراجعة اسم التطبيق، اللغة المعتمدة، ومعرف التتبع الحالي.',
                    'en' => 'View platform metadata properties including app locale and check timestamp.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'اسم المكون والحالة',
                    'en' => 'Component Name & Status',
                ],
                'body' => [
                    'ar' => 'اسم فحص الجاهزية والحالة الحالية (سليم / متأثر / متوقف).',
                    'en' => 'Check identifier name and active health state (healthy / degraded / down).',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'رسالة التتقرير ومعرف التتبع',
                    'en' => 'Report Message & Trace ID',
                ],
                'body' => [
                    'ar' => 'تفاصيل الفحص الآمن ومعرف الارتباط المرجعي للتتبع.',
                    'en' => 'Safe diagnostic check message and correlation tracking reference.',
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
            'selector' => '[data-guide="health-header"]',
            'title' => [
                'ar' => 'رأس مراقبة صحة النظام',
                'en' => 'System Health Header',
            ],
            'body' => [
                'ar' => 'استعراض المؤشرات الصحية المباشرة للبيئة المحلية ومعرف الطلب.',
                'en' => 'Monitor local system readiness status, PHP/Laravel versions, and request ID.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="health-refresh-action"]',
            'title' => [
                'ar' => 'زر تحديث حالة الجاهزية',
                'en' => 'Refresh Health Status Action',
            ],
            'body' => [
                'ar' => 'إعادة الفحص الآمن لجميع المكونات التشغيلية وتحديث النتائج.',
                'en' => 'Re-run system health checks to fetch current operational status.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="health-banner"]',
            'title' => [
                'ar' => 'مؤشر الحالة العامة للنظام',
                'en' => 'Overall Operational Health Banner',
            ],
            'body' => [
                'ar' => 'تنبيه مباشر يوضح هل المنصة تعمل بكفاءة كاملة أم بها أي تعثر.',
                'en' => 'Live callout indicating operational, degraded, or critical platform status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="health-grid"]',
            'title' => [
                'ar' => 'بطاقات فحوصات المكونات الرئيسية',
                'en' => 'Component Health Cards Grid',
            ],
            'body' => [
                'ar' => 'فحص قاعدة بيانات MySQL عبر phpMyAdmin، ونظام الملفات، والذاكرة المؤقتة، والبيئة.',
                'en' => 'Inspect Database, Storage, Cache, and Application environment statuses.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="health-table"]',
            'title' => [
                'ar' => 'جدول البيانات التشغيلية الوصفية',
                'en' => 'Platform Overview & Metadata Table',
            ],
            'body' => [
                'ar' => 'مراجعة اسم التطبيق، اللغة المعتمدة، ومعرف التتبع الحالي.',
                'en' => 'View platform metadata properties including app locale and check timestamp.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-SYS-004',
];
