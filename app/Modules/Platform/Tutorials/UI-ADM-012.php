<?php

return [
    'route_names' => [
        0 => 'admin.authorization-baseline',
    ],
    'title' => [
        'ar' => 'الصلاحيات',
        'en' => 'Permissions',
    ],
    'purpose' => [
        'ar' => 'تراجع مصفوفة الصلاحيات ضمن نطاق الإدارة المعتمد.',
        'en' => 'Review the permission matrix within the approved administration scope.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'users_roles_permissions.view',
        1 => 'users_roles_permissions.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'users_roles_permissions.view',
            'label' => [
                'ar' => 'عرض مصفوفة الصلاحيات وسياسات الحماية',
                'en' => 'View permission matrix and policy guards',
            ],
            'required_permission' => 'users_roles_permissions.view',
        ],
        1 => [
            'key' => 'users_roles_permissions.edit',
            'label' => [
                'ar' => 'مراجعة وتعديل مصفوفة الوصول',
                'en' => 'Review and adjust permission matrix',
            ],
            'required_permission' => 'users_roles_permissions.edit',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-ADM-04',
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
                'selector' => '[data-guide="auth-header"]',
                'title' => [
                    'ar' => 'رأس مصفوفة الصلاحيات المعتمدة',
                    'en' => 'Permissions Baseline Header',
                ],
                'body' => [
                    'ar' => 'مراجعة قائمة الصلاحيات الخادمية المسجلة لحماية موارد المنصة.',
                    'en' => 'Review registered server capability permissions guarding platform resources.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="auth-permissions-card"]',
                'title' => [
                    'ar' => 'بطاقة إحصاء الصلاحيات القياسية',
                    'en' => 'Canonical Permissions Matrix Card',
                ],
                'body' => [
                    'ar' => 'عرض عدد الصلاحيات المودعة للتحكم بالإجراءات التشغيلية.',
                    'en' => 'View count of seeded canonical permissions protecting system modules.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="auth-overview"]',
                'title' => [
                    'ar' => 'قيود حماية العمليات بالخادم',
                    'en' => 'Server Authorization Policy Banner',
                ],
                'body' => [
                    'ar' => 'التأكد من خضوع كافة الحركات والطلبات لسياسات الخادم (Policies/Gates).',
                    'en' => 'Verify that all sensitive application actions require server policy checks.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="auth-users-table"]',
                'title' => [
                    'ar' => 'مراجعة صلاحيات المستخدمين',
                    'en' => 'User Permissions Verification List',
                ],
                'body' => [
                    'ar' => 'مراجعة نتائج توزيع الصلاحيات الفعلية على مستخدمي النظام.',
                    'en' => 'Verify permissions effective distribution across authenticated users.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="auth-scopes-card"]',
                'title' => [
                    'ar' => 'حدود نطاقات الفروع والمتاجر',
                    'en' => 'Branch & Store Scope Guard Card',
                ],
                'body' => [
                    'ar' => 'تأكيد حظر وصول المستخدم لبث البيانات خارج نطاق فرعه أو متجره.',
                    'en' => 'Confirm user access isolation strictly enforced by assigned scope.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'مفتاح الصلاحية والوحدة',
                    'en' => 'Permission Key & Module',
                ],
                'body' => [
                    'ar' => 'رمز الصلاحية الخادمي والوحدة التشغيلية التابعة لها.',
                    'en' => 'Server permission identifier key and parent system module.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'مستوى الحساسية والحماية',
                    'en' => 'Sensitivity & Guard Level',
                ],
                'body' => [
                    'ar' => 'درجة خطورة الصلاحية والسياسة الخادمية المطبقة عليها.',
                    'en' => 'Risk level of the capability and associated server policy guard.',
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
            'selector' => '[data-guide="auth-header"]',
            'title' => [
                'ar' => 'رأس مصفوفة الصلاحيات المعتمدة',
                'en' => 'Permissions Baseline Header',
            ],
            'body' => [
                'ar' => 'مراجعة قائمة الصلاحيات الخادمية المسجلة لحماية موارد المنصة.',
                'en' => 'Review registered server capability permissions guarding platform resources.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="auth-permissions-card"]',
            'title' => [
                'ar' => 'بطاقة إحصاء الصلاحيات القياسية',
                'en' => 'Canonical Permissions Matrix Card',
            ],
            'body' => [
                'ar' => 'عرض عدد الصلاحيات المودعة للتحكم بالإجراءات التشغيلية.',
                'en' => 'View count of seeded canonical permissions protecting system modules.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="auth-overview"]',
            'title' => [
                'ar' => 'قيود حماية العمليات بالخادم',
                'en' => 'Server Authorization Policy Banner',
            ],
            'body' => [
                'ar' => 'التأكد من خضوع كافة الحركات والطلبات لسياسات الخادم (Policies/Gates).',
                'en' => 'Verify that all sensitive application actions require server policy checks.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="auth-users-table"]',
            'title' => [
                'ar' => 'مراجعة صلاحيات المستخدمين',
                'en' => 'User Permissions Verification List',
            ],
            'body' => [
                'ar' => 'مراجعة نتائج توزيع الصلاحيات الفعلية على مستخدمي النظام.',
                'en' => 'Verify permissions effective distribution across authenticated users.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="auth-scopes-card"]',
            'title' => [
                'ar' => 'حدود نطاقات الفروع والمتاجر',
                'en' => 'Branch & Store Scope Guard Card',
            ],
            'body' => [
                'ar' => 'تأكيد حظر وصول المستخدم لبث البيانات خارج نطاق فرعه أو متجره.',
                'en' => 'Confirm user access isolation strictly enforced by assigned scope.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-ADM-012',
];
