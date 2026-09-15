<?php

return [
    'route_names' => [
        0 => 'admin.cash-drawers',
    ],
    'title' => [
        'ar' => 'أدراج النقدية',
        'en' => 'Cash Drawers',
    ],
    'purpose' => [
        'ar' => 'تراجع تعيينات الأدراج وحالتها.',
        'en' => 'Review drawer assignments and status.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'drawers_payments_tax_numbering_printers.view',
        1 => 'drawers_payments_tax_numbering_printers.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'drawers_payments_tax_numbering_printers.view',
            'label' => [
                'ar' => 'عرض تعيينات الأدراج والإعدادات التشغيلية',
                'en' => 'View drawer assignments and operational settings',
            ],
            'required_permission' => 'drawers_payments_tax_numbering_printers.view',
        ],
        1 => [
            'key' => 'drawers_payments_tax_numbering_printers.edit',
            'label' => [
                'ar' => 'تعديل تعيينات أدراج النقدية والإعدادات',
                'en' => 'Edit cash drawer assignments and settings',
            ],
            'required_permission' => 'drawers_payments_tax_numbering_printers.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-ADM-03',
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
                'selector' => '[data-guide="drawers-header"]',
                'title' => [
                    'ar' => 'رأس سجل أدراج النقدية',
                    'en' => 'Cash Drawer Masters Header',
                ],
                'body' => [
                    'ar' => 'تهيئة وتخصيص أدراج النقدية الفيزيائية للفروع والمتاجر البيعية.',
                    'en' => 'Configure branch-scoped cash drawers and default POS cashier assignments.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="drawers-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة درج نقدية',
                    'en' => 'Add Cash Drawer Action',
                ],
                'body' => [
                    'ar' => 'إنشاء تسجيل جديد لدرج نقدية وتعيينه للفرع والمتجر المناسب.',
                    'en' => 'Create a cash drawer record linked to a branch and optional store.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="drawers-filters"]',
                'title' => [
                    'ar' => 'تصفية الأدراج حسب الفرع والحالة',
                    'en' => 'Search & Branch Drawer Filters',
                ],
                'body' => [
                    'ar' => 'البحث في أكواد الأدراج والتصفية حسب الفرع التابع أو حالة الصيانة.',
                    'en' => 'Filter cash drawers by code, branch location, or operational status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="drawers-table"]',
                'title' => [
                    'ar' => 'جدول أدراج النقدية والتعيينات',
                    'en' => 'Cash Drawer Registry Table',
                ],
                'body' => [
                    'ar' => 'عرض كود الدرج، الفرع والارتباط بالمتجر، والكاشير الموكل إليه.',
                    'en' => 'Inspect drawer codes, assigned branches, stores, cashiers, and status badges.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="drawers-pagination"], [data-guide="drawers-filters"]',
                'title' => [
                    'ar' => 'التنقل في قائمة الأدراج',
                    'en' => 'Cash Drawer Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل عبر صفحات قائمة أدراج النقدية المسجلة.',
                    'en' => 'Browse paginated cash drawer records safely.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود الدرج والاسم',
                    'en' => 'Drawer Code & Name',
                ],
                'body' => [
                    'ar' => 'المعرف الفريد لدرج النقدية الفيزيائي في موقع البيع.',
                    'en' => 'Unique identifier for the physical cash drawer at point of sale.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'الموقع والوردية النشطة',
                    'en' => 'Location & Active Shift',
                ],
                'body' => [
                    'ar' => 'الفرع والمتجر المربوط به الدرج وحالة الوردية المفتوحة حالياً.',
                    'en' => 'Mapped branch and store along with current open shift assignment state.',
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
            'selector' => '[data-guide="drawers-header"]',
            'title' => [
                'ar' => 'رأس سجل أدراج النقدية',
                'en' => 'Cash Drawer Masters Header',
            ],
            'body' => [
                'ar' => 'تهيئة وتخصيص أدراج النقدية الفيزيائية للفروع والمتاجر البيعية.',
                'en' => 'Configure branch-scoped cash drawers and default POS cashier assignments.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="drawers-add-action"]',
            'title' => [
                'ar' => 'زر إضافة درج نقدية',
                'en' => 'Add Cash Drawer Action',
            ],
            'body' => [
                'ar' => 'إنشاء تسجيل جديد لدرج نقدية وتعيينه للفرع والمتجر المناسب.',
                'en' => 'Create a cash drawer record linked to a branch and optional store.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="drawers-filters"]',
            'title' => [
                'ar' => 'تصفية الأدراج حسب الفرع والحالة',
                'en' => 'Search & Branch Drawer Filters',
            ],
            'body' => [
                'ar' => 'البحث في أكواد الأدراج والتصفية حسب الفرع التابع أو حالة الصيانة.',
                'en' => 'Filter cash drawers by code, branch location, or operational status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="drawers-table"]',
            'title' => [
                'ar' => 'جدول أدراج النقدية والتعيينات',
                'en' => 'Cash Drawer Registry Table',
            ],
            'body' => [
                'ar' => 'عرض كود الدرج، الفرع والارتباط بالمتجر، والكاشير الموكل إليه.',
                'en' => 'Inspect drawer codes, assigned branches, stores, cashiers, and status badges.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="drawers-pagination"], [data-guide="drawers-filters"]',
            'title' => [
                'ar' => 'التنقل في قائمة الأدراج',
                'en' => 'Cash Drawer Pagination',
            ],
            'body' => [
                'ar' => 'التنقل عبر صفحات قائمة أدراج النقدية المسجلة.',
                'en' => 'Browse paginated cash drawer records safely.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-ADM-005',
];
