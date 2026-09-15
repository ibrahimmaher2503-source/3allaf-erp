<?php

return [
    'route_names' => [
        0 => 'admin.branches',
    ],
    'title' => [
        'ar' => 'الفروع',
        'en' => 'Branches',
    ],
    'purpose' => [
        'ar' => 'تدير قائمة الفروع وحالتها.',
        'en' => 'Manage the branch list and status.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'branches_stores.view',
        1 => 'branches_stores.create',
        2 => 'branches_stores.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'branches_stores.view',
            'label' => [
                'ar' => 'عرض قائمة الفروع',
                'en' => 'View branch list',
            ],
            'required_permission' => 'branches_stores.view',
        ],
        1 => [
            'key' => 'branches_stores.create',
            'label' => [
                'ar' => 'إضافة فرع جديد',
                'en' => 'Create new branch',
            ],
            'required_permission' => 'branches_stores.create',
        ],
        2 => [
            'key' => 'branches_stores.edit',
            'label' => [
                'ar' => 'تعديل بيانات الفرع',
                'en' => 'Edit branch details',
            ],
            'required_permission' => 'branches_stores.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-ADM-01',
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
                'selector' => '[data-guide="branches-header"]',
                'title' => [
                    'ar' => 'رأس إدارة الفروع التجارية',
                    'en' => 'Branch Masters Header',
                ],
                'body' => [
                    'ar' => 'استعراض وإدارة مواقع الفروع التجارية وتعيينات متاجر نقاط البيع.',
                    'en' => 'Manage commercial branch locations and POS selling store assignments.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="branches-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة فرع جديد',
                    'en' => 'Add Branch Action Button',
                ],
                'body' => [
                    'ar' => 'فتح نموذج إنشاء فرع جديد مخصص لنطاق الشركة.',
                    'en' => 'Open modal form to create a new branch with unique code and bilingual names.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="branches-filters"]',
                'title' => [
                    'ar' => 'شريط البحث والتصفية',
                    'en' => 'Search & Status Filters',
                ],
                'body' => [
                    'ar' => 'البحث المباشر بكود أو اسم الفرع والتصفية حسب الحالة التشغيلية.',
                    'en' => 'Filter branches by active status or search by code and bilingual name.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="branches-table"], [data-guide="branches-empty"]',
                'title' => [
                    'ar' => 'جدول الفروع المعتمدة والربط',
                    'en' => 'Branch Directory Table & POS Mapping',
                ],
                'body' => [
                    'ar' => 'استعراض أكواد الفروع وأسمائها وحالتها والإجراءات المتاحة.',
                    'en' => 'Inspect branch codes, names, status, and available actions.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="branches-pagination"], [data-guide="branches-filters"]',
                'title' => [
                    'ar' => 'التنقل بين صفحات الفروع',
                    'en' => 'Branch List Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل بين الصفحات لاستعراض بقية سجلات الفروع دون بطء.',
                    'en' => 'Navigate between pages of registered branches efficiently.',
                ],
            ],
            5 => [
                'key' => 'bulk-operations',
                'selector' => '[role="region"][aria-label="Bulk operations"]',
                'title' => [
                    'ar' => 'العمليات الجماعية الآمنة',
                    'en' => 'Safe Bulk Operations',
                ],
                'body' => [
                    'ar' => 'يوضح شريط العمليات الجماعية أن التحديد يخص الصفحة الحالية، ويمكن تغيير حالة الفروع المحددة بعد التأكيد.',
                    'en' => 'The bulk toolbar selects the current page only; selected branches can have their status changed after confirmation.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود الفرع والاسم',
                    'en' => 'Branch Code & Name',
                ],
                'body' => [
                    'ar' => 'الكود الفريد للفرع والاسم ثنائي اللغة (عربي/إنجليزي) المعروض في المستندات.',
                    'en' => 'Unique branch code and bilingual names displayed on official documents.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'بيانات الاتصال والحالة',
                    'en' => 'Contact details and status',
                ],
                'body' => [
                    'ar' => 'توضح بيانات اتصال الفرع وحالة تفعيله.',
                    'en' => 'Shows branch contact details and active status.',
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
            'selector' => '[data-guide="branches-header"]',
            'title' => [
                'ar' => 'رأس إدارة الفروع التجارية',
                'en' => 'Branch Masters Header',
            ],
            'body' => [
                'ar' => 'استعراض وإدارة مواقع الفروع التجارية وتعيينات متاجر نقاط البيع.',
                'en' => 'Manage commercial branch locations and POS selling store assignments.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="branches-add-action"]',
            'title' => [
                'ar' => 'زر إضافة فرع جديد',
                'en' => 'Add Branch Action Button',
            ],
            'body' => [
                'ar' => 'فتح نموذج إنشاء فرع جديد مخصص لنطاق الشركة.',
                'en' => 'Open modal form to create a new branch with unique code and bilingual names.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="branches-filters"]',
            'title' => [
                'ar' => 'شريط البحث والتصفية',
                'en' => 'Search & Status Filters',
            ],
            'body' => [
                'ar' => 'البحث المباشر بكود أو اسم الفرع والتصفية حسب الحالة التشغيلية.',
                'en' => 'Filter branches by active status or search by code and bilingual name.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="branches-table"], [data-guide="branches-empty"]',
            'title' => [
                'ar' => 'جدول الفروع المعتمدة والربط',
                'en' => 'Branch Directory Table & POS Mapping',
            ],
            'body' => [
                'ar' => 'استعراض أكواد الفروع وأسمائها وحالتها والإجراءات المتاحة.',
                'en' => 'Inspect branch codes, names, status, and available actions.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="branches-pagination"], [data-guide="branches-filters"]',
            'title' => [
                'ar' => 'التنقل بين صفحات الفروع',
                'en' => 'Branch List Pagination',
            ],
            'body' => [
                'ar' => 'التنقل بين الصفحات لاستعراض بقية سجلات الفروع دون بطء.',
                'en' => 'Navigate between pages of registered branches efficiently.',
            ],
        ],
        5 => [
            'key' => 'bulk-operations',
            'selector' => '[role="region"][aria-label="Bulk operations"]',
            'title' => [
                'ar' => 'العمليات الجماعية الآمنة',
                'en' => 'Safe Bulk Operations',
            ],
            'body' => [
                'ar' => 'يوضح شريط العمليات الجماعية أن التحديد يخص الصفحة الحالية، ويمكن تغيير حالة الفروع المحددة بعد التأكيد.',
                'en' => 'The bulk toolbar selects the current page only; selected branches can have their status changed after confirmation.',
            ],
        ],
    ],
    'updated_at' => '2026-08-06',
    'version' => '1.1',
    'screen_id' => 'UI-ADM-003',
];
