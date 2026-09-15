<?php

return [
    'route_names' => [
        0 => 'admin.settings',
    ],
    'title' => [
        'ar' => 'إعدادات الشركة',
        'en' => 'Company Settings',
    ],
    'purpose' => [
        'ar' => 'تراجع هوية الشركة وإعداداتها العامة المسموح بها.',
        'en' => 'Review the company identity and permitted general settings.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'company_settings.view',
        1 => 'company_settings.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'company_settings.view',
            'label' => [
                'ar' => 'عرض إعدادات الشركة الهيكلية',
                'en' => 'View company structural settings',
            ],
            'required_permission' => 'company_settings.view',
        ],
        1 => [
            'key' => 'company_settings.edit',
            'label' => [
                'ar' => 'تعديل إعدادات الشركة والهوية',
                'en' => 'Edit company settings and identity',
            ],
            'required_permission' => 'company_settings.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-ADM-05',
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
                'selector' => '[data-guide="settings-header"]',
                'title' => [
                    'ar' => 'رأس إعدادات النظام القياسية',
                    'en' => 'System Settings Baseline Header',
                ],
                'body' => [
                    'ar' => 'مراجعة الهوية المحلية للشركة وسياسات النظام وسجل التغييرات.',
                    'en' => 'Review company identity baseline, system policies, and audit log links.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="settings-tabs"]',
                'title' => [
                    'ar' => 'تبويبات أقسام الإعدادات',
                    'en' => 'Settings Navigation Tabs',
                ],
                'body' => [
                    'ar' => 'التنقل بين بيانات الشركة، وسائل الدفع، الضرائب، الترقيم، والطابعات.',
                    'en' => 'Switch between Company, Payment Methods, Tax, Sequences, Printers, and Audit tabs.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="settings-company-card"]',
                'title' => [
                    'ar' => 'بطاقة البيانات الأساسية للشركة',
                    'en' => 'Company Master Information Card',
                ],
                'body' => [
                    'ar' => 'إدخال ومراجعة كود الشركة، الاسم التجاري، الرقم الضريبي، والسجل التجاري.',
                    'en' => 'Enter and review company code, legal name, tax number, and commercial registration.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="settings-localization-card"]',
                'title' => [
                    'ar' => 'سياسات اللغات والعملات والتوقيت',
                    'en' => 'Localization & Currency Policy Card',
                ],
                'body' => [
                    'ar' => 'تحديد العملة الأساسية، المنطقة الزمنية، واللغة الافتراضية للنظام.',
                    'en' => 'Configure system base currency, timezone, and default application locale.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="settings-save-button"]',
                'title' => [
                    'ar' => 'مراجعة تغييرات الشركة',
                    'en' => 'Review Company Changes',
                ],
                'body' => [
                    'ar' => 'راجع تغييرات هوية الشركة ثم أكّد الحفظ المسجل في سجل التدقيق.',
                    'en' => 'Review company identity changes before confirming the audited save.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'بيانات هوية الشركة',
                    'en' => 'Company Identity Fields',
                ],
                'body' => [
                    'ar' => 'تشمل الاسم الرسمي والاسم التجاري والرقم الضريبي والسجل التجاري والعملة والتوقيت.',
                    'en' => 'Includes legal name, trade name, tax registration, CR number, currency, and timezone.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'إعدادات الدفع والضرائب والترقيم',
                    'en' => 'Payment, Tax & Numbering Fields',
                ],
                'body' => [
                    'ar' => 'تحدد وسائل الدفع المتاحة، نسبة الضريبة، وتسلسلات أرقام المستندات الرسمية.',
                    'en' => 'Defines payment methods, effective tax rate, and official document number patterns.',
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
            'selector' => '[data-guide="settings-header"]',
            'title' => [
                'ar' => 'رأس إعدادات النظام القياسية',
                'en' => 'System Settings Baseline Header',
            ],
            'body' => [
                'ar' => 'مراجعة الهوية المحلية للشركة وسياسات النظام وسجل التغييرات.',
                'en' => 'Review company identity baseline, system policies, and audit log links.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="settings-tabs"]',
            'title' => [
                'ar' => 'تبويبات أقسام الإعدادات',
                'en' => 'Settings Navigation Tabs',
            ],
            'body' => [
                'ar' => 'التنقل بين بيانات الشركة، وسائل الدفع، الضرائب، الترقيم، والطابعات.',
                'en' => 'Switch between Company, Payment Methods, Tax, Sequences, Printers, and Audit tabs.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="settings-company-card"]',
            'title' => [
                'ar' => 'بطاقة البيانات الأساسية للشركة',
                'en' => 'Company Master Information Card',
            ],
            'body' => [
                'ar' => 'إدخال ومراجعة كود الشركة، الاسم التجاري، الرقم الضريبي، والسجل التجاري.',
                'en' => 'Enter and review company code, legal name, tax number, and commercial registration.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="settings-localization-card"]',
            'title' => [
                'ar' => 'سياسات اللغات والعملات والتوقيت',
                'en' => 'Localization & Currency Policy Card',
            ],
            'body' => [
                'ar' => 'تحديد العملة الأساسية، المنطقة الزمنية، واللغة الافتراضية للنظام.',
                'en' => 'Configure system base currency, timezone, and default application locale.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="settings-save-button"]',
            'title' => [
                'ar' => 'مراجعة تغييرات الشركة',
                'en' => 'Review Company Changes',
            ],
            'body' => [
                'ar' => 'راجع تغييرات هوية الشركة ثم أكّد الحفظ المسجل في سجل التدقيق.',
                'en' => 'Review company identity changes before confirming the audited save.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-ADM-002',
];
