<?php

return [
    'route_names' => [
        0 => 'catalog.products.create',
        1 => 'catalog.products.edit',
    ],
    'title' => [
        'ar' => 'إنشاء وتعديل المنتج',
        'en' => 'Product Create/Edit',
    ],
    'purpose' => [
        'ar' => 'تحرر بيانات المنتج وفق الصلاحيات وقواعد المجال.',
        'en' => 'Edit product data according to permissions and domain rules.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'products_categories_brands.create',
        1 => 'products_categories_brands.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'products_categories_brands.create',
            'label' => [
                'ar' => 'إنشاء وحفظ سجل منتج جديد',
                'en' => 'Create new product record',
            ],
            'required_permission' => 'products_categories_brands.create',
        ],
        1 => [
            'key' => 'products_categories_brands.edit',
            'label' => [
                'ar' => 'تعديل وحفظ بيانات المنتج',
                'en' => 'Update product record',
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
                'selector' => '[data-guide="product-form-header"]',
                'title' => [
                    'ar' => 'رأس نموذج بطاقة المنتج',
                    'en' => 'Product Form Header',
                ],
                'body' => [
                    'ar' => 'نموذج موحد لإنشاء وتعديل بيانات هوية المنتج والمحتوى.',
                    'en' => 'Focused header for creating or updating product card information.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="product-form-identity"]',
                'title' => [
                    'ar' => 'قسم الهوية الأساسية الثابتة',
                    'en' => 'Basic Immutable Identity Section',
                ],
                'body' => [
                    'ar' => 'إدخال الكود الثابت غير القابل للتعديل لاحقاً، والأسماء والوصف.',
                    'en' => 'Enter immutable item code, model number, bilingual names, and descriptions.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="product-form-classification"]',
                'title' => [
                    'ar' => 'قسم التصنيف ونوع المنتج',
                    'en' => 'Classification & Product Type Section',
                ],
                'body' => [
                    'ar' => 'اختيار التصنيف النشط، العلامة التجارية، ونوع المنتج (قياسي/مركب/خدمي).',
                    'en' => 'Select active category, brand, product type, and operational status.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="product-form-attributes"]',
                'title' => [
                    'ar' => 'قسم المواصفات والخصائص',
                    'en' => 'Physical Attributes & Keywords Section',
                ],
                'body' => [
                    'ar' => 'إدخال خصائص البحث مثل العمر، الجنس، اللون، الحجم، الأبعاد، والكلمات المفتاحية.',
                    'en' => 'Configure UOM, target age, gender, colour, dimensions, and search keywords.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="product-form-media"]',
                'title' => [
                    'ar' => 'قسم رفع وإدارة الصور المحمية',
                    'en' => 'Protected Product Media Section',
                ],
                'body' => [
                    'ar' => 'رفع صورة رئيسية وحتى 4 صور إضافية محمية عبر مؤسسة المرفقات.',
                    'en' => 'Upload and organize 1 main image and up to 4 additional protected images.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'الحقول الأساسية والنوع',
                    'en' => 'Master Fields & Type',
                ],
                'body' => [
                    'ar' => 'الكود الثابت، الأسماء بالعربية والإنجليزية، ونوع المنتج.',
                    'en' => 'Immutable code, bilingual names, and product composition type.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'التصنيفات والباركود',
                    'en' => 'Categories & Barcode',
                ],
                'body' => [
                    'ar' => 'التصنيف، العلامة التجارية، المورد المفضل، والباركود الفريد.',
                    'en' => 'Category, brand, preferred supplier, and unique item barcode.',
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
            'selector' => '[data-guide="product-form-header"]',
            'title' => [
                'ar' => 'رأس نموذج بطاقة المنتج',
                'en' => 'Product Form Header',
            ],
            'body' => [
                'ar' => 'نموذج موحد لإنشاء وتعديل بيانات هوية المنتج والمحتوى.',
                'en' => 'Focused header for creating or updating product card information.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="product-form-identity"]',
            'title' => [
                'ar' => 'قسم الهوية الأساسية الثابتة',
                'en' => 'Basic Immutable Identity Section',
            ],
            'body' => [
                'ar' => 'إدخال الكود الثابت غير القابل للتعديل لاحقاً، والأسماء والوصف.',
                'en' => 'Enter immutable item code, model number, bilingual names, and descriptions.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="product-form-classification"]',
            'title' => [
                'ar' => 'قسم التصنيف ونوع المنتج',
                'en' => 'Classification & Product Type Section',
            ],
            'body' => [
                'ar' => 'اختيار التصنيف النشط، العلامة التجارية، ونوع المنتج (قياسي/مركب/خدمي).',
                'en' => 'Select active category, brand, product type, and operational status.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="product-form-attributes"]',
            'title' => [
                'ar' => 'قسم المواصفات والخصائص',
                'en' => 'Physical Attributes & Keywords Section',
            ],
            'body' => [
                'ar' => 'إدخال خصائص البحث مثل العمر، الجنس، اللون، الحجم، الأبعاد، والكلمات المفتاحية.',
                'en' => 'Configure UOM, target age, gender, colour, dimensions, and search keywords.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="product-form-media"]',
            'title' => [
                'ar' => 'قسم رفع وإدارة الصور المحمية',
                'en' => 'Protected Product Media Section',
            ],
            'body' => [
                'ar' => 'رفع صورة رئيسية وحتى 4 صور إضافية محمية عبر مؤسسة المرفقات.',
                'en' => 'Upload and organize 1 main image and up to 4 additional protected images.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-CAT-003',
];
