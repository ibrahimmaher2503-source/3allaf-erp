<?php

return [
    'route_names' => [
        0 => 'catalog.products.import',
    ],
    'title' => [
        'ar' => 'استيراد المنتجات',
        'en' => 'Product Import',
    ],
    'purpose' => [
        'ar' => 'ترفع ملفاً وتراجعه ثم تعتمد الصفوف الصحيحة فقط.',
        'en' => 'Upload, review, and approve only valid rows.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'products_categories_brands.view',
        1 => 'products_categories_brands.create',
        2 => 'products_categories_brands.edit',
        3 => 'products_categories_brands.approve',
        4 => 'products_categories_brands.export',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'products_categories_brands.view',
            'label' => [
                'ar' => 'عرض ومراجعة صفحة استيراد المنتجات',
                'en' => 'Review and view product import page',
            ],
            'required_permission' => 'products_categories_brands.view',
        ],
        1 => [
            'key' => 'products_categories_brands.create',
            'label' => [
                'ar' => 'رفع ومرحلة دفعة استيراد المنتجات',
                'en' => 'Stage and upload an import batch',
            ],
            'required_permission' => 'products_categories_brands.create',
        ],
        2 => [
            'key' => 'products_categories_brands.edit',
            'label' => [
                'ar' => 'مراجعة نتائج التحقق للصفوف المرحلة',
                'en' => 'Review staged validation results',
            ],
            'required_permission' => 'products_categories_brands.edit',
        ],
        3 => [
            'key' => 'products_categories_brands.approve',
            'label' => [
                'ar' => 'اعتماد دفعة استيراد المنتجات الصالحة',
                'en' => 'Approve valid import batch',
            ],
            'required_permission' => 'products_categories_brands.approve',
        ],
        4 => [
            'key' => 'products_categories_brands.export',
            'label' => [
                'ar' => 'تنزيل تقرير أخطاء الاستيراد',
                'en' => 'Download import error report',
            ],
            'required_permission' => 'products_categories_brands.export',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-CAT-02',
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
                'selector' => '[data-guide="import-header"]',
                'title' => [
                    'ar' => 'رأس شاشة استيراد المنتجات',
                    'en' => 'Product Import Screen Header',
                ],
                'body' => [
                    'ar' => 'معالجة واستيراد ملفات المنتجات عبر مراحل التدقيق والاعتماد المحمي.',
                    'en' => 'Stage, validate, review, and approve product spreadsheet batches safely.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="import-upload-section"]',
                'title' => [
                    'ar' => 'بطاقة رفع الملفات وتجهيزها',
                    'en' => 'Upload & Stage Spreadsheet Card',
                ],
                'body' => [
                    'ar' => 'اختيار ملف Excel أو CSV مطابق للأعمدة الأساسية المطلوبة دون صيغ حسابية.',
                    'en' => 'Select valid Excel or CSV file with required columns and no formula cells.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="import-mode-select"]',
                'title' => [
                    'ar' => 'تحديد نمط الاستيراد المعتمد',
                    'en' => 'Select Import Processing Mode',
                ],
                'body' => [
                    'ar' => 'اختيار إما إنشاء جديد فقط لمنع التعديل أو تحديث المنتجات الموجودة.',
                    'en' => 'Choose Create Only to prevent overwrites or Update Existing for updates.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="import-stage-button"]',
                'title' => [
                    'ar' => 'زر مرحلة وفحص الملف',
                    'en' => 'Stage File Action Button',
                ],
                'body' => [
                    'ar' => 'بدء فحص الهيكل والتكرارات وتدقيق البيانات مرجعياً دون كتابة في القاعدة.',
                    'en' => 'Process file validation and staging without writing any database records yet.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="import-batches-section"]',
                'title' => [
                    'ar' => 'جدول الدفعات المجهزة للمراجعة',
                    'en' => 'Staged Import Batches List',
                ],
                'body' => [
                    'ar' => 'متابعة حالة الدفعات المرفوعة وعدد الصفوف الصالحة والمرفوضة بكل دفعة.',
                    'en' => 'Inspect staged batches, status badges, valid row counts, and review actions.',
                ],
            ],
            5 => [
                'key' => 'step-6',
                'selector' => '[data-guide="import-review-section"], [data-guide="import-batches-section"]',
                'title' => [
                    'ar' => 'معاينة ومراجعة صفوف الدفعة',
                    'en' => 'Batch Review & Row Diagnostics',
                ],
                'body' => [
                    'ar' => 'فحص ملخص الأخطاء والتأكد من سلامة الصفوف قبل اتخاذ قرار الاعتماد.',
                    'en' => 'Review row status, mapped data, and error details for selected batch.',
                ],
            ],
            6 => [
                'key' => 'step-7',
                'selector' => '[data-guide="import-approve-button"], [data-guide="import-batches-section"]',
                'title' => [
                    'ar' => 'زر اعتماد الصفوف الصالحة',
                    'en' => 'Approve Valid Rows Action Button',
                ],
                'body' => [
                    'ar' => 'اعتماد كتابة المنتجات المعتمدة؛ مع حظر العملية في حال وجود أي صف مرفوض.',
                    'en' => 'Commit valid product rows to database; server blocks if invalid rows exist.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'ملف الاستيراد ونمط التنفيذ',
                    'en' => 'Import File & Execution Mode',
                ],
                'body' => [
                    'ar' => 'ملف Excel/CSV ونمط المعالجة (إنشاء جديد فقط / تحديث الموجود).',
                    'en' => 'Spreadsheet file and execution mode (Create Only / Update Existing).',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'حالة الدفعة والصفوف',
                    'en' => 'Batch & Row Validation Status',
                ],
                'body' => [
                    'ar' => 'حالة الدفعة، إجمالي الصفوف، الصفوف الصالحة، والصفوف المرفوضة.',
                    'en' => 'Batch state, total row count, valid rows count, and rejected rows count.',
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
            'selector' => '[data-guide="import-header"]',
            'title' => [
                'ar' => 'رأس شاشة استيراد المنتجات',
                'en' => 'Product Import Screen Header',
            ],
            'body' => [
                'ar' => 'معالجة واستيراد ملفات المنتجات عبر مراحل التدقيق والاعتماد المحمي.',
                'en' => 'Stage, validate, review, and approve product spreadsheet batches safely.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="import-upload-section"]',
            'title' => [
                'ar' => 'بطاقة رفع الملفات وتجهيزها',
                'en' => 'Upload & Stage Spreadsheet Card',
            ],
            'body' => [
                'ar' => 'اختيار ملف Excel أو CSV مطابق للأعمدة الأساسية المطلوبة دون صيغ حسابية.',
                'en' => 'Select valid Excel or CSV file with required columns and no formula cells.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="import-mode-select"]',
            'title' => [
                'ar' => 'تحديد نمط الاستيراد المعتمد',
                'en' => 'Select Import Processing Mode',
            ],
            'body' => [
                'ar' => 'اختيار إما إنشاء جديد فقط لمنع التعديل أو تحديث المنتجات الموجودة.',
                'en' => 'Choose Create Only to prevent overwrites or Update Existing for updates.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="import-stage-button"]',
            'title' => [
                'ar' => 'زر مرحلة وفحص الملف',
                'en' => 'Stage File Action Button',
            ],
            'body' => [
                'ar' => 'بدء فحص الهيكل والتكرارات وتدقيق البيانات مرجعياً دون كتابة في القاعدة.',
                'en' => 'Process file validation and staging without writing any database records yet.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="import-batches-section"]',
            'title' => [
                'ar' => 'جدول الدفعات المجهزة للمراجعة',
                'en' => 'Staged Import Batches List',
            ],
            'body' => [
                'ar' => 'متابعة حالة الدفعات المرفوعة وعدد الصفوف الصالحة والمرفوضة بكل دفعة.',
                'en' => 'Inspect staged batches, status badges, valid row counts, and review actions.',
            ],
        ],
        5 => [
            'key' => 'step-6',
            'selector' => '[data-guide="import-review-section"], [data-guide="import-batches-section"]',
            'title' => [
                'ar' => 'معاينة ومراجعة صفوف الدفعة',
                'en' => 'Batch Review & Row Diagnostics',
            ],
            'body' => [
                'ar' => 'فحص ملخص الأخطاء والتأكد من سلامة الصفوف قبل اتخاذ قرار الاعتماد.',
                'en' => 'Review row status, mapped data, and error details for selected batch.',
            ],
        ],
        6 => [
            'key' => 'step-7',
            'selector' => '[data-guide="import-approve-button"], [data-guide="import-batches-section"]',
            'title' => [
                'ar' => 'زر اعتماد الصفوف الصالحة',
                'en' => 'Approve Valid Rows Action Button',
            ],
            'body' => [
                'ar' => 'اعتماد كتابة المنتجات المعتمدة؛ مع حظر العملية في حال وجود أي صف مرفوض.',
                'en' => 'Commit valid product rows to database; server blocks if invalid rows exist.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-CAT-004',
];
