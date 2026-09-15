<?php

return [
    'route_names' => [
        0 => 'admin.authorization-baseline',
    ],
    'title' => [
        'ar' => 'المستخدمون',
        'en' => 'Users',
    ],
    'purpose' => [
        'ar' => 'تدير المستخدمين والنطاقات المعتمدة.',
        'en' => 'Manage users and approved scopes.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'users_roles_permissions.view',
        1 => 'users_roles_permissions.create',
        2 => 'users_roles_permissions.edit',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'users_roles_permissions.view',
            'label' => [
                'ar' => 'عرض قائمة المستخدمين والنطاقات',
                'en' => 'View users and scopes',
            ],
            'required_permission' => 'users_roles_permissions.view',
        ],
        1 => [
            'key' => 'users_roles_permissions.create',
            'label' => [
                'ar' => 'إضافة حساب مستخدم جديد',
                'en' => 'Create new user account',
            ],
            'required_permission' => 'users_roles_permissions.create',
        ],
        2 => [
            'key' => 'users_roles_permissions.edit',
            'label' => [
                'ar' => 'تعديل أدوار ونطاقات المستخدم',
                'en' => 'Edit user roles and scopes',
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
                    'ar' => 'رأس إدارة الصلاحيات الأساسية',
                    'en' => 'Authorization Baseline Header',
                ],
                'body' => [
                    'ar' => 'إدارة أدوار المستخدمين ونطاقات الوصول على مستوى الفروع والمتاجر.',
                    'en' => 'Manage platform user roles, permissions, and branch/store scopes.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="auth-users-card"]',
                'title' => [
                    'ar' => 'بطاقة إحصاء المستخدمين المسجلين',
                    'en' => 'Registered Users Stat Card',
                ],
                'body' => [
                    'ar' => 'عرض الإجمالي الكلي للمستخدمين النشطين والمسجلين في النظام.',
                    'en' => 'View total registered users available for role and scope management.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="auth-users-search"]',
                'title' => [
                    'ar' => 'البحث الفوري عن المستخدمين',
                    'en' => 'Search Users Input Filter',
                ],
                'body' => [
                    'ar' => 'البحث عن مستخدم معين بالاسم أو البريد الإلكتروني المعتمد.',
                    'en' => 'Filter user inventory instantly by name or email address.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="auth-users-table"]',
                'title' => [
                    'ar' => 'جدول حسابات المستخدمين والأدوار',
                    'en' => 'Users Access Inventory Table',
                ],
                'body' => [
                    'ar' => 'استعراض أسماء المستخدمين والبريد، الأدوار المسندة، وحالة التحقيق.',
                    'en' => 'Inspect user accounts, email verification status, assigned roles, and actions.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="auth-users-manage-action"], [data-guide="auth-users-table"]',
                'title' => [
                    'ar' => 'إدارة صلاحيات ونطاق المستخدم',
                    'en' => 'Manage User Authorization Action',
                ],
                'body' => [
                    'ar' => 'فتح نافذة تعيين الأدوار والنطاقات المسموح بها للمستخدم المحدد.',
                    'en' => 'Open modal to assign canonical roles and branch/store scopes to a user.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'هوية المستخدم والاتصال',
                    'en' => 'User Identity & Contact',
                ],
                'body' => [
                    'ar' => 'الاسم الكامل والبريد الإلكتروني المعتمد لتسجيل الدخول والإشعارات.',
                    'en' => 'Full name and approved email address used for login and notifications.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'الأدوار والنطاقات المعتمدة',
                    'en' => 'Assigned Roles & Scopes',
                ],
                'body' => [
                    'ar' => 'الأدوار القياسية المسندة ونطاق الفروع والمتاجر المصرح بها للمستخدم.',
                    'en' => 'Assigned canonical roles and permitted branch/store operational scopes.',
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
                'ar' => 'رأس إدارة الصلاحيات الأساسية',
                'en' => 'Authorization Baseline Header',
            ],
            'body' => [
                'ar' => 'إدارة أدوار المستخدمين ونطاقات الوصول على مستوى الفروع والمتاجر.',
                'en' => 'Manage platform user roles, permissions, and branch/store scopes.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="auth-users-card"]',
            'title' => [
                'ar' => 'بطاقة إحصاء المستخدمين المسجلين',
                'en' => 'Registered Users Stat Card',
            ],
            'body' => [
                'ar' => 'عرض الإجمالي الكلي للمستخدمين النشطين والمسجلين في النظام.',
                'en' => 'View total registered users available for role and scope management.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="auth-users-search"]',
            'title' => [
                'ar' => 'البحث الفوري عن المستخدمين',
                'en' => 'Search Users Input Filter',
            ],
            'body' => [
                'ar' => 'البحث عن مستخدم معين بالاسم أو البريد الإلكتروني المعتمد.',
                'en' => 'Filter user inventory instantly by name or email address.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="auth-users-table"]',
            'title' => [
                'ar' => 'جدول حسابات المستخدمين والأدوار',
                'en' => 'Users Access Inventory Table',
            ],
            'body' => [
                'ar' => 'استعراض أسماء المستخدمين والبريد، الأدوار المسندة، وحالة التحقيق.',
                'en' => 'Inspect user accounts, email verification status, assigned roles, and actions.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="auth-users-manage-action"], [data-guide="auth-users-table"]',
            'title' => [
                'ar' => 'إدارة صلاحيات ونطاق المستخدم',
                'en' => 'Manage User Authorization Action',
            ],
            'body' => [
                'ar' => 'فتح نافذة تعيين الأدوار والنطاقات المسموح بها للمستخدم المحدد.',
                'en' => 'Open modal to assign canonical roles and branch/store scopes to a user.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-ADM-010',
];
