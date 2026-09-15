<?php

return [
    'route_names' => [
        0 => 'catalog.products.show',
    ],
    'title' => [
        'ar' => 'تفاصيل المنتج',
        'en' => 'Product Details',
    ],
    'purpose' => [
        'ar' => 'تعرض تفاصيل المنتج والتبويبات المسموح بها.',
        'en' => 'Shows product details and permitted tabs.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'products_categories_brands.view',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'products_categories_brands.view',
            'label' => [
                'ar' => 'عرض تفاصيل وسجل المنتج',
                'en' => 'View product details and history',
            ],
            'required_permission' => 'products_categories_brands.view',
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
                'selector' => '[data-guide="product-detail-header"]',
                'title' => [
                    'ar' => 'رأس تفاصيل المنتج',
                    'en' => 'Product Detail Summary Header',
                ],
                'body' => [
                    'ar' => 'عرض العنوان الرئيسي للمنتج، زر العودة للقائمة، وزر التعديل المصرح.',
                    'en' => 'Display product title, navigation back link, and permitted edit actions.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="product-detail-hero"]',
                'title' => [
                    'ar' => 'بطاقة هوية المنتج والصورة الرئيسية',
                    'en' => 'Product Identity & Hero Media Card',
                ],
                'body' => [
                    'ar' => 'استعراض الصورة الرئيسية، الكود الثابت، الحالة، التصنيف، والعلامة.',
                    'en' => 'Inspect primary image, item code, active status, category, and brand.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="product-detail-descriptions"]',
                'title' => [
                    'ar' => 'الوصف والأنقاط الرئيسية ثنائية اللغة',
                    'en' => 'Bilingual Descriptions Panel',
                ],
                'body' => [
                    'ar' => 'مراجعة النص الوصفي والنقاط الترويجية بالعربية والإنجليزية.',
                    'en' => 'Review detailed product descriptions and key selling points in AR & EN.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="product-detail-attributes"]',
                'title' => [
                    'ar' => 'الخصائص الفيزيائية والتصنيفية',
                    'en' => 'Reportable Physical Attributes Panel',
                ],
                'body' => [
                    'ar' => 'استعراض اللون، الحجم، الشخصية، العمر الموجه، الأبعاد، والوزن.',
                    'en' => 'Inspect colour, size, character, target age, gender, dimensions, and weight.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="product-detail-media"]',
                'title' => [
                    'ar' => 'شريط الباركود والملفات المحمية',
                    'en' => 'Barcodes & Protected Media Sidebar',
                ],
                'body' => [
                    'ar' => 'مراجعة الباركودات المسجلة ومعاينة الصور المحمية بالصلاحيات.',
                    'en' => 'Review assigned barcodes list and preview scope-authorized product images.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'بيانات الهوية والمستندات',
                    'en' => 'Identity & Media Details',
                ],
                'body' => [
                    'ar' => 'كود المنتج، الأسماء ثنائية اللغة، الباركودات، والصور المحمية.',
                    'en' => 'Item code, bilingual names, assigned barcodes, and protected media.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'الموردون وسجل التدقيق',
                    'en' => 'Suppliers & Audit Trail',
                ],
                'body' => [
                    'ar' => 'المورد المفضل، التكاليف المحمية حسب الصلاحية، وسجل الأحداث.',
                    'en' => 'Preferred supplier, scope-protected costs, and audit event timeline.',
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
            'selector' => '[data-guide="product-detail-header"]',
            'title' => [
                'ar' => 'رأس تفاصيل المنتج',
                'en' => 'Product Detail Summary Header',
            ],
            'body' => [
                'ar' => 'عرض العنوان الرئيسي للمنتج، زر العودة للقائمة، وزر التعديل المصرح.',
                'en' => 'Display product title, navigation back link, and permitted edit actions.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="product-detail-hero"]',
            'title' => [
                'ar' => 'بطاقة هوية المنتج والصورة الرئيسية',
                'en' => 'Product Identity & Hero Media Card',
            ],
            'body' => [
                'ar' => 'استعراض الصورة الرئيسية، الكود الثابت، الحالة، التصنيف، والعلامة.',
                'en' => 'Inspect primary image, item code, active status, category, and brand.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="product-detail-descriptions"]',
            'title' => [
                'ar' => 'الوصف والأنقاط الرئيسية ثنائية اللغة',
                'en' => 'Bilingual Descriptions Panel',
            ],
            'body' => [
                'ar' => 'مراجعة النص الوصفي والنقاط الترويجية بالعربية والإنجليزية.',
                'en' => 'Review detailed product descriptions and key selling points in AR & EN.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="product-detail-attributes"]',
            'title' => [
                'ar' => 'الخصائص الفيزيائية والتصنيفية',
                'en' => 'Reportable Physical Attributes Panel',
            ],
            'body' => [
                'ar' => 'استعراض اللون، الحجم، الشخصية، العمر الموجه، الأبعاد، والوزن.',
                'en' => 'Inspect colour, size, character, target age, gender, dimensions, and weight.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="product-detail-media"]',
            'title' => [
                'ar' => 'شريط الباركود والملفات المحمية',
                'en' => 'Barcodes & Protected Media Sidebar',
            ],
            'body' => [
                'ar' => 'مراجعة الباركودات المسجلة ومعاينة الصور المحمية بالصلاحيات.',
                'en' => 'Review assigned barcodes list and preview scope-authorized product images.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-CAT-002',
];
