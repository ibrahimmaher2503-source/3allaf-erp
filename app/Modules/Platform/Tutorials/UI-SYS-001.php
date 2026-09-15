<?php

return [
    'route_names' => [
        0 => 'dashboard',
    ],
    'title' => [
        'ar' => 'لوحة التحكم',
        'en' => 'Dashboard',
    ],
    'purpose' => [
        'ar' => 'تعرض لوحة التحكم مؤشرات العمل والتنبيهات المسموح بها لنطاقك.',
        'en' => 'The dashboard presents the work indicators and alerts allowed for your scope.',
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
        0 => 'FLW-HELP-01',
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
                'selector' => '[data-guide="page-header"]',
                'title' => [
                    'ar' => 'رأس لوحة التحكم التشغيلية',
                    'en' => 'Workspace Operations Header',
                ],
                'body' => [
                    'ar' => 'استعرض اسم مساحة العمل ومؤشر حالة البنية التحتية الأساسية.',
                    'en' => 'Overview workspace title and system foundation progress indicators.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="dashboard-foundation-heading"]',
                'title' => [
                    'ar' => 'حالة الجاهزية الأساسية',
                    'en' => 'Foundation Readiness Status',
                ],
                'body' => [
                    'ar' => 'تابع حالة المكونات الأساسية المعتمدة في البيئة التشغيلية.',
                    'en' => 'Monitor the readiness status of verified building blocks in the system.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="dashboard-foundation-first-row"]',
                'title' => [
                    'ar' => 'قائمة المكونات النشطة',
                    'en' => 'Verified Core Building Blocks',
                ],
                'body' => [
                    'ar' => 'افحص الجاهزية للهيكل التنفيذي والمصادقة واتجاهات اللغة.',
                    'en' => 'Inspect app shell, authentication, and language direction status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="dashboard-setup-heading"]',
                'title' => [
                    'ar' => 'استكمال إعداد المنصة',
                    'en' => 'Continue Platform Setup',
                ],
                'body' => [
                    'ar' => 'قسم التوجيه إلى إعدادات الحساب ومراحل البنية التحتية القادمة.',
                    'en' => 'Section guiding to account settings and active milestone setups.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="dashboard-profile-action"]',
                'title' => [
                    'ar' => 'فتح إعدادات الحساب',
                    'en' => 'Open Account Settings',
                ],
                'body' => [
                    'ar' => 'زر الانتقال المباشر لإدارة الملف الشخصي والأمان والوثائق.',
                    'en' => 'Direct navigation button to manage user profile and security.',
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
            'selector' => '[data-guide="page-header"]',
            'title' => [
                'ar' => 'رأس لوحة التحكم التشغيلية',
                'en' => 'Workspace Operations Header',
            ],
            'body' => [
                'ar' => 'استعرض اسم مساحة العمل ومؤشر حالة البنية التحتية الأساسية.',
                'en' => 'Overview workspace title and system foundation progress indicators.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="dashboard-foundation-heading"]',
            'title' => [
                'ar' => 'حالة الجاهزية الأساسية',
                'en' => 'Foundation Readiness Status',
            ],
            'body' => [
                'ar' => 'تابع حالة المكونات الأساسية المعتمدة في البيئة التشغيلية.',
                'en' => 'Monitor the readiness status of verified building blocks in the system.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="dashboard-foundation-first-row"]',
            'title' => [
                'ar' => 'قائمة المكونات النشطة',
                'en' => 'Verified Core Building Blocks',
            ],
            'body' => [
                'ar' => 'افحص الجاهزية للهيكل التنفيذي والمصادقة واتجاهات اللغة.',
                'en' => 'Inspect app shell, authentication, and language direction status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="dashboard-setup-heading"]',
            'title' => [
                'ar' => 'استكمال إعداد المنصة',
                'en' => 'Continue Platform Setup',
            ],
            'body' => [
                'ar' => 'قسم التوجيه إلى إعدادات الحساب ومراحل البنية التحتية القادمة.',
                'en' => 'Section guiding to account settings and active milestone setups.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="dashboard-profile-action"]',
            'title' => [
                'ar' => 'فتح إعدادات الحساب',
                'en' => 'Open Account Settings',
            ],
            'body' => [
                'ar' => 'زر الانتقال المباشر لإدارة الملف الشخصي والأمان والوثائق.',
                'en' => 'Direct navigation button to manage user profile and security.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-SYS-001',
];
