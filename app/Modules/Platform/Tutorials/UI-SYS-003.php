<?php

return [
    'route_names' => [
        0 => 'admin.audit',
    ],
    'title' => [
        'ar' => 'سجل التدقيق',
        'en' => 'Audit Logs',
    ],
    'purpose' => [
        'ar' => 'تراجع الأحداث المسموح بها للتتبع والدعم.',
        'en' => 'Review permitted events for traceability and support.',
    ],
    'when_to_use' => [
        'ar' => 'استخدم هذه الشاشة عندما تكون مهمتك الحالية مرتبطة بهذا السجل أو الإجراء.',
        'en' => 'Use this screen when your current task relates to this record or operation.',
    ],
    'permissions' => [
        0 => 'audit_logs.view',
    ],
    'approved_actions' => [
        0 => [
            'key' => 'audit_logs.view',
            'label' => [
                'ar' => 'عرض سجلات التدقيق وصحة النظام',
                'en' => 'View audit logs and system health',
            ],
            'required_permission' => 'audit_logs.view',
        ],
    ],
    'stories' => [
        0 => 'US-046',
    ],
    'flows' => [
        0 => 'FLW-SYS-01',
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
                'selector' => '[data-guide="audit-header"]',
                'title' => [
                    'ar' => 'رأس سجل التدقيق التشغيلي',
                    'en' => 'Audit Log Header',
                ],
                'body' => [
                    'ar' => 'سجل تتبع الحركات التاريخية المحمي والمحفوظ دون إمكانية للحذف.',
                    'en' => 'Append-only operational history tracking system activity securely.',
                ],
            ],
            1 => [
                'key' => 'step-2',
                'selector' => '[data-guide="audit-filters"]',
                'title' => [
                    'ar' => 'خيارات تصفية السجل المتقدمة',
                    'en' => 'Audit Search & Filter Toolbar',
                ],
                'body' => [
                    'ar' => 'تصفية الأحداث حسب التصنيف، الحدث، المستخدم، الفرع، المتجر، أو الفترة.',
                    'en' => 'Filter events by category, event type, actor, branch, store, or date range.',
                ],
            ],
            2 => [
                'key' => 'step-3',
                'selector' => '[data-guide="audit-table"]',
                'title' => [
                    'ar' => 'جدول أحداث سجل التدقيق',
                    'en' => 'Audit Events Registry Table',
                ],
                'body' => [
                    'ar' => 'عرض تاريخ ووقت الحركة، اسم الحدث، منفذ العملية، والفرع/المتجر.',
                    'en' => 'Inspect timestamp, event name, executing actor, source type, and scope.',
                ],
            ],
            3 => [
                'key' => 'step-4',
                'selector' => '[data-guide="audit-view-action"], [data-guide="audit-table"]',
                'title' => [
                    'ar' => 'معاينة تفاصيل الحركة المحمية',
                    'en' => 'View Audit Event Details Action',
                ],
                'body' => [
                    'ar' => 'فتح تفاصيل الحركة لمقارنة القيم قبل وبعد التعديل ومعرف الطلب.',
                    'en' => 'Open modal comparing protected before/after record states and correlation ID.',
                ],
            ],
            4 => [
                'key' => 'step-5',
                'selector' => '[data-guide="audit-pagination"], [data-guide="audit-filters"]',
                'title' => [
                    'ar' => 'تصفح صفحات سجل التدقيق',
                    'en' => 'Audit Events Log Pagination',
                ],
                'body' => [
                    'ar' => 'التنقل في صفحات سجل الأحداث بكفاءة ودون تحميل زائد.',
                    'en' => 'Browse paginated audit log entries without performance impact.',
                ],
            ],
        ],
        'fields' => [
            0 => [
                'key' => 'field-1',
                'title' => [
                    'ar' => 'بيانات الحدث والمستخدم',
                    'en' => 'Event & User Info',
                ],
                'body' => [
                    'ar' => 'اسم الحدث، اسم المستخدم المنفذ، تاريخ ووقت التنفيذ، ومعرف الطلب.',
                    'en' => 'Event name, executing user, timestamp, and request correlation ID.',
                ],
            ],
            1 => [
                'key' => 'field-2',
                'title' => [
                    'ar' => 'القيم السابقة والجديدة',
                    'en' => 'Before & After Values',
                ],
                'body' => [
                    'ar' => 'مقارنة دقيقة للتغييرات في الحقول المعدلة دون كشف الأسرار.',
                    'en' => 'Detailed diff of modified fields without exposing system secrets.',
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
            'selector' => '[data-guide="audit-header"]',
            'title' => [
                'ar' => 'رأس سجل التدقيق التشغيلي',
                'en' => 'Audit Log Header',
            ],
            'body' => [
                'ar' => 'سجل تتبع الحركات التاريخية المحمي والمحفوظ دون إمكانية للحذف.',
                'en' => 'Append-only operational history tracking system activity securely.',
            ],
        ],
        1 => [
            'key' => 'step-2',
            'selector' => '[data-guide="audit-filters"]',
            'title' => [
                'ar' => 'خيارات تصفية السجل المتقدمة',
                'en' => 'Audit Search & Filter Toolbar',
            ],
            'body' => [
                'ar' => 'تصفية الأحداث حسب التصنيف، الحدث، المستخدم، الفرع، المتجر، أو الفترة.',
                'en' => 'Filter events by category, event type, actor, branch, store, or date range.',
            ],
        ],
        2 => [
            'key' => 'step-3',
            'selector' => '[data-guide="audit-table"]',
            'title' => [
                'ar' => 'جدول أحداث سجل التدقيق',
                'en' => 'Audit Events Registry Table',
            ],
            'body' => [
                'ar' => 'عرض تاريخ ووقت الحركة، اسم الحدث، منفذ العملية، والفرع/المتجر.',
                'en' => 'Inspect timestamp, event name, executing actor, source type, and scope.',
            ],
        ],
        3 => [
            'key' => 'step-4',
            'selector' => '[data-guide="audit-view-action"], [data-guide="audit-table"]',
            'title' => [
                'ar' => 'معاينة تفاصيل الحركة المحمية',
                'en' => 'View Audit Event Details Action',
            ],
            'body' => [
                'ar' => 'فتح تفاصيل الحركة لمقارنة القيم قبل وبعد التعديل ومعرف الطلب.',
                'en' => 'Open modal comparing protected before/after record states and correlation ID.',
            ],
        ],
        4 => [
            'key' => 'step-5',
            'selector' => '[data-guide="audit-pagination"], [data-guide="audit-filters"]',
            'title' => [
                'ar' => 'تصفح صفحات سجل التدقيق',
                'en' => 'Audit Events Log Pagination',
            ],
            'body' => [
                'ar' => 'التنقل في صفحات سجل الأحداث بكفاءة ودون تحميل زائد.',
                'en' => 'Browse paginated audit log entries without performance impact.',
            ],
        ],
    ],
    'updated_at' => '2026-08-04',
    'version' => '1.0',
    'screen_id' => 'UI-SYS-003',
];
