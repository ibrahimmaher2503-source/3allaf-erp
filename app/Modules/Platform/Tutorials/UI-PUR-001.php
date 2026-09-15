<?php

return [
    'route_names' => [
        0 => 'purchasing.orders',
    ],
    'title' => [
        'ar' => 'أوامر الشراء',
        'en' => 'Purchase Orders',
    ],
    'purpose' => [
        'ar' => 'إدارة وإنشاء وتتبع أوامر الشراء وحالات المتابعة وإلغاء الطلبات.',
        'en' => 'Manage, create, and track purchase order lifecycle, status transitions, and cancellations.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'purchase_orders.view',
        1 => 'purchase_orders.create',
        2 => 'purchase_orders.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'purchase_orders.view',
            'label' => [
                'ar' => 'عرض قائمة وتفاصيل أوامر الشراء',
                'en' => 'View purchase order list and detail',
            ],
            'required_permission' => 'purchase_orders.view',
        ],
        1 => [
            'key' => 'purchase_orders.create',
            'label' => [
                'ar' => 'إنشاء مسودة أمر شراء جديد',
                'en' => 'Create new draft purchase order',
            ],
            'required_permission' => 'purchase_orders.create',
        ],
        2 => [
            'key' => 'purchase_orders.edit',
            'label' => [
                'ar' => 'تعديل وتأكيد وإغلاق أمر الشراء',
                'en' => 'Edit, submit, and close purchase order',
            ],
            'required_permission' => 'purchase_orders.edit',
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
                'selector' => '[data-guide="po-header"]',
                'title' => [
                    'ar' => 'رأس قائمة أوامر الشراء',
                    'en' => 'Purchase Orders Header',
                ],
                'body' => [
                    'ar' => 'إدارة دورة توريد المنتجات وأوامر الشراء للموردين.',
                    'en' => 'Manage supplier procurement and purchase order lifecycle.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="po-create-action"]',
                'title' => [
                    'ar' => 'زر إنشاء أمر شراء',
                    'en' => 'New Purchase Order Action',
                ],
                'body' => [
                    'ar' => 'إضافة مسودة أمر شراء وتحديد المورد والمتجر والمنتجات.',
                    'en' => 'Create a new draft purchase order selecting supplier, store, and items.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="po-filters"]',
                'title' => [
                    'ar' => 'تصفية والبحث في الأوامر',
                    'en' => 'PO Search & Filters',
                ],
                'body' => [
                    'ar' => 'البحث برقم أمر الشراء وتصفية الأوامر حسب الحالة والمورد.',
                    'en' => 'Search by PO number and filter by status or supplier.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="po-table"], [data-guide="po-empty"]',
                'title' => [
                    'ar' => 'جدول أوامر الشراء',
                    'en' => 'Purchase Orders Directory Table',
                ],
                'body' => [
                    'ar' => 'عرض رقم الأمر، المورد، المتجر، الحالة، الإجمالي، والإجراءات.',
                    'en' => 'View PO number, supplier, store, status, totals, and actions.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="po-pagination"], [data-guide="po-filters"]',
                'title' => [
                    'ar' => 'تصفح قائمة أوامر الشراء',
                    'en' => 'PO Directory Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل المريح بين صفحات أوامر الشراء المسجلة.',
                    'en' => 'Browse paginated purchase order records smoothly.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'رقم أمر الشراء والمورد',
                    'en' => 'PO Number & Supplier',
                ],
                'body' => [
                    'ar' => 'الرقم المرجعي الفريد لأمر الشراء والمورد المعتمد.',
                    'en' => 'Unique purchase order reference code and active assigned supplier.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'حالة الطلب والمتجر المستلم',
                    'en' => 'Order Status & Destination Store',
                ],
                'body' => [
                    'ar' => 'الحالة التشغيلية الحالية (مسودة/مؤكد/ملغى/مغلق) والمتجر المستهدف.',
                    'en' => 'Current status (Draft/Submitted/Cancelled/Closed) and destination store.',
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
            'selector' => '[data-guide="po-header"]',
            'title' => [
                'ar' => 'رأس قائمة أوامر الشراء',
                'en' => 'Purchase Orders Header',
            ],
            'body' => [
                'ar' => 'إدارة دورة توريد المنتجات وأوامر الشراء للموردين.',
                'en' => 'Manage supplier procurement and purchase order lifecycle.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="po-create-action"]',
            'title' => [
                'ar' => 'زر إنشاء أمر شراء',
                'en' => 'New Purchase Order Action',
            ],
            'body' => [
                'ar' => 'إضافة مسودة أمر شراء وتحديد المورد والمتجر والمنتجات.',
                'en' => 'Create a new draft purchase order selecting supplier, store, and items.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="po-filters"]',
            'title' => [
                'ar' => 'تصفية والبحث في الأوامر',
                'en' => 'PO Search & Filters',
            ],
            'body' => [
                'ar' => 'البحث برقم أمر الشراء وتصفية الأوامر حسب الحالة والمورد.',
                'en' => 'Search by PO number and filter by status or supplier.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="po-table"], [data-guide="po-empty"]',
            'title' => [
                'ar' => 'جدول أوامر الشراء',
                'en' => 'Purchase Orders Directory Table',
            ],
            'body' => [
                'ar' => 'عرض رقم الأمر، المورد، المتجر، الحالة، الإجمالي، والإجراءات.',
                'en' => 'View PO number, supplier, store, status, totals, and actions.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="po-pagination"], [data-guide="po-filters"]',
            'title' => [
                'ar' => 'تصفح قائمة أوامر الشراء',
                'en' => 'PO Directory Pagination',
            ],
            'body' => [
                'ar' => 'التنقل المريح بين صفحات أوامر الشراء المسجلة.',
                'en' => 'Browse paginated purchase order records smoothly.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-PUR-001',
];
