<?php

return [
    'route_names' => [
        0 => 'admin.stores',
    ],
    'title' => [
        'ar' => 'المخازن ونقاط البيع',
        'en' => 'Warehouses & Points of Sale',
    ],
    'purpose' => [
        'ar' => 'تدير المخازن ونقاط البيع التابعة للفروع.',
        'en' => 'Manage warehouses and branch points of sale.',
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
                'ar' => 'عرض مواقع التخزين والمتاجر',
                'en' => 'View stores and stock locations',
            ],
            'required_permission' => 'branches_stores.view',
        ],
        1 => [
            'key' => 'branches_stores.create',
            'label' => [
                'ar' => 'إضافة موقع تخزين جديد',
                'en' => 'Create new store location',
            ],
            'required_permission' => 'branches_stores.create',
        ],
        2 => [
            'key' => 'branches_stores.edit',
            'label' => [
                'ar' => 'تعديل بيانات المتجر والربط بالفرع',
                'en' => 'Edit store mapping and details',
            ],
            'required_permission' => 'branches_stores.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-ADM-02',
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
                'selector' => '[data-guide="stores-header"]',
                'title' => [
                    'ar' => 'رأس سجل المتاجر والمخازن',
                    'en' => 'Store Masters & Mapping Header',
                ],
                'body' => [
                    'ar' => 'إدارة المواقع الفعلية والمنطقية (نقطة بيع، مخزن رئيسي، مركز خدمات، مخزون تالف ومعيب، مخزون قيد النقل) وتعيينات الفروع.',
                    'en' => 'Manage physical and logical locations (point of sale, main warehouse, service center, damaged & defective stock, stock in transit).',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="stores-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة متجر جديد',
                    'en' => 'Add Store Action Button',
                ],
                'body' => [
                    'ar' => 'فتح نافذة إضافة موقع جديد وتحديد نوع الموقع والفرع التابع له.',
                    'en' => 'Open the form modal to add a location and define its location type.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="stores-filters"]',
                'title' => [
                    'ar' => 'تصفية المواقع حسب الفرع ونوع الموقع',
                    'en' => 'Search, Branch & Location Type Filters',
                ],
                'body' => [
                    'ar' => 'تصفية نتائج المواقع حسب كود الفرع، نوع الموقع، أو الحالة التشغيلية.',
                    'en' => 'Filter locations by branch context, location type, and active status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="stores-table"], [data-guide="stores-empty"]',
                'title' => [
                    'ar' => 'جدول المتاجر وسياسة المخزون',
                    'en' => 'Store Directory Table & Stock Policy',
                ],
                'body' => [
                    'ar' => 'استعراض أكواد المواقع، نوع الموقع، ربط نقاط البيع، وسياسة المخزون السالب.',
                    'en' => 'View location codes, location types, branch mapping, negative stock policy, and row actions.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="stores-pagination"], [data-guide="stores-filters"]',
                'title' => [
                    'ar' => 'تصفح صفحات المتاجر',
                    'en' => 'Store List Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل بين صفحات سجلات المتاجر المسجلة.',
                    'en' => 'Browse paginated store records cleanly.',
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
                    'ar' => 'استخدم التحديد الجماعي للصفحة الحالية لتغيير حالة المتاجر المسموح بها دون تنفيذ عمليات خارج النطاق.',
                    'en' => 'Use current-page bulk selection to change permitted store status without performing out-of-scope operations.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود الموقع ونوعه',
                    'en' => 'Location Code & Type',
                ],
                'body' => [
                    'ar' => 'كود موقع التخزين ونوع الموقع (نقطة بيع / مخزن رئيسي / مركز خدمات / مخزون تالف ومعيب / مخزون قيد النقل).',
                    'en' => 'Stock location code and location type (point of sale / main warehouse / service center / damaged & defective stock / stock in transit).',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'ربط الفرع والتأشير البيعي',
                    'en' => 'Branch Association & Selling Flag',
                ],
                'body' => [
                    'ar' => 'تحدد الفرع المالكي للمتجر وتأشير المتجر البيعي الرئيسي للفرع.',
                    'en' => 'Defines owning branch and primary selling store mapping for retail transactions.',
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
            'selector' => '[data-guide="stores-header"]',
            'title' => [
                'ar' => 'رأس سجل المتاجر والمخازن',
                'en' => 'Store Masters & Mapping Header',
            ],
            'body' => [
                'ar' => 'إدارة المواقع الفعلية والمنطقية (نقطة بيع، مخزن رئيسي، مركز خدمات، مخزون تالف ومعيب، مخزون قيد النقل) وتعيينات الفروع.',
                'en' => 'Manage physical and logical locations (point of sale, main warehouse, service center, damaged & defective stock, stock in transit).',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="stores-add-action"]',
            'title' => [
                'ar' => 'زر إضافة متجر جديد',
                'en' => 'Add Store Action Button',
            ],
            'body' => [
                'ar' => 'فتح نافذة إضافة موقع جديد وتحديد نوع الموقع والفرع التابع له.',
                'en' => 'Open the form modal to add a location and define its location type.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="stores-filters"]',
            'title' => [
                'ar' => 'تصفية المواقع حسب الفرع ونوع الموقع',
                'en' => 'Search, Branch & Location Type Filters',
            ],
            'body' => [
                'ar' => 'تصفية نتائج المواقع حسب كود الفرع، نوع الموقع، أو الحالة التشغيلية.',
                'en' => 'Filter locations by branch context, location type, and active status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="stores-table"], [data-guide="stores-empty"]',
            'title' => [
                'ar' => 'جدول المتاجر وسياسة المخزون',
                'en' => 'Store Directory Table & Stock Policy',
            ],
            'body' => [
                'ar' => 'استعراض أكواد المواقع، نوع الموقع، ربط نقاط البيع، وسياسة المخزون السالب.',
                'en' => 'View location codes, location types, branch mapping, negative stock policy, and row actions.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="stores-pagination"], [data-guide="stores-filters"]',
            'title' => [
                'ar' => 'تصفح صفحات المتاجر',
                'en' => 'Store List Pagination',
            ],
            'body' => [
                'ar' => 'التنقل بين صفحات سجلات المتاجر المسجلة.',
                'en' => 'Browse paginated store records cleanly.',
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
                'ar' => 'استخدم التحديد الجماعي للصفحة الحالية لتغيير حالة المتاجر المسموح بها دون تنفيذ عمليات خارج النطاق.',
                'en' => 'Use current-page bulk selection to change permitted store status without performing out-of-scope operations.',
            ],
        ],
    ],
    'updated_at' => '2026-08-06',
    'version' => '1.1',
    'screen_id' => 'UI-ADM-004',
];
