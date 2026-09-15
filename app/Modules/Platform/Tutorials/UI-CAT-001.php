<?php

return [
    'route_names' => [
        0 => 'catalog.products',
    ],
    'title' => [
        'ar' => 'قائمة المنتجات',
        'en' => 'Product List',
    ],
    'purpose' => [
        'ar' => 'تستعرض المنتجات والبيانات المسموح بها ضمن نطاقك.',
        'en' => 'Browse products and permitted data within your scope.',
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
                'ar' => 'عرض قائمة المنتجات',
                'en' => 'Browse product catalog',
            ],
            'required_permission' => 'products_categories_brands.view',
        ],
        1 => [
            'key' => 'products_categories_brands.create',
            'label' => [
                'ar' => 'إضافة منتج جديد',
                'en' => 'Create new product',
            ],
            'required_permission' => 'products_categories_brands.create',
        ],
        2 => [
            'key' => 'products_categories_brands.edit',
            'label' => [
                'ar' => 'تعديل بيانات المنتج',
                'en' => 'Edit product data',
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
                'selector' => '[data-guide="products-header"]',
                'title' => [
                    'ar' => 'رأس سجل المنتجات القياسي',
                    'en' => 'Product Masters Directory Header',
                ],
                'body' => [
                    'ar' => 'إدارة بطاقات المنتجات الأساسية، الباركودات، والتصنيفات في مكان واحد.',
                    'en' => 'Browse product master records, reportable attributes, and barcodes.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="products-add-action"]',
                'title' => [
                    'ar' => 'زر إضافة منتج جديد',
                    'en' => 'Add Product Action Button',
                ],
                'body' => [
                    'ar' => 'فتح نموذج إنشاء بطاقة منتج جديدة بكود ثابت وأسماء ثنائية اللغة.',
                    'en' => 'Open modal to create a new product identity record in the catalog.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="products-filters"]',
                'title' => [
                    'ar' => 'بطاقة التصفية والبحث المتقدم',
                    'en' => 'Catalog Search & Filter Controls',
                ],
                'body' => [
                    'ar' => 'البحث بالرمز أو الباركود وتصفية المنتجات حسب التصنيف، العلامة، أو النوع.',
                    'en' => 'Filter product catalog by code, barcode, category, brand, type, or gender.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="products-table"], [data-guide="products-empty"]',
                'title' => [
                    'ar' => 'جدول المنتجات المعتمدة',
                    'en' => 'Product Masters Registry Table',
                ],
                'body' => [
                    'ar' => 'عرض كود المنتج الثابت، الاسم ثنائي اللغة، النوع، الباركود، والحالة.',
                    'en' => 'Inspect item codes, bilingual names, product types, primary barcodes, and status.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="products-pagination"], [data-guide="products-filters"]',
                'title' => [
                    'ar' => 'تصفح قائمة المنتجات',
                    'en' => 'Product Catalog Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل المريح بين صفحات سجلات المنتجات المعتمدة.',
                    'en' => 'Browse paginated product records smoothly.',
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
                    'ar' => 'استخدم شريط العمليات الجماعية لتحديد سجلات الصفحة الحالية، مع الالتزام بحد الاختيار وإجراء تغيير الحالة المسموح فقط.',
                    'en' => 'Use the bulk operations toolbar to select the current page, respect the selection limit, and run only the permitted status action.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود المنتج والباركود',
                    'en' => 'Item Code & Barcode',
                ],
                'body' => [
                    'ar' => 'الكود الثابت والباركود الرئيسي المستخدم للبحث والمسح.',
                    'en' => 'Immutable item code and primary barcode used for lookup and scanning.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'الأسماء والتصنيف والعلامة',
                    'en' => 'Names, Category & Brand',
                ],
                'body' => [
                    'ar' => 'الاسم العربي والإنجليزي والتصنيف التابع له والعلامة التجارية.',
                    'en' => 'Bilingual names, assigned category hierarchy, and product brand.',
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
            'selector' => '[data-guide="products-header"]',
            'title' => [
                'ar' => 'رأس سجل المنتجات القياسي',
                'en' => 'Product Masters Directory Header',
            ],
            'body' => [
                'ar' => 'إدارة بطاقات المنتجات الأساسية، الباركودات، والتصنيفات في مكان واحد.',
                'en' => 'Browse product master records, reportable attributes, and barcodes.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="products-add-action"]',
            'title' => [
                'ar' => 'زر إضافة منتج جديد',
                'en' => 'Add Product Action Button',
            ],
            'body' => [
                'ar' => 'فتح نموذج إنشاء بطاقة منتج جديدة بكود ثابت وأسماء ثنائية اللغة.',
                'en' => 'Open modal to create a new product identity record in the catalog.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="products-filters"]',
            'title' => [
                'ar' => 'بطاقة التصفية والبحث المتقدم',
                'en' => 'Catalog Search & Filter Controls',
            ],
            'body' => [
                'ar' => 'البحث بالرمز أو الباركود وتصفية المنتجات حسب التصنيف، العلامة، أو النوع.',
                'en' => 'Filter product catalog by code, barcode, category, brand, type, or gender.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="products-table"], [data-guide="products-empty"]',
            'title' => [
                'ar' => 'جدول المنتجات المعتمدة',
                'en' => 'Product Masters Registry Table',
            ],
            'body' => [
                'ar' => 'عرض كود المنتج الثابت، الاسم ثنائي اللغة، النوع، الباركود، والحالة.',
                'en' => 'Inspect item codes, bilingual names, product types, primary barcodes, and status.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="products-pagination"], [data-guide="products-filters"]',
            'title' => [
                'ar' => 'تصفح قائمة المنتجات',
                'en' => 'Product Catalog Pagination',
            ],
            'body' => [
                'ar' => 'التنقل المريح بين صفحات سجلات المنتجات المعتمدة.',
                'en' => 'Browse paginated product records smoothly.',
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
                'ar' => 'استخدم شريط العمليات الجماعية لتحديد سجلات الصفحة الحالية، مع الالتزام بحد الاختيار وإجراء تغيير الحالة المسموح فقط.',
                'en' => 'Use the bulk operations toolbar to select the current page, respect the selection limit, and run only the permitted status action.',
            ],
        ],
    ],
    'updated_at' => '2026-08-06',
    'version' => '1.1',
    'screen_id' => 'UI-CAT-001',
];
