<?php

return [
    'route_names' => [
        0 => 'catalog.suppliers',
    ],
    'title' => [
        'ar' => 'الموردون',
        'en' => 'Suppliers',
    ],
    'purpose' => [
        'ar' => 'إدارة بيانات الموردين والشروط التجارية وسجل التعاملات.',
        'en' => 'Manage supplier contacts, commercial terms, and relation history.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'suppliers.view',
        1 => 'suppliers.create',
        2 => 'suppliers.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'suppliers.view',
            'label' => [
                'ar' => 'عرض سجل وبيانات الموردين',
                'en' => 'View supplier list and details',
            ],
            'required_permission' => 'suppliers.view',
        ],
        1 => [
            'key' => 'suppliers.create',
            'label' => [
                'ar' => 'إضافة سجل مورد جديد',
                'en' => 'Create new supplier master record',
            ],
            'required_permission' => 'suppliers.create',
        ],
        2 => [
            'key' => 'suppliers.edit',
            'label' => [
                'ar' => 'تعديل بيانات المورد والشروط التجارية',
                'en' => 'Edit supplier data and commercial terms',
            ],
            'required_permission' => 'suppliers.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-PUR-01',
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
                'selector' => '[data-guide="suppliers-header"]',
                'title' => [
                    'ar' => 'رأس سجل الموردين',
                    'en' => 'Supplier Masters Directory Header',
                ],
                'body' => [
                    'ar' => 'إدارة الموردين الشركاء، التراخيص الضريبية والشروط التجارية.',
                    'en' => 'Maintain supplier partner contacts, tax registration, and commercial terms.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="suppliers-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة مورد جديد',
                    'en' => 'Add Supplier Action Button',
                ],
                'body' => [
                    'ar' => 'فتح نموذج إنشاء سجل مورد جديد بكود فريد واسم ثنائي اللغة.',
                    'en' => 'Open modal to create a new supplier master record with unique code.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="suppliers-filters"]',
                'title' => [
                    'ar' => 'تصفية والبحث في الموردين',
                    'en' => 'Search & Status Supplier Filters',
                ],
                'body' => [
                    'ar' => 'البحث بكود المورد أو اسمه أو رقم ضريبته والتصفية حسب الحالة.',
                    'en' => 'Search suppliers by code, name, contact or tax number and filter by status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="suppliers-table"], [data-guide="suppliers-empty"]',
                'title' => [
                    'ar' => 'جدول سجل الموردين',
                    'en' => 'Supplier Masters Directory Table',
                ],
                'body' => [
                    'ar' => 'عرض كود المورد، الاسم ثنائي اللغة، معلومات الاتصال، الرقم الضريبي، وشروط الدفع.',
                    'en' => 'View supplier codes, bilingual names, contacts, tax numbers, and payment terms.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="suppliers-pagination"], [data-guide="suppliers-filters"]',
                'title' => [
                    'ar' => 'تصفح قائمة الموردين',
                    'en' => 'Supplier Directory Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل المريح بين صفحات سجلات الموردين المسجلة.',
                    'en' => 'Browse paginated supplier master records smoothly.',
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
                    'ar' => 'حدد الموردين الظاهرين في الصفحة الحالية فقط، ثم نفذ الإجراء المسموح مع مراجعة حد التحديد.',
                    'en' => 'Select only suppliers visible on the current page, then run the permitted action while respecting the selection limit.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود المورد والاسم',
                    'en' => 'Supplier Code & Name',
                ],
                'body' => [
                    'ar' => 'الكود الفريد للمورد والاسم ثنائي اللغة.',
                    'en' => 'Unique supplier identifier code and bilingual supplier name.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'الرقم الضريبي وشروط الدفع',
                    'en' => 'Tax Number & Payment Terms',
                ],
                'body' => [
                    'ar' => 'رقم التسجيل الضريبي والشروط التجارية المعتمدة للدفع.',
                    'en' => 'Tax registration number and configured commercial payment terms.',
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
            'selector' => '[data-guide="suppliers-header"]',
            'title' => [
                'ar' => 'رأس سجل الموردين',
                'en' => 'Supplier Masters Directory Header',
            ],
            'body' => [
                'ar' => 'إدارة الموردين الشركاء، التراخيص الضريبية والشروط التجارية.',
                'en' => 'Maintain supplier partner contacts, tax registration, and commercial terms.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="suppliers-add-action"]',
            'title' => [
                'ar' => 'زر إضافة مورد جديد',
                'en' => 'Add Supplier Action Button',
            ],
            'body' => [
                'ar' => 'فتح نموذج إنشاء سجل مورد جديد بكود فريد واسم ثنائي اللغة.',
                'en' => 'Open modal to create a new supplier master record with unique code.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="suppliers-filters"]',
            'title' => [
                'ar' => 'تصفية والبحث في الموردين',
                'en' => 'Search & Status Supplier Filters',
            ],
            'body' => [
                'ar' => 'البحث بكود المورد أو اسمه أو رقم ضريبته والتصفية حسب الحالة.',
                'en' => 'Search suppliers by code, name, contact or tax number and filter by status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="suppliers-table"], [data-guide="suppliers-empty"]',
            'title' => [
                'ar' => 'جدول سجل الموردين',
                'en' => 'Supplier Masters Directory Table',
            ],
            'body' => [
                'ar' => 'عرض كود المورد، الاسم ثنائي اللغة، معلومات الاتصال، الرقم الضريبي، وشروط الدفع.',
                'en' => 'View supplier codes, bilingual names, contacts, tax numbers, and payment terms.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="suppliers-pagination"], [data-guide="suppliers-filters"]',
            'title' => [
                'ar' => 'تصفح قائمة الموردين',
                'en' => 'Supplier Directory Pagination',
            ],
            'body' => [
                'ar' => 'التنقل المريح بين صفحات سجلات الموردين المسجلة.',
                'en' => 'Browse paginated supplier master records smoothly.',
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
                'ar' => 'حدد الموردين الظاهرين في الصفحة الحالية فقط، ثم نفذ الإجراء المسموح مع مراجعة حد التحديد.',
                'en' => 'Select only suppliers visible on the current page, then run the permitted action while respecting the selection limit.',
            ],
        ],
    ],
    'updated_at' => '2026-08-06',
    'version' => '1.1',
    'screen_id' => 'UI-CAT-008',
];
