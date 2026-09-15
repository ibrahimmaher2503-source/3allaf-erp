<?php

return [
    'route_names' => [
        'initial-setup',
    ],
    'title' => [
        'ar' => 'دليل الإعداد الأولي',
        'en' => 'Initial Setup Guide',
    ],
    'purpose' => [
        'ar' => 'يرشدك هذا الدليل إلى تجهيز بيانات التشغيل الأساسية بالترتيب الصحيح، مع إبقاء البيانات الناقصة والاعتمادات المطلوبة ظاهرة بوضوح.',
        'en' => 'This guide walks you through the essential operational setup in the right order while keeping missing data and required approvals visible.',
    ],
    'when_to_use' => [
        'ar' => 'استخدمه عند فتح النظام لأول مرة أو قبل بدء التشغيل الفعلي لمراجعة جاهزية الشركة والفروع والصلاحيات والإعدادات المالية.',
        'en' => 'Use it on first launch or before operations begin to review company, branch, permission, and financial-setting readiness.',
    ],
    'permissions' => [
        'company_settings.edit',
        'company_settings.view',
        'branches_stores.view',
        'users_roles_permissions.view',
        'drawers_payments_tax_numbering_printers.view',
    ],
    'approved_actions' => [
        [
            'key' => 'setup.review',
            'label' => [
                'ar' => 'مراجعة حالة الإعداد الأولي',
                'en' => 'Review initial setup status',
            ],
            'required_permission' => 'company_settings.edit',
        ],
        [
            'key' => 'setup.company',
            'label' => [
                'ar' => 'فتح إعدادات الشركة وإدخال بيانات الهوية',
                'en' => 'Open company settings and enter identity data',
            ],
            'required_permission' => 'company_settings.view',
        ],
        [
            'key' => 'setup.branches',
            'label' => [
                'ar' => 'فتح الفروع والمتاجر ومراجعة الهيكل',
                'en' => 'Open branches and stores and review the structure',
            ],
            'required_permission' => 'branches_stores.view',
        ],
        [
            'key' => 'setup.permissions',
            'label' => [
                'ar' => 'مراجعة المستخدمين والأدوار والنطاقات',
                'en' => 'Review users, roles, and scopes',
            ],
            'required_permission' => 'users_roles_permissions.view',
        ],
        [
            'key' => 'setup.devices',
            'label' => [
                'ar' => 'مراجعة إعدادات الطباعة والأجهزة',
                'en' => 'Review printer and device settings',
            ],
            'required_permission' => 'drawers_payments_tax_numbering_printers.view',
        ],
    ],
    'stories' => [
        'US-001',
        'US-032',
    ],
    'flows' => [
        'FLW-ADM-06',
        'FLW-HELP-02',
        'FLW-ADM-05',
    ],
    'acceptance_criteria' => [
        'AC-UI-08',
        'AC-UI-12',
    ],
    'sections' => [
        'steps' => [
            [
                'key' => 'mission',
                'selector' => '[data-guide="initial-setup-hero"]',
                'title' => [
                    'ar' => 'ابدأ من الصورة الكبيرة',
                    'en' => 'Start with the big picture',
                ],
                'body' => [
                    'ar' => 'يوضح الـHero هدف الشاشة: إدخال بيانات المالك الحقيقية خطوة بخطوة بدون اختلاق قيم إنتاجية أو تجاوز الاعتماد.',
                    'en' => 'The Hero states the mission: enter real owner data step by step without inventing production values or bypassing approval.',
                ],
            ],
            [
                'key' => 'progress',
                'selector' => '[data-guide="initial-setup-summary"]',
                'title' => [
                    'ar' => 'اقرأ نسبة الجاهزية',
                    'en' => 'Read the readiness summary',
                ],
                'body' => [
                    'ar' => 'النسبة تحسب الخطوات الإلزامية فقط. الخطوة الاختيارية لا تمنع بدء المراجعة التشغيلية، لكنها تظل ظاهرة للمراجعة.',
                    'en' => 'The percentage counts required steps only. The optional step does not block operational review but remains visible for follow-up.',
                ],
            ],
            [
                'key' => 'next-action',
                'selector' => '[data-guide="initial-setup-next-step"]',
                'title' => [
                    'ar' => 'ابدأ بالخطوة المقترحة',
                    'en' => 'Start with the recommended step',
                ],
                'body' => [
                    'ar' => 'استخدم زر فتح الخطوة التالية للانتقال إلى شاشة الإدخال المناسبة. إذا اكتملت الخطوة، سيقترح النظام الخطوة الإلزامية التالية.',
                    'en' => 'Use Open next step to reach the correct data-entry screen. When a step is completed, the system recommends the next required one.',
                ],
            ],
            [
                'key' => 'checklist',
                'selector' => '[data-guide="initial-setup-steps"]',
                'title' => [
                    'ar' => 'راجع قائمة المالك',
                    'en' => 'Review the owner checklist',
                ],
                'body' => [
                    'ar' => 'كل بطاقة تعرض المطلوب وحالته وزر المراجعة. لا تعتبر كلمة مكتملة اعتمادًا إنتاجيًا؛ هي فقط نتيجة قراءة البيانات الحالية.',
                    'en' => 'Each card shows the requirement, its current state, and a review action. Completed means the current data passes readiness checks; it is not production sign-off.',
                ],
            ],
            [
                'key' => 'financial',
                'selector' => '[data-guide="initial-setup-step-financial-settings"]',
                'title' => [
                    'ar' => 'افهم الإعدادات المالية',
                    'en' => 'Understand financial settings',
                ],
                'body' => [
                    'ar' => 'يمكن للمالك حفظ الإعداد المالي كنسخة قيد الاعتماد. لا تُستخدم النسخة في التشغيل إلا بعد ربطها بـ ApprovalRecord معتمد ومرورها بشرط السريان الزمني.',
                    'en' => 'The owner may save financial settings as a pending version. They are not operational until linked to an approved ApprovalRecord and within the effective date window.',
                ],
            ],
            [
                'key' => 'safety',
                'selector' => '[data-guide="initial-setup-safety"]',
                'title' => [
                    'ar' => 'احفظ حدود الأمان',
                    'en' => 'Keep the safety boundary',
                ],
                'body' => [
                    'ar' => 'لا تنشئ أسباب إرجاع أو حدود اعتماد أو مستخدمين إنتاجيين من هذه الصفحة. أدخل القيم التي يحددها المالك فقط واترك الاعتماد لمساره المنفصل.',
                    'en' => 'Do not create return reasons, approval limits, or production users from this page. Enter only owner-provided values and keep approval on its separate workflow.',
                ],
            ],
        ],
        'fields' => [
            [
                'key' => 'required',
                'title' => [
                    'ar' => 'إلزامية',
                    'en' => 'Required',
                ],
                'body' => [
                    'ar' => 'خطوة تدخل في نسبة الجاهزية ولا تكتمل إلا إذا قرأ النظام بيانات حقيقية صالحة لها.',
                    'en' => 'A required step contributes to readiness and completes only when the system reads valid real data for it.',
                ],
            ],
            [
                'key' => 'optional',
                'title' => [
                    'ar' => 'اختيارية',
                    'en' => 'Optional',
                ],
                'body' => [
                    'ar' => 'مراجعة مفيدة مثل إعداد الطابعة، لكنها لا تزيد نسبة الخطوات الإلزامية ولا تعني قبول جهاز إنتاجي.',
                    'en' => 'A useful review such as printer setup; it does not increase the required-step percentage or represent production device acceptance.',
                ],
            ],
            [
                'key' => 'pending',
                'title' => [
                    'ar' => 'قيد الاعتماد',
                    'en' => 'Pending approval',
                ],
                'body' => [
                    'ar' => 'القيمة محفوظة للمراجعة لكنها لا تدخل في التشغيل. يجب انتظار مسار الاعتماد المنفصل.',
                    'en' => 'The value is saved for review but is not used operationally. Wait for the separate approval workflow.',
                ],
            ],
            [
                'key' => 'approved',
                'title' => [
                    'ar' => 'معتمد وفعال',
                    'en' => 'Approved and active',
                ],
                'body' => [
                    'ar' => 'تُحسب الإعدادات المالية جاهزة فقط عندما تكون النسخة مرتبطة باعتماد approved ونافذة السريان غير منتهية.',
                    'en' => 'Financial settings count as ready only when linked to an approved record and inside a non-expired effective window.',
                ],
            ],
        ],
        'notes' => [
            'ar' => 'تأكد من أن بيانات الشركة والفروع والأسباب والإعدادات المالية مكتوبة من المالك أو من مصدر تشغيلي معتمد. بيانات Demo وSmoke للاختبار فقط.',
            'en' => 'Confirm that company, branch, reason, and financial values come from the owner or an approved operational source. Demo and smoke data are test-only.',
        ],
        'warnings' => [
            'ar' => 'لا تعتمد على النسبة وحدها كإشارة إطلاق. Production/UAT والطباعة والأجهزة والتشغيل الفعلي لها بوابات قبول مستقلة.',
            'en' => 'Do not treat the percentage alone as a launch signal. Production/UAT, printing, devices, and live operations have separate acceptance gates.',
        ],
        'errors' => [
            'ar' => 'إذا لم تتغير الحالة بعد الحفظ، راجع حالة السجل وتاريخ السريان والاعتماد المرتبط به، ولا تنشئ قيمة بديلة تلقائيًا.',
            'en' => 'If the status does not change after saving, review the record state, effective dates, and linked approval instead of creating an automatic fallback.',
        ],
        'next_step' => [
            'ar' => 'افتح البطاقة الإلزامية التالية، أدخل البيانات الحقيقية، ثم عد إلى هذه الصفحة للتأكد من تغير الحالة. بعد اكتمال الإعداد، انتقل إلى مراجعة UAT بدل اعتبار الشاشة توقيعًا إنتاجيًا.',
            'en' => 'Open the next required card, enter real data, and return here to confirm the state changed. After setup, move to UAT review rather than treating this page as production sign-off.',
        ],
        'faq' => [
            'ar' => 'هل تنشئ الصفحة بيانات افتراضية؟ لا. هل تمنع تسجيل الدخول؟ لا. هل تجعل النسخة المالية فعالة فورًا؟ لا، الاعتماد والسريان الزمني مطلوبان. هل يمكن للمستخدم العادي فتحها؟ لا، الوصول محمي بصلاحية company_settings.edit.',
            'en' => 'Does the page create defaults? No. Does it block login? No. Does a saved financial version become active immediately? No, approval and effective dates are required. Can a regular user open it? No, access requires company_settings.edit.',
        ],
    ],
    'tour_steps' => [
        [
            'key' => 'hero',
            'selector' => '[data-guide="initial-setup-hero"]',
            'title' => [
                'ar' => 'هذه هي نقطة البداية',
                'en' => 'This is your starting point',
            ],
            'body' => [
                'ar' => 'تعرف هنا على هدف الإعداد وحدود البيانات المسموح بها قبل أن تبدأ.',
                'en' => 'Start here to understand the setup mission and the boundaries around allowed data.',
            ],
        ],
        [
            'key' => 'summary',
            'selector' => '[data-guide="initial-setup-summary"]',
            'title' => [
                'ar' => 'تابع التقدم',
                'en' => 'Track progress',
            ],
            'body' => [
                'ar' => 'هذه المنطقة تلخص الخطوات الإلزامية وتوضح لك أول إجراء مقترح.',
                'en' => 'This area summarizes required steps and points you to the next recommended action.',
            ],
        ],
        [
            'key' => 'next',
            'selector' => '[data-guide="initial-setup-next-step"]',
            'title' => [
                'ar' => 'اضغط هنا للمتابعة',
                'en' => 'Continue from here',
            ],
            'body' => [
                'ar' => 'افتح شاشة الإدخال التالية بدل البحث عنها من القائمة الجانبية.',
                'en' => 'Open the next data-entry screen directly instead of searching the sidebar.',
            ],
        ],
        [
            'key' => 'cards',
            'selector' => '[data-guide="initial-setup-steps"]',
            'title' => [
                'ar' => 'استخدم البطاقات بالترتيب',
                'en' => 'Use the cards in order',
            ],
            'body' => [
                'ar' => 'راجع كل بطاقة، ثم استخدم زر فتح الخطوة أو مراجعتها حسب حالتها.',
                'en' => 'Review each card and use Open step or Review step according to its state.',
            ],
        ],
        [
            'key' => 'financial',
            'selector' => '[data-guide="initial-setup-step-financial-settings"]',
            'title' => [
                'ar' => 'لا تتجاوز الاعتماد المالي',
                'en' => 'Do not bypass financial approval',
            ],
            'body' => [
                'ar' => 'الإدخال المالي قد يبقى قيد الاعتماد. هذا طبيعي ولا يعني أنه جاهز للتشغيل.',
                'en' => 'A financial input may remain pending. That is expected and does not make it operational.',
            ],
        ],
        [
            'key' => 'safety',
            'selector' => '[data-guide="initial-setup-safety"]',
            'title' => [
                'ar' => 'آخر تذكير قبل الإنهاء',
                'en' => 'Final safety reminder',
            ],
            'body' => [
                'ar' => 'أكمل القيم الحقيقية فقط، واترك UAT وProduction لمساراتهما الرسمية.',
                'en' => 'Enter real values only, and keep UAT and Production on their formal approval paths.',
            ],
        ],
    ],
    'updated_at' => '2026-08-07',
    'version' => '1.0',
    'screen_id' => 'UI-ADM-013',
];
