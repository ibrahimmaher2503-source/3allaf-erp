<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class UserFlowRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $flows = [
            'FLW-UI-01' => self::flow(
                ['ar' => 'تخصيص المظهر', 'en' => 'Customize Interface'],
                ['ar' => 'مستخدم مصادق عليه', 'en' => 'Authenticated user'],
                ['UI-SYS-001'],
                ['appearance.edit'],
                [
                    ['ar' => 'فتح أداة تخصيص المظهر من الشاشات المتاحة.', 'en' => 'Open the appearance customizer from available screens.'],
                    ['ar' => 'اختر المظهر (فاتح/داكن/نظام) واللون المميز وكثافة الجداول.', 'en' => 'Choose appearance (light/dark/system), accent color, and table density.'],
                    ['ar' => 'احفظ التفضيلات للتحقق من استمرارها عبر الجلسات.', 'en' => 'Save preferences and verify persistence across user sessions.'],
                    ['ar' => 'استخدم خيار إعادة الإعدادات الافتراضية عند الحاجة.', 'en' => 'Use reset option to restore system defaults when needed.'],
                ],
                'UI-SYS-001',
            ),
            'FLW-HELP-01' => self::flow(
                ['ar' => 'فتح دليل الصفحة', 'en' => 'Open Contextual Page Guide'],
                ['ar' => 'مستخدم مصادق عليه', 'en' => 'Authenticated user'],
                ['UI-SYS-001', 'UI-CAT-001', 'UI-CAT-004'],
                [],
                [
                    ['ar' => 'افتح أي شاشة مسجلة في النظام.', 'en' => 'Open any registered application screen.'],
                    ['ar' => 'يحدد النظام كود الشاشة والسياق المصرح به تلقائياً.', 'en' => 'System automatically resolves Screen ID and safe context.'],
                    ['ar' => 'افتح درج دليل الصفحة لمراجعة الخطوات والحقول الإرشادية.', 'en' => 'Open Page Guide drawer to review steps and field guidance.'],
                    ['ar' => 'انتقل إلى الدليل الكامل أو تدفق المستخدم المرتبط عند الحاجة.', 'en' => 'Navigate to the full guide or related user flow when needed.'],
                ],
                'FLW-HELP-02',
            ),
            'FLW-HELP-02' => self::flow(
                ['ar' => 'تشغيل الجولة الإرشادية', 'en' => 'Run Guided Tutorial'],
                ['ar' => 'مستخدم مصادق عليه', 'en' => 'Authenticated user'],
                ['UI-SYS-001'],
                [],
                [
                    ['ar' => 'افتح أداة الدليل واضغط بدء الجولة التفاعلية.', 'en' => 'Open the guide tool and click Start Interactive Tour.'],
                    ['ar' => 'يتنقل المشغل بين العناصر المتاحة في الشاشة الحالية.', 'en' => 'Tour steps highlight visible target elements on current screen.'],
                    ['ar' => 'يتم التجاوز الآمن للعناصر غير المتوفرة على الشاشة.', 'en' => 'Missing or unavailable targets are safely skipped.'],
                    ['ar' => 'أنه الجولة أو اغلقها لإعادة تركيز المتصفح إلى مكانه الأصلي.', 'en' => 'Finish or exit tour to restore focus to original element.'],
                ],
                null,
            ),
            'FLW-CAT-01' => self::flow(
                ['ar' => 'إدارة المنتج', 'en' => 'Manage a Product'],
                ['ar' => 'مستخدم الكتالوج المصرح له', 'en' => 'Authorized catalog user'],
                ['UI-CAT-001', 'UI-CAT-002', 'UI-CAT-003'],
                ['products_categories_brands.view'],
                [
                    ['ar' => 'افتح قائمة المنتجات المسجلة.', 'en' => 'Open the registered product list.'],
                    ['ar' => 'ابحث بالباركود أو كود المنتج ضمن نطاق الصلاحيات.', 'en' => 'Search by barcode or item code within authorized scope.'],
                    ['ar' => 'افتح بطاقة التفاصيل أو نموذج الإنشاء والتعديل المصرح به.', 'en' => 'Open product details card or authorized create/edit form.'],
                    ['ar' => 'احفظ التغييرات فقط بعد نجاح التحقق وقواعد المجال.', 'en' => 'Save changes only after validation and domain rules succeed.'],
                ],
                null,
            ),
            'FLW-CAT-02' => self::flow(
                ['ar' => 'استيراد المنتجات المرحلي', 'en' => 'Stage Product Import'],
                ['ar' => 'مستخدم الاستيراد المصرح له', 'en' => 'Authorized import user'],
                ['UI-CAT-004'],
                ['products_categories_brands.create', 'products_categories_brands.approve'],
                [
                    ['ar' => 'ارفع ملف الاستيراد بالقالب المعتمد مع تحديد نمط المعالجة.', 'en' => 'Upload import spreadsheet in approved template with chosen mode.'],
                    ['ar' => 'راجع نتائج مرحلة التحقق والصفوف الصالحة والمرفوضة.', 'en' => 'Review validation staging results, valid rows, and rejects.'],
                    ['ar' => 'صحح الصفوف المرفوضة أو نزّل تقرير الأخطاء قبل الاعتماد.', 'en' => 'Fix rejected rows or download error report before approval.'],
                    ['ar' => 'اعتمد الدفعة عندما تكون جميع الصفوف صالحة فقط.', 'en' => 'Approve batch only when all rows are valid.'],
                ],
                null,
            ),
            'FLW-ADM-01' => self::flow(
                ['ar' => 'إدارة الفروع', 'en' => 'Manage Branches'],
                ['ar' => 'مسؤول النظام المصرح له', 'en' => 'Authorized administrator'],
                ['UI-ADM-003'],
                ['branches_stores.view'],
                [
                    ['ar' => 'مراجعة قائمة الفروع المعتمدة وحالتها.', 'en' => 'Review the approved branch list and status.'],
                    ['ar' => 'إضافة فرع جديد أو تعديل بيانات الفرع عند توفر الصلاحية.', 'en' => 'Create new branch or edit details when authorized.'],
                    ['ar' => 'حفظ التغييرات بعد نجاح التحقق من كود الفرع وبياناته.', 'en' => 'Save changes after validation of the branch code and details.'],
                ],
                null,
            ),
            'FLW-ADM-02' => self::flow(
                ['ar' => 'إدارة المخازن ونقاط البيع', 'en' => 'Manage Warehouses and Points of Sale'],
                ['ar' => 'مسؤول النظام المصرح له', 'en' => 'Authorized administrator'],
                ['UI-ADM-004'],
                ['branches_stores.view'],
                [
                    ['ar' => 'مراجعة قائمة مواقع التخزين المسجلة.', 'en' => 'Review registered stock locations.'],
                    ['ar' => 'تحديد نقطة البيع الأساسية لكل فرع.', 'en' => 'Choose the primary point of sale for each branch.'],
                    ['ar' => 'حفظ التغيير مع الاحتفاظ بالسجل السابق.', 'en' => 'Save the change while keeping the previous history.'],
                ],
                null,
            ),
            'FLW-ADM-03' => self::flow(
                ['ar' => 'إدارة الأدراج', 'en' => 'Manage Cash Drawers'],
                ['ar' => 'مسؤول النظام المصرح له', 'en' => 'Authorized administrator'],
                ['UI-ADM-005'],
                ['drawers_payments_tax_numbering_printers.view'],
                [
                    ['ar' => 'مراجعة تعينات أدراج النقدية للفرع والمتجر.', 'en' => 'Review drawer assignments per branch and store.'],
                    ['ar' => 'إضافة درج أو تعديل التعيين مع عدم وجود وردية مفتوحة.', 'en' => 'Add drawer or edit assignment ensuring no open shift conflict.'],
                    ['ar' => 'حفظ وتأكيد الحالة المعتمدة للدرج.', 'en' => 'Save and confirm the approved drawer assignment.'],
                ],
                null,
            ),
            'FLW-ADM-04' => self::flow(
                ['ar' => 'مراجعة التفويض', 'en' => 'Review Authorization'],
                ['ar' => 'مسؤول النظام أو المراجع المصرح له', 'en' => 'Authorized administrator or reviewer'],
                ['UI-ADM-010', 'UI-ADM-011', 'UI-ADM-012'],
                ['users_roles_permissions.view'],
                [
                    ['ar' => 'فتح شاشات إدارة المستخدمين والأدوار والصلاحيات.', 'en' => 'Open user, role, and permission administration screens.'],
                    ['ar' => 'مراجعة نطاق الصلاحيات الخادمية ومصفوفة الوصول.', 'en' => 'Review server authority scope and permission matrix.'],
                    ['ar' => 'حفظ التعديلات المصرح بها مع منع قفل المسؤول الأخير.', 'en' => 'Save authorized edits while preventing last-admin lockout.'],
                ],
                null,
            ),
            'FLW-ADM-05' => self::flow(
                ['ar' => 'مراجعة إعدادات الشركة', 'en' => 'Review Company Settings'],
                ['ar' => 'مسؤول النظام أو المراجع المصرح له', 'en' => 'Authorized administrator or reviewer'],
                ['UI-ADM-002'],
                ['company_settings.view'],
                [
                    ['ar' => 'مراجعة إعدادات الهوية والضرائب والترقيم والدفع والطابعات.', 'en' => 'Review company identity, tax, numbering, payment, and printer settings.'],
                    ['ar' => 'تعديل الحقول المسموح بها وفق السياسة التشغيلية.', 'en' => 'Edit permitted fields according to operational policy.'],
                    ['ar' => 'حفظ الإعدادات وتدوين أحداث التغيير في سجل التدقيق.', 'en' => 'Save settings and record change events in audit log.'],
                ],
                null,
            ),
            'FLW-ADM-06' => self::flow(
                ['ar' => 'الإعداد الأولي والتجهيز للتشغيل', 'en' => 'Complete Initial Setup'],
                ['ar' => 'مالك النظام أو مسؤول النظام المصرح له', 'en' => 'System owner or authorized administrator'],
                ['UI-ADM-013', 'UI-ADM-002', 'UI-ADM-003', 'UI-ADM-004', 'UI-ADM-012', 'UI-ADM-005'],
                ['company_settings.edit'],
                [
                    ['ar' => 'افتح لوحة الإعداد الأولي واقرأ نسبة الخطوات الإلزامية وحالة كل بطاقة.', 'en' => 'Open Initial Setup and read the required-step percentage and each card state.'],
                    ['ar' => 'أدخل هوية الشركة ثم أنشئ الفروع والمتاجر الفعلية وفق بيانات المالك.', 'en' => 'Enter company identity, then create real branches and stores from owner-provided data.'],
                    ['ar' => 'أضف أسباب إرجاع الموردين وإعدادات الترقيم والطباعة، واحفظ المالي منها كقيد الاعتماد فقط.', 'en' => 'Add supplier-return reasons and numbering/print settings; save financial values as pending only.'],
                    ['ar' => 'راجع المستخدمين والأدوار والنطاقات، ثم انتظر اعتماد النسخ المالية قبل اعتبارها فعالة.', 'en' => 'Review users, roles, and scopes, then wait for financial approvals before treating them as active.'],
                    ['ar' => 'راجع الطابعات كخطوة اختيارية، ثم افصل نتيجة الجولة عن UAT وProduction sign-off.', 'en' => 'Review printers as an optional step, keeping the tour result separate from UAT and Production sign-off.'],
                ],
                null,
            ),
            'FLW-POS-SETUP' => self::flow(
                ['ar' => 'إعداد وتشغيل نقطة البيع', 'en' => 'Set Up and Run POS'],
                ['ar' => 'مسؤول النظام والكاشير، كلٌ ضمن صلاحياته ونطاقه', 'en' => 'System administrator and cashier, each within their own permission and scope'],
                ['UI-ADM-002', 'UI-ADM-003', 'UI-ADM-004', 'UI-ADM-005', 'UI-ADM-010', 'UI-ADM-012', 'UI-CAT-001', 'UI-INV-001', 'UI-CUS-001', 'UI-RET-005', 'UI-POS-003', 'UI-RET-001', 'UI-RET-002', 'UI-RET-003', 'UI-RET-004', 'UI-POS-007'],
                ['pos_sales.view'],
                [
                    ['ar' => 'سجّل الدخول وتحقق من الحساب قبل فتح أي شاشة تشغيلية.', 'en' => 'Sign in and verify your account before opening an operational screen.'],
                    ['ar' => 'راجع دور الكاشير وصلاحياته ونطاق الفرع والمتجر. لا يمنح الدليل أي صلاحية إضافية.', 'en' => 'Review the cashier role, permissions, and branch and store scope. The guide grants no additional access.'],
                    ['ar' => 'أكمل بيانات الشركة المصرح بها، بما في ذلك الترقيم والضرائب وطرق الدفع والطباعة حسب السياسة المعتمدة.', 'en' => 'Complete authorized company settings, including numbering, tax, payment methods, and printing under the approved policy.'],
                    ['ar' => 'أنشئ الفرع التشغيلي وتحقق من بياناته وحالته.', 'en' => 'Create the operating branch and confirm its details and status.'],
                    ['ar' => 'أنشئ متجر البيع واربطه بالفرع كمتجر بيع فعلي.', 'en' => 'Create the selling store and map it to the branch as an active selling store.'],
                    ['ar' => 'عيّن درجًا نقديًا متاحًا للكاشير ضمن الفرع والمتجر الصحيحين.', 'en' => 'Assign an available cash drawer to the cashier in the correct branch and store.'],
                    ['ar' => 'أضف منتجًا بلغتيه العربية والإنجليزية مع كود أو باركود صالح.', 'en' => 'Add a product with Arabic and English names plus a valid item code or barcode.'],
                    ['ar' => 'احفظ سعر بيع أساسيًا موجبًا في بطاقة المنتج ليصبح متاحًا فورًا في نقطة البيع.', 'en' => 'Save a positive base selling price on the Product Card to make it immediately available in POS.'],
                    ['ar' => 'تحقق من رصيد قابل للبيع في متجر البيع، وليس في متجر أو فرع آخر.', 'en' => 'Verify sellable stock in the selling store, not in another store or branch.'],
                    ['ar' => 'إذا استُخدم عميل اختياري، أنشئه وسجّل الموافقة المطلوبة قبل استخدام بياناته أو ولائه.', 'en' => 'When an optional customer is used, create the customer and record required consent before using personal or loyalty data.'],
                    ['ar' => 'راجع شاشة الجاهزية المالية كمرجع فقط. لا تستخدم صفحة الجاهزية لتنفيذ بيع أو لتجاوز قرار المالك.', 'en' => 'Review the financial readiness screen as reference only. Do not use a readiness page to make a sale or bypass an owner decision.'],
                    ['ar' => 'افتح وردية للكاشير على الدرج المعيّن وسجّل رصيد البداية المطلوب.', 'en' => 'Open a cashier shift on the assigned drawer and record the required opening float.'],
                    ['ar' => 'افتح نقطة البيع التشغيلية وتحقق من الفرع والمتجر والدرج والوردية قبل بدء السلة.', 'en' => 'Open the operational POS and verify branch, store, drawer, and shift before starting a cart.'],
                    ['ar' => 'ابحث بالاسم أو الكود أو الباركود، ثم أضف المنتج وراجع الكمية والسعر وإجمالي السطر.', 'en' => 'Search by name, item code, or barcode, then add the product and review quantity, price, and line total.'],
                    ['ar' => 'استخدم الخصم أو السعر المفتوح أو الضريبة فقط عند توفر الصلاحية والتحقق والموافقة المطلوبة.', 'en' => 'Use discount, open price, or tax only when the required permission, validation, and approval are available.'],
                    ['ar' => 'علّق السلة عند الحاجة. الاسترجاع يعيد التحقق من السعر والمخزون ولا ينشئ بيعًا وحده.', 'en' => 'Suspend a cart when needed. Retrieval revalidates price and stock and does not create a sale by itself.'],
                    ['ar' => 'اختر طريقة الدفع المتاحة، وأدخل المبلغ وأرفق دليل الدفع الإلكتروني عندما تطلبه السياسة.', 'en' => 'Choose an available payment method, enter the amount, and attach electronic payment evidence when policy requires it.'],
                    ['ar' => 'أكمل البيع مرة واحدة بعد نجاح التحقق. الإعادة أو التحديث لا تتجاوز فحوصات المخزون أو الصلاحيات.', 'en' => 'Complete the sale once validation succeeds. Retrying or refreshing does not bypass stock or authorization checks.'],
                    ['ar' => 'افتح سجل المبيعات ثم تفاصيل البيع لمراجعة المرجع والخطوط والإجمالي وحركة المخزون.', 'en' => 'Open sales history, then the sale detail to review the reference, lines, total, and inventory movement.'],
                    ['ar' => 'اطبع أو أعد طباعة الإيصال فقط من المسار المصرح، ثم راجع سجل التدقيق عند وجود استثناء.', 'en' => 'Print or reprint the receipt only through the authorized path, then review the audit trail when an exception occurred.'],
                    ['ar' => 'عند ظهور مانع، اقرأ الرسالة وحدد السبب: صلاحية أو نطاق أو وردية أو سعر أو مخزون أو دفعة أو دليل. صحح السبب بدل تجاوزه.', 'en' => 'When blocked, read the message and identify the cause: permission, scope, shift, price, stock, payment, or evidence. Correct the cause instead of bypassing it.'],
                ],
                'FLW-POS-01',
            ),
            'FLW-POS-01' => self::flow(
                ['ar' => 'إتمام بيع نقطة البيع', 'en' => 'Complete a POS Sale'],
                ['ar' => 'كاشير مصرح له في وردية نشطة', 'en' => 'Authorized cashier with an active shift'],
                ['UI-POS-003', 'UI-RET-001', 'UI-RET-002', 'UI-RET-003', 'UI-RET-004', 'UI-POS-007'],
                ['pos_sales.view'],
                [
                    ['ar' => 'تأكد من تسجيل الدخول ونطاق الفرع والمتجر والدرج والوردية النشطة.', 'en' => 'Confirm sign-in and the active branch, store, drawer, and shift scope.'],
                    ['ar' => 'ابحث عن منتج قابل للبيع بالاسم أو الكود أو الباركود وأضفه إلى السلة.', 'en' => 'Find a sellable product by name, item code, or barcode and add it to the cart.'],
                    ['ar' => 'راجع الكمية والسعر وإجمالي السطر، ثم أضف العميل الاختياري عند الحاجة.', 'en' => 'Review quantity, price, and line total, then add the optional customer when needed.'],
                    ['ar' => 'استخدم الخصم أو السعر المفتوح أو الضريبة ضمن الصلاحيات وقواعد الموافقة فقط.', 'en' => 'Use discount, open price, or tax only within the required permissions and approval rules.'],
                    ['ar' => 'علّق السلة أو استرجعها عند الحاجة، مع إعادة التحقق من السعر والمخزون قبل الإكمال.', 'en' => 'Suspend or retrieve the cart when needed, with price and stock revalidated before completion.'],
                    ['ar' => 'أدخل طرق الدفع والمبالغ، وأضف دليل الدفع الإلكتروني عند طلبه.', 'en' => 'Enter payment methods and amounts, and add electronic payment evidence when required.'],
                    ['ar' => 'أكمل البيع مرة واحدة بعد نجاح التحقق، ثم احتفظ بمرجع النتيجة المعتمدة.', 'en' => 'Complete the sale once validation succeeds, then retain the approved result reference.'],
                    ['ar' => 'راجع سجل المبيعات والتفاصيل والإيصال وحركة المخزون وسجل التدقيق عند وجود استثناء.', 'en' => 'Review sales history, detail, receipt, inventory movement, and audit trail when an exception occurred.'],
                ],
                null,
            ),
            'FLW-INV-01' => self::flow(
                ['ar' => 'مراجعة رصيد ودفتر المخزون', 'en' => 'Review Inventory Balance and Ledger'],
                ['ar' => 'مستخدم المخزون المصرح له', 'en' => 'Authorized inventory user'],
                ['UI-INV-001', 'UI-INV-002', 'UI-INV-003'],
                ['inventory_stock_card.view'],
                [
                    ['ar' => 'افتح مركز تحكم المخزون ضمن نطاق المتاجر المرئية.', 'en' => 'Open Inventory Control Center within visible store scope.'],
                    ['ar' => 'افتح بطاقة المنتج واقرأ on-hand وreserved وavailable وWAC عند السماح.', 'en' => 'Open the stock card and read on-hand, reserved, available, and WAC when permitted.'],
                    ['ar' => 'طابق الرصيد مع دفتر الحركات ومصدر كل movement.', 'en' => 'Reconcile the balance with the movement ledger and each movement source.'],
                ],
                null,
            ),
            'FLW-INV-02' => self::flow(
                ['ar' => 'دورة تحويل المخزون', 'en' => 'Run Stock Transfer Lifecycle'],
                ['ar' => 'مستخدم التحويل المصرح له', 'en' => 'Authorized transfer user'],
                ['UI-INV-004', 'UI-INV-005', 'UI-INV-006', 'UI-INV-007'],
                ['transfers.view'],
                [
                    ['ar' => 'راجع source وdestination وخطوط التحويل.', 'en' => 'Review source, destination, and transfer lines.'],
                    ['ar' => 'نفذ approve ثم dispatch، وتحقق من حركة الخروج وin-transit.', 'en' => 'Run approve then dispatch, and verify the exit movement and in-transit state.'],
                    ['ar' => 'سجل received_quantities لكل خط عند الوصول.', 'en' => 'Record received_quantities for every line on arrival.'],
                    ['ar' => 'عند الفرق، اترك الحالة difference_review ثم استخدم resolver بنوع وسبب allowlisted.', 'en' => 'When quantities differ, leave difference_review and use the resolver with an allowlisted type and reason.'],
                ],
                null,
            ),
            'FLW-INV-03' => self::flow(
                ['ar' => 'مراجعة تسوية مخزون', 'en' => 'Review Inventory Adjustment'],
                ['ar' => 'مستخدم التسويات المصرح له', 'en' => 'Authorized adjustment user'],
                ['UI-INV-011'],
                ['inventory_stock_card.view'],
                [
                    ['ar' => 'حدد entry أو exit أو adjustment واقرأ السبب.', 'en' => 'Identify entry, exit, or adjustment and read the reason.'],
                    ['ar' => 'راجع before/after وstore scope قبل submit.', 'en' => 'Review before/after and store scope before submit.'],
                    ['ar' => 'اتبع draft ثم submitted ثم approved؛ الترحيل يحدث عند approve.', 'en' => 'Follow draft, submitted, then approved; posting occurs on approve.'],
                ],
                null,
            ),
            'FLW-INV-04' => self::flow(
                ['ar' => 'مراجعة إدخال أو خروج المخزون', 'en' => 'Review Inventory Entry or Exit'],
                ['ar' => 'مستخدم المخزون المصرح له', 'en' => 'Authorized inventory user'],
                ['UI-INV-011', 'UI-INV-003'],
                ['inventory_stock_card.view'],
                [
                    ['ar' => 'راجع نوع الحركة والكمية والسبب في شاشة التسويات.', 'en' => 'Review movement type, quantity, and reason on adjustments.'],
                    ['ar' => 'لا تعدل الرصيد مباشرة من الواجهة.', 'en' => 'Do not edit balances directly from the UI.'],
                    ['ar' => 'طابق الحركة الناتجة في الدفتر بعد الاعتماد.', 'en' => 'Reconcile the resulting movement in the ledger after approval.'],
                ],
                null,
            ),
            'FLW-INV-05' => self::flow(
                ['ar' => 'اعتماد تسوية المخزون', 'en' => 'Approve Inventory Adjustment'],
                ['ar' => 'مراجع التسويات المصرح له', 'en' => 'Authorized adjustment reviewer'],
                ['UI-INV-011'],
                ['inventory_stock_card.approve'],
                [
                    ['ar' => 'لا تعتمد سجلًا خارج نطاق المتاجر المرئية.', 'en' => 'Do not approve a record outside visible store scope.'],
                    ['ar' => 'تحقق من السبب ومنع المخزون السالب قبل الاعتماد.', 'en' => 'Verify reason and negative-stock protection before approval.'],
                    ['ar' => 'راجع audit وinventory movement بعد الترحيل.', 'en' => 'Review audit and inventory movement after posting.'],
                ],
                null,
            ),
            'FLW-INV-06' => self::flow(
                ['ar' => 'تنفيذ جلسة جرد كاملة أو جزئية', 'en' => 'Run Full or Partial Stock Count'],
                ['ar' => 'مستخدم الجرد المصرح له', 'en' => 'Authorized count user'],
                ['UI-INV-008', 'UI-INV-009'],
                ['stock_counts.view'],
                [
                    ['ar' => 'حدد المتجر ونوع الجرد والنطاق.', 'en' => 'Select store, count type, and scope.'],
                    ['ar' => 'أدخل الكميات الفعلية فقط، مع إبقاء غير المعدود واضحًا.', 'en' => 'Enter physical quantities only and keep uncounted lines explicit.'],
                    ['ar' => 'أرسل الجلسة بعد مراجعة duplicate والـvalidation.', 'en' => 'Submit after reviewing duplicates and validation.'],
                ],
                null,
            ),
            'FLW-INV-07' => self::flow(
                ['ar' => 'مطابقة الجرد وترحيل الفرق', 'en' => 'Reconcile Stock Count Variance'],
                ['ar' => 'مراجع الجرد المصرح له', 'en' => 'Authorized count reviewer'],
                ['UI-INV-008', 'UI-INV-010', 'UI-INV-003'],
                ['stock_counts.reconcile'],
                [
                    ['ar' => 'قارن snapshot وexpected وcounted وvariance مع reference time.', 'en' => 'Compare snapshot, expected, counted, and variance with reference time.'],
                    ['ar' => 'لا تصفر المنتجات غير المعدودة تلقائيًا، خصوصًا في partial count.', 'en' => 'Never auto-zero uncounted products, especially in a partial count.'],
                    ['ar' => 'اعتمد المطابقة ثم راجع adjustment movement في الدفتر.', 'en' => 'Approve reconciliation and review the adjustment movement in the ledger.'],
                ],
                null,
            ),
            'FLW-SYS-01' => self::flow(
                ['ar' => 'مراجعة سجل التدقيق', 'en' => 'Review Audit Logs'],
                ['ar' => 'المراجع المصرح له', 'en' => 'Authorized reviewer'],
                ['UI-SYS-003'],
                ['audit_logs.view'],
                [
                    ['ar' => 'تطبيق تصفية السجلات حسب المستخدم أو الحدث أو التاريخ.', 'en' => 'Apply log filters by user, event, or date range.'],
                    ['ar' => 'مراجعة الأحداث المعروضة ومقارنة البيانات قبل التعديل وبعده.', 'en' => 'Review displayed events and compare before/after values.'],
                    ['ar' => 'استخدام معرف التتبع المرجعي لدعم المراجعة.', 'en' => 'Use correlation tracking reference for review support.'],
                ],
                null,
            ),
            'FLW-SYS-02' => self::flow(
                ['ar' => 'فحص صحة النظام', 'en' => 'Check System Health'],
                ['ar' => 'مستخدم الدعم المصرح له', 'en' => 'Authorized support user'],
                ['UI-SYS-004'],
                ['audit_logs.view'],
                [
                    ['ar' => 'فتح شاشة فحوصات صحة النظام المحلية.', 'en' => 'Open local system health checks screen.'],
                    ['ar' => 'تحديث عرض الفحوصات الآمنة دون إفشاء الأسرار.', 'en' => 'Refresh safe health status view without exposing secrets.'],
                    ['ar' => 'رفع معرف التتبع المرجعي في حالة وجود مكون متأثر.', 'en' => 'Escalate correlation reference if a component is degraded.'],
                ],
                null,
            ),
            'FLW-SYS-03' => self::flow(
                ['ar' => 'مراجعة تطبيق النظام', 'en' => 'Review System App'],
                ['ar' => 'مستخدم العمليات المصرح له', 'en' => 'Authorized operations user'],
                ['UI-SYS-002'],
                ['dashboard_reports.view'],
                [
                    ['ar' => 'مراجعة حالة جاهزية تثبيت تطبيق النظام PWA.', 'en' => 'Review system app PWA installation readiness.'],
                    ['ar' => 'متابعة مؤشر الاتصال المباشر بالشبكة.', 'en' => 'Monitor live network connectivity status.'],
                    ['ar' => 'تأكد من تطبيق سياسة التخزين المؤقت المحمي.', 'en' => 'Ensure enforcement of protected cache policy.'],
                ],
                null,
            ),
        ];

        foreach ($flows as $flowId => &$flow) {
            $flow['flow_id'] = $flowId;
        }
        unset($flow);

        return $flows;
    }

    public static function find(string $flowId): ?array
    {
        return self::all()[$flowId] ?? null;
    }

    private static function flow(array|string $title, array|string $actor, array $screens, array $permissions, array $steps, ?string $next): array
    {
        $titleArray = is_array($title) ? $title : ['ar' => $title, 'en' => $title];
        $actorArray = is_array($actor) ? $actor : ['ar' => $actor, 'en' => $actor];

        return [
            'flow_id' => '',
            'title' => $titleArray,
            'actor' => $actorArray,
            'preconditions' => [
                'ar' => 'المستخدم مصادق عليه والشاشة متاحة ضمن نطاقه.',
                'en' => 'The user is authenticated and the screen is available within scope.',
            ],
            'trigger' => [
                'ar' => 'فتح الشاشة المسجلة.',
                'en' => 'Open the registered screen.',
            ],
            'steps' => array_map(function (array|string $body, int $index): array {
                $bodyArray = is_array($body) ? $body : ['ar' => $body, 'en' => $body];

                return [
                    'number' => $index + 1,
                    'body' => $bodyArray,
                ];
            }, $steps, array_keys($steps)),
            'alternate_paths' => [
                'ar' => 'قد تختلف الخطوات المتاحة حسب الصلاحيات وحالة السجل.',
                'en' => 'Available steps can differ by permission and record state.',
            ],
            'failure_paths' => [
                'ar' => 'اعرض رسالة التحقق أو المنع، ثم صحح السبب أو اطلب المساعدة.',
                'en' => 'Read the validation or denial message, then correct the cause or request help.',
            ],
            'required_permissions' => $permissions,
            'audit_expectations' => [
                'ar' => 'الإجراءات المؤثرة تسجل وفق سياسة التدقيق الحالية.',
                'en' => 'Mutating actions are audited according to the current audit policy.',
            ],
            'source_screen_ids' => $screens,
            'destination_screen_ids' => $screens,
            'completion_condition' => [
                'ar' => 'اكتملت الخطوة المنشورة وظهرت نتيجتها الآمنة.',
                'en' => 'The published step completed and its safe result is visible.',
            ],
            'next_recommended_flow' => $next,
        ];
    }
}
