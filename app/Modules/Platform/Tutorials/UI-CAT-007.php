<?php

return [
    'route_names' => [
        0 => 'catalog.brands',
    ],
    'title' => [
        'ar' => 'العلامات التجارية',
        'en' => 'Brands',
    ],
    'purpose' => [
        'ar' => 'تدير بيانات العلامات التجارية.',
        'en' => 'Manage brand master data.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'products_categories_brands.view',
        1 => 'products_categories_brands.create',
        2 => 'products_categories_brands.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'products_categories_brands.view',
            'label' => [
                'ar' => 'عرض قائمة العلامات التجارية',
                'en' => 'View brand list',
            ],
            'required_permission' => 'products_categories_brands.view',
        ],
        1 => [
            'key' => 'products_categories_brands.create',
            'label' => [
                'ar' => 'إضافة علامة تجارية جديدة',
                'en' => 'Create new brand',
            ],
            'required_permission' => 'products_categories_brands.create',
        ],
        2 => [
            'key' => 'products_categories_brands.edit',
            'label' => [
                'ar' => 'تعديل بيانات العلامة التجارية',
                'en' => 'Edit brand details',
            ],
            'required_permission' => 'products_categories_brands.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-CAT-01',
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
                'selector' => '[data-guide="brands-header"]',
                'title' => [
                    'ar' => 'رأس سجل العلامات التجارية',
                    'en' => 'Brand Masters Directory Header',
                ],
                'body' => [
                    'ar' => 'إدارة العلامات التجارية العالمية ثنائية اللغة لمنتجات الكتالوج.',
                    'en' => 'Maintain global bilingual brand master records for catalog products.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="brands-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة علامة تجارية',
                    'en' => 'Add Brand Action Button',
                ],
                'body' => [
                    'ar' => 'فتح نموذج إنشاء علامة تجارية جديدة بكود فريد واسم ثنائي اللغة.',
                    'en' => 'Open modal to create a new brand master record with unique code.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="brands-filters"]',
                'title' => [
                    'ar' => 'تصفية والبحث في العلامات',
                    'en' => 'Search & Status Brand Filters',
                ],
                'body' => [
                    'ar' => 'البحث بكود العلامة أو اسمها والتصفية حسب الحالة التشغيلية.',
                    'en' => 'Search brands by code or bilingual name and filter active vs inactive.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="brands-table"], [data-guide="brands-empty"]',
                'title' => [
                    'ar' => 'جدول سجل العلامات التجارية',
                    'en' => 'Brand Masters Directory Table',
                ],
                'body' => [
                    'ar' => 'عرض كود العلامة، الأسماء ثنائية اللغة، عدد المنتجات المربوطة، والحالة.',
                    'en' => 'View brand codes, bilingual names, assigned products count, and status.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="brands-pagination"], [data-guide="brands-filters"]',
                'title' => [
                    'ar' => 'تصفح قائمة العلامات التجارية',
                    'en' => 'Brand Directory Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل المريح بين صفحات سجلات العلامات التجارية المسجلة.',
                    'en' => 'Browse paginated brand records smoothly.',
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
                    'ar' => 'استخدم شريط العمليات الجماعية لتحديد العلامات الحالية وتغيير حالتها بأمان.',
                    'en' => 'Use the bulk toolbar to select current brands and change their status safely.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود العلامة التجارية والاسم',
                    'en' => 'Brand Code & Name',
                ],
                'body' => [
                    'ar' => 'الكود الفريد للعلامة التجارية والاسم ثنائي اللغة.',
                    'en' => 'Unique brand identifier code and bilingual brand name.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'حالة التفعيل',
                    'en' => 'Active Status',
                ],
                'body' => [
                    'ar' => 'حالة تفعيل العلامة وإتاحيتها للاستخدام في المنتجات.',
                    'en' => 'Brand operational activation status for catalog assignment.',
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
            'selector' => '[data-guide="brands-header"]',
            'title' => [
                'ar' => 'رأس سجل العلامات التجارية',
                'en' => 'Brand Masters Directory Header',
            ],
            'body' => [
                'ar' => 'إدارة العلامات التجارية العالمية ثنائية اللغة لمنتجات الكتالوج.',
                'en' => 'Maintain global bilingual brand master records for catalog products.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="brands-add-action"]',
            'title' => [
                'ar' => 'زر إضافة علامة تجارية',
                'en' => 'Add Brand Action Button',
            ],
            'body' => [
                'ar' => 'فتح نموذج إنشاء علامة تجارية جديدة بكود فريد واسم ثنائي اللغة.',
                'en' => 'Open modal to create a new brand master record with unique code.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="brands-filters"]',
            'title' => [
                'ar' => 'تصفية والبحث في العلامات',
                'en' => 'Search & Status Brand Filters',
            ],
            'body' => [
                'ar' => 'البحث بكود العلامة أو اسمها والتصفية حسب الحالة التشغيلية.',
                'en' => 'Search brands by code or bilingual name and filter active vs inactive.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="brands-table"], [data-guide="brands-empty"]',
            'title' => [
                'ar' => 'جدول سجل العلامات التجارية',
                'en' => 'Brand Masters Directory Table',
            ],
            'body' => [
                'ar' => 'عرض كود العلامة، الأسماء ثنائية اللغة، عدد المنتجات المربوطة، والحالة.',
                'en' => 'View brand codes, bilingual names, assigned products count, and status.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="brands-pagination"], [data-guide="brands-filters"]',
            'title' => [
                'ar' => 'تصفح قائمة العلامات التجارية',
                'en' => 'Brand Directory Pagination',
            ],
            'body' => [
                'ar' => 'التنقل المريح بين صفحات سجلات العلامات التجارية المسجلة.',
                'en' => 'Browse paginated brand records smoothly.',
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
                'ar' => 'استخدم شريط العمليات الجماعية لتحديد العلامات الحالية وتغيير حالتها بأمان.',
                'en' => 'Use the bulk toolbar to select current brands and change their status safely.',
            ],
        ],
    ],
    'updated_at' => '2026-08-06',
    'version' => '1.1',
    'screen_id' => 'UI-CAT-007',
];
