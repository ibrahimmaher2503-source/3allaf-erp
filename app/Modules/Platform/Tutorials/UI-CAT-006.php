<?php

return [
    'route_names' => [
        0 => 'catalog.categories',
    ],
    'title' => [
        'ar' => 'التصنيفات',
        'en' => 'Categories',
    ],
    'purpose' => [
        'ar' => 'تدير شجرة التصنيفات وحالتها.',
        'en' => 'Manage the category tree and status.',
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
                'ar' => 'عرض شجرة وهيكل التصنيفات',
                'en' => 'View category tree and list',
            ],
            'required_permission' => 'products_categories_brands.view',
        ],
        1 => [
            'key' => 'products_categories_brands.create',
            'label' => [
                'ar' => 'إضافة تصنيف جديد',
                'en' => 'Create new category',
            ],
            'required_permission' => 'products_categories_brands.create',
        ],
        2 => [
            'key' => 'products_categories_brands.edit',
            'label' => [
                'ar' => 'تعديل بيانات والتسلسل الهرمي للتصنيف',
                'en' => 'Edit category details',
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
                'selector' => '[data-guide="categories-header"]',
                'title' => [
                    'ar' => 'رأس سجل تصنيفات المنتجات',
                    'en' => 'Category Masters Directory Header',
                ],
                'body' => [
                    'ar' => 'إدارة الهيكل الهرمي للتصنيفات وقواعد التبعية المعتمدة بالخادم.',
                    'en' => 'Maintain bounded category hierarchy with server-side dependency guards.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="categories-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة تصنيف جديد',
                    'en' => 'Add Category Action Button',
                ],
                'body' => [
                    'ar' => 'فتح نموذج إضافة تصنيف جديد وإسناده كتصنيف رئيسي أو فرعي.',
                    'en' => 'Open modal form to create a new root or child category record.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="categories-filters"]',
                'title' => [
                    'ar' => 'تصفية وبحث شجرة التصنيفات',
                    'en' => 'Search & Status Category Filters',
                ],
                'body' => [
                    'ar' => 'البحث بالكود أو الاسم ثنائي اللغة والتصفية حسب حالة التفعيل.',
                    'en' => 'Search categories by code or bilingual name and filter by status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="categories-table"], [data-guide="categories-empty"]',
                'title' => [
                    'ar' => 'جدول شجرة التصنيفات والترتيب',
                    'en' => 'Category Hierarchy Table & Order',
                ],
                'body' => [
                    'ar' => 'استعراض كود التصنيف، علاقة الأب والفرع، الترتيب التشغيلي، والحالة.',
                    'en' => 'Inspect category codes, parent markers, sort order, and status badges.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="categories-pagination"], [data-guide="categories-filters"]',
                'title' => [
                    'ar' => 'تصفح قائمة التصنيفات',
                    'en' => 'Category Directory Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل المنظم بين صفحات شجرة التصنيفات المسجلة.',
                    'en' => 'Browse paginated category records safely.',
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
                    'ar' => 'حدد التصنيفات من شريط العمليات الجماعية الحالي للصفحة ثم نفذ تغيير الحالة بعد مراجعة التأكيد.',
                    'en' => 'Select categories from the current-page bulk toolbar, then run the status change after reviewing the confirmation.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود التصنيف والاسم',
                    'en' => 'Category Code & Name',
                ],
                'body' => [
                    'ar' => 'الكود المرجعي والتسمية ثنائية اللغة للتصنيف.',
                    'en' => 'Reference code and bilingual category names.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'التصنيف الأب والحالة',
                    'en' => 'Parent Category & Status',
                ],
                'body' => [
                    'ar' => 'التصنيف الأب الأعلى في الهيكل وحالة التفعيل التشغيلية.',
                    'en' => 'Higher-level parent category and active operational status.',
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
            'selector' => '[data-guide="categories-header"]',
            'title' => [
                'ar' => 'رأس سجل تصنيفات المنتجات',
                'en' => 'Category Masters Directory Header',
            ],
            'body' => [
                'ar' => 'إدارة الهيكل الهرمي للتصنيفات وقواعد التبعية المعتمدة بالخادم.',
                'en' => 'Maintain bounded category hierarchy with server-side dependency guards.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="categories-add-action"]',
            'title' => [
                'ar' => 'زر إضافة تصنيف جديد',
                'en' => 'Add Category Action Button',
            ],
            'body' => [
                'ar' => 'فتح نموذج إضافة تصنيف جديد وإسناده كتصنيف رئيسي أو فرعي.',
                'en' => 'Open modal form to create a new root or child category record.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="categories-filters"]',
            'title' => [
                'ar' => 'تصفية وبحث شجرة التصنيفات',
                'en' => 'Search & Status Category Filters',
            ],
            'body' => [
                'ar' => 'البحث بالكود أو الاسم ثنائي اللغة والتصفية حسب حالة التفعيل.',
                'en' => 'Search categories by code or bilingual name and filter by status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="categories-table"], [data-guide="categories-empty"]',
            'title' => [
                'ar' => 'جدول شجرة التصنيفات والترتيب',
                'en' => 'Category Hierarchy Table & Order',
            ],
            'body' => [
                'ar' => 'استعراض كود التصنيف، علاقة الأب والفرع، الترتيب التشغيلي، والحالة.',
                'en' => 'Inspect category codes, parent markers, sort order, and status badges.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="categories-pagination"], [data-guide="categories-filters"]',
            'title' => [
                'ar' => 'تصفح قائمة التصنيفات',
                'en' => 'Category Directory Pagination',
            ],
            'body' => [
                'ar' => 'التنقل المنظم بين صفحات شجرة التصنيفات المسجلة.',
                'en' => 'Browse paginated category records safely.',
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
                'ar' => 'حدد التصنيفات من شريط العمليات الجماعية الحالي للصفحة ثم نفذ تغيير الحالة بعد مراجعة التأكيد.',
                'en' => 'Select categories from the current-page bulk toolbar, then run the status change after reviewing the confirmation.',
            ],
        ],
    ],
    'updated_at' => '2026-08-06',
    'version' => '1.1',
    'screen_id' => 'UI-CAT-006',
];
