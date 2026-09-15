<?php

return [
    'route_names' => [
        0 => 'admin.authorization-baseline',
    ],
    'title' => [
        'ar' => 'الأدوار',
        'en' => 'Roles',
    ],
    'purpose' => [
        'ar' => 'تراجع الأدوار القياسية وملخص التعيينات.',
        'en' => 'Review canonical roles and assignment summaries.',
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
                'ar' => 'عرض الأدوار القياسية والتعيينات',
                'en' => 'View canonical roles and assignments',
            ],
            'required_permission' => 'users_roles_permissions.view',
        ],
        1 => [
            'key' => 'users_roles_permissions.edit',
            'label' => [
                'ar' => 'تعديل مصفوفة صلاحيات الأدوار',
                'en' => 'Edit role permission mappings',
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
                    'ar' => 'عنوان سياسات التفويض القياسية',
                    'en' => 'Authorization Policy Title',
                ],
                'body' => [
                    'ar' => 'نظام أدوار موحد يدعم التحكم الدقيق بالصلاحيات والتدقيق الشامل.',
                    'en' => 'Role-based access control baseline supporting full action auditability.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="auth-roles-card"]',
                'title' => [
                    'ar' => 'بطاقة الأدوار القياسية المعتمدة',
                    'en' => 'Canonical Roles Catalog Card',
                ],
                'body' => [
                    'ar' => 'عرض عدد الأدوار الأساسية الجاهزة (مدير، كاشير، مشتريات، إلخ).',
                    'en' => 'View total count of canonical role definitions in the baseline.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="auth-overview"]',
                'title' => [
                    'ar' => 'ملخص سياسة الأمان والتحقق',
                    'en' => 'Security Policy Overview Section',
                ],
                'body' => [
                    'ar' => 'مراجعة آليات تطبيق الصلاحيات بالخادم وقواعد الحماية المعتمدة.',
                    'en' => 'Review role policy enforcement, scope protection, and server gate rules.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="auth-users-table"]',
                'title' => [
                    'ar' => 'جدول توزيع الأدوار على الحسابات',
                    'en' => 'User Role Assignment Directory',
                ],
                'body' => [
                    'ar' => 'متابعة توزيع الأدوار القياسية على الحسابات الفردية لضمان الأمان.',
                    'en' => 'Monitor canonical role assignments across individual user accounts.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="auth-scopes-card"]',
                'title' => [
                    'ar' => 'بطاقة تعيينات نطاقات الوصول',
                    'en' => 'Scope Assignments Summary Card',
                ],
                'body' => [
                    'ar' => 'استعراض إجمالي قيود الوصول المطبقة على الفروع والمتاجر.',
                    'en' => 'Inspect total active branch and store scope restriction records.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'كود واسم الدور',
                    'en' => 'Role Code & Name',
                ],
                'body' => [
                    'ar' => 'الرمز القياسي والدور الوظيفي المعتمد في الهيكل التنظيمي.',
                    'en' => 'Standard code and job role name within the organizational matrix.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'نطاق الصلاحيات المسندة',
                    'en' => 'Mapped Permission Scope',
                ],
                'body' => [
                    'ar' => 'ملخص الوظائف والإجراءات المسموح بها لمستخدمي هذا الدور.',
                    'en' => 'Summary of functional actions and capabilities granted to role users.',
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
                'ar' => 'عنوان سياسات التفويض القياسية',
                'en' => 'Authorization Policy Title',
            ],
            'body' => [
                'ar' => 'نظام أدوار موحد يدعم التحكم الدقيق بالصلاحيات والتدقيق الشامل.',
                'en' => 'Role-based access control baseline supporting full action auditability.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="auth-roles-card"]',
            'title' => [
                'ar' => 'بطاقة الأدوار القياسية المعتمدة',
                'en' => 'Canonical Roles Catalog Card',
            ],
            'body' => [
                'ar' => 'عرض عدد الأدوار الأساسية الجاهزة (مدير، كاشير، مشتريات، إلخ).',
                'en' => 'View total count of canonical role definitions in the baseline.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="auth-overview"]',
            'title' => [
                'ar' => 'ملخص سياسة الأمان والتحقق',
                'en' => 'Security Policy Overview Section',
            ],
            'body' => [
                'ar' => 'مراجعة آليات تطبيق الصلاحيات بالخادم وقواعد الحماية المعتمدة.',
                'en' => 'Review role policy enforcement, scope protection, and server gate rules.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="auth-users-table"]',
            'title' => [
                'ar' => 'جدول توزيع الأدوار على الحسابات',
                'en' => 'User Role Assignment Directory',
            ],
            'body' => [
                'ar' => 'متابعة توزيع الأدوار القياسية على الحسابات الفردية لضمان الأمان.',
                'en' => 'Monitor canonical role assignments across individual user accounts.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="auth-scopes-card"]',
            'title' => [
                'ar' => 'بطاقة تعيينات نطاقات الوصول',
                'en' => 'Scope Assignments Summary Card',
            ],
            'body' => [
                'ar' => 'استعراض إجمالي قيود الوصول المطبقة على الفروع والمتاجر.',
                'en' => 'Inspect total active branch and store scope restriction records.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-ADM-011',
];
