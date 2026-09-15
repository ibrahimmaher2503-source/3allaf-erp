<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use LogicException;

final class RetailTutorialFactory
{
    /** @return array<string, mixed> */
    public static function make(string $screenId): array
    {
        $definitions = [
            'UI-RET-001' => self::definition(
                ['pos'],
                ['ar' => 'دليل نقطة البيع', 'en' => 'POS Checkout Guide'],
                ['ar' => 'يوضح مسار البيع المحلي online مع إبقاء الضرائب والخصومات والمدفوعات الرسمية معلقة.', 'en' => 'Explains the local online checkout slice while tax, discount, and official payment policy remain pending.'],
                ['ar' => 'استخدمه لمراجعة المنتج والسلة والسياق التشغيلي قبل checkout المحلي.', 'en' => 'Use it to review products, cart, and operational context before local checkout.'],
                ['pos_sales.view'],
                self::actions([
                    ['pos.review', ['ar' => 'مراجعة المنتجات والسلة', 'en' => 'Review products and cart'], 'pos_sales.view'],
                    ['pos.checkout', ['ar' => 'تنفيذ checkout محلي مصرح', 'en' => 'Run an authorized local checkout'], 'pos_sales.create'],
                ]),
                ['TSK-023'], ['FLW-POS-SETUP', 'FLW-POS-01'], ['AC-UI-08', 'AC-UI-12'],
                [
                    self::step('header', 'pos-header', ['ar' => 'حدود نقطة البيع', 'en' => 'POS boundary'], ['ar' => 'هذه شريحة Local/Dev online فقط؛ لا تعتبر اعتمادًا للسياسات المالية أو الأجهزة.', 'en' => 'This is a Local/Dev online slice; it does not approve financial or hardware policy.']),
                    self::step('products', 'pos-products-heading', ['ar' => 'اختر منتجًا مسعرًا', 'en' => 'Choose a priced product'], ['ar' => 'أضف منتجًا نشطًا له سعر ظاهر. يعيد الخادم التحقق من السعر والمخزون.', 'en' => 'Add an active product with a visible price. The server rechecks price and stock.']),
                    self::step('cart', 'pos-cart-heading', ['ar' => 'راجع السلة', 'en' => 'Review the cart'], ['ar' => 'تحقق من المنتج والكمية والسعر قبل أي إجراء؛ الإزالة والإفراغ تخضعان للخادم.', 'en' => 'Check product, quantity, and price before acting; removal and clearing remain server-controlled.']),
                    self::step('context', 'pos-operational-context', ['ar' => 'تحقق من السياق', 'en' => 'Confirm operational context'], ['ar' => 'المتجر والدرج والوردية المعروضة تحدد ما إذا كان checkout المحلي متاحًا.', 'en' => 'Displayed store, drawer, and shift determine whether local checkout is available.']),
                    self::step('summary', 'pos-summary-actions', ['ar' => 'افهم الإجراء النهائي', 'en' => 'Understand the final action'], ['ar' => 'الملخص يوضح ما هو معلّق؛ checkout cash sale هو الإجراء المحلي المسموح فقط عند اكتمال الشروط.', 'en' => 'The summary shows what remains pending; cash checkout is local-only and condition-gated.']),
                ],
                [self::field('scope', ['ar' => 'النطاق', 'en' => 'Scope'], ['ar' => 'Local/Dev online فقط.', 'en' => 'Local/Dev online only.'])],
                ['ar' => 'لا تدخل بيانات عميل أو أسرارًا في هذه الشريحة.', 'en' => 'Do not enter customer data or secrets in this slice.'],
                ['ar' => 'الضريبة والخصم والـgateway والإيصال الرسمي PENDING.', 'en' => 'Tax, discount, gateway, and official receipt remain PENDING.'],
                ['ar' => 'إذا غاب زر الإجراء، راجع الصلاحية والمتجر والوردية.', 'en' => 'If an action is missing, review permission, store, and shift.'],
                ['ar' => 'بعد checkout، افتح المبيعات وطابق حركة المخزون.', 'en' => 'After checkout, open Sales and reconcile the inventory movement.'],
                ['ar' => 'هل POS هنا Production-ready؟ لا، هو Local/Dev evidence فقط.', 'en' => 'Is this POS Production-ready? No, it is Local/Dev evidence only.']
            ),
            'UI-RET-002' => self::definition(
                ['pos.suspended'],
                ['ar' => 'دليل المبيعات المعلقة', 'en' => 'Suspended Sales Guide'],
                ['ar' => 'يعرض السلات المحلية المعلقة التي يمكن استئنافها من قبل الكاشير المصرح.', 'en' => 'Shows local suspended carts that an authorized cashier may resume.'],
                ['ar' => 'استخدمه لاستعادة سلة معلقة ثم العودة إلى POS.', 'en' => 'Use it to resume a suspended cart and return to POS.'],
                ['pos_sales.view'],
                self::actions([['suspended.review', ['ar' => 'مراجعة السلات المعلقة', 'en' => 'Review suspended carts'], 'pos_sales.view'], ['suspended.resume', ['ar' => 'استئناف سلة', 'en' => 'Resume a cart'], 'pos_sales.create']]),
                ['TSK-023'], ['FLW-POS-01'], ['AC-UI-08'],
                [
                    self::step('header', 'suspended-header', ['ar' => 'نطاق السلات المعلقة', 'en' => 'Suspended cart scope'], ['ar' => 'هذه السلات محلية وتنتظر الاستئناف؛ لا تعتبر مبيعات معتمدة بعد.', 'en' => 'These carts are local and awaiting resume; they are not approved sales yet.']),
                    self::step('table', 'suspended-table-heading', ['ar' => 'اقرأ مرجع الاستئناف', 'en' => 'Read the resume reference'], ['ar' => 'استخدم الكود والوقت وعدد العناصر للتأكد من السلة الصحيحة.', 'en' => 'Use code, time, and item count to confirm the correct cart.']),
                ],
                [self::field('resume', ['ar' => 'كود الاستئناف', 'en' => 'Resume code'], ['ar' => 'معرف محلي للسلة.', 'en' => 'Local cart identifier.'])],
                ['ar' => 'لا تعدل السلة المعلقة من HTML أو endpoint غير ظاهر.', 'en' => 'Do not alter a suspended cart through HTML or a hidden endpoint.'],
                ['ar' => 'السلة المعلقة ليست إيصالًا أو حركة مخزون.', 'en' => 'A suspended cart is not a receipt or inventory movement.'],
                ['ar' => 'راجع الكود قبل الاستئناف.', 'en' => 'Confirm the code before resuming.'],
                ['ar' => 'بعد الاستئناف، راجع السلة ثم نفذ الإجراء المصرح.', 'en' => 'After resuming, review the cart and take the authorized action.'],
                ['ar' => 'هل الاستئناف يبيع تلقائيًا؟ لا، يعيد السلة فقط.', 'en' => 'Does resume sell automatically? No, it only restores the cart.']
            ),
            'UI-RET-003' => self::definition(
                ['sales.index'],
                ['ar' => 'دليل سجل المبيعات', 'en' => 'Sales Register Guide'],
                ['ar' => 'يعرض المبيعات المحلية المعتمدة مع المتجر والكاشير والتاريخ والإجمالي.', 'en' => 'Lists approved local sales with store, cashier, date, and total.'],
                ['ar' => 'استخدمه بعد checkout لمراجعة المرجع وفتح التفاصيل.', 'en' => 'Use it after checkout to review the reference and open details.'],
                ['pos_sales.view'],
                self::actions([['sales.review', ['ar' => 'مراجعة المبيعات', 'en' => 'Review sales'], 'pos_sales.view'], ['sales.open-detail', ['ar' => 'فتح تفاصيل البيع', 'en' => 'Open sale detail'], 'pos_sales.view']]),
                ['TSK-023'], ['FLW-POS-01'], ['AC-UI-08', 'AC-UI-12'],
                [
                    self::step('header', 'sales-header', ['ar' => 'حدود سجل المبيعات', 'en' => 'Sales register boundary'], ['ar' => 'هذه مبيعات Local/Dev المعتمدة فقط، وليست سجلًا ماليًا Production.', 'en' => 'This is an approved Local/Dev sales list, not a Production financial ledger.']),
                    self::step('action', 'sales-open-pos', ['ar' => 'العودة إلى POS', 'en' => 'Return to POS'], ['ar' => 'استخدم الزر للعودة إلى شاشة البيع المحلية.', 'en' => 'Use the action to return to local checkout.']),
                    self::step('table', 'sales-table-heading', ['ar' => 'افتح مرجع الفاتورة', 'en' => 'Open the invoice reference'], ['ar' => 'رابط رقم الفاتورة يفتح التفاصيل دون تعديل السجل.', 'en' => 'The invoice link opens detail without editing the record.']),
                ],
                [self::field('invoice', ['ar' => 'رقم المستند', 'en' => 'Document number'], ['ar' => 'مرجع البيع المحلي.', 'en' => 'Local sale reference.'])],
                ['ar' => 'لا توجد إجراءات حذف أو تعديل من هذا السجل.', 'en' => 'There are no delete or edit actions in this register.'],
                ['ar' => 'الطباعة الرسمية والتسويات المالية PENDING.', 'en' => 'Official printing and financial settlement remain PENDING.'],
                ['ar' => 'إذا لم يظهر بيع، راجع النطاق والحالة.', 'en' => 'If a sale is missing, review scope and status.'],
                ['ar' => 'افتح التفاصيل لمطابقة الخطوط والإجمالي وحركة المخزون.', 'en' => 'Open detail to reconcile lines, total, and inventory movement.'],
                ['ar' => 'هل سجل المبيعات يسمح بالتعديل؟ لا، هو عرض للتفاصيل فقط.', 'en' => 'Does the register allow editing? No, it is a detail view.']
            ),
            'UI-RET-004' => self::definition(
                ['sales.show'],
                ['ar' => 'دليل تفاصيل البيع', 'en' => 'Sale Detail Guide'],
                ['ar' => 'يربط تفاصيل البيع المحلي بالمتجر والكاشير والخطوط والإجمالي.', 'en' => 'Connects a local sale to store, cashier, lines, and total.'],
                ['ar' => 'استخدمه للتحقق بعد فتح رقم مستند من سجل المبيعات.', 'en' => 'Use it to verify a sale after opening its document number.'],
                ['pos_sales.view'],
                self::actions([['sale.detail', ['ar' => 'مراجعة تفاصيل البيع', 'en' => 'Review sale detail'], 'pos_sales.view'], ['sale.print-baseline', ['ar' => 'فتح خط أساس الطباعة', 'en' => 'Open print baseline'], 'pos_sales.view']]),
                ['TSK-023'], ['FLW-POS-01'], ['AC-UI-08', 'AC-UI-12'],
                [
                    self::step('header', 'sale-detail-header', ['ar' => 'مرجع البيع', 'en' => 'Sale reference'], ['ar' => 'تحقق من رقم المستند قبل مطابقة أي خط أو إجمالي.', 'en' => 'Confirm the document number before reconciling any line or total.']),
                    self::step('meta', 'sale-detail-meta', ['ar' => 'تحقق من السياق', 'en' => 'Confirm context'], ['ar' => 'المتجر والكاشير والحالة توضح نطاق البيع المحلي.', 'en' => 'Store, cashier, and status establish the local sale context.']),
                    self::step('lines', 'sale-detail-lines-heading', ['ar' => 'راجع الخطوط', 'en' => 'Review lines'], ['ar' => 'طابق المنتج والكمية والسعر والمبلغ قبل قراءة الإجمالي.', 'en' => 'Reconcile product, quantity, price, and amount before reading the total.']),
                    self::step('print', 'sale-detail-print-action', ['ar' => 'افتح خط الطباعة', 'en' => 'Open print baseline'], ['ar' => 'الطباعة هنا baseline محلي وليست إيصال Production رسميًا.', 'en' => 'This print view is a local baseline, not an official Production receipt.']),
                ],
                [self::field('status', ['ar' => 'الحالة', 'en' => 'Status'], ['ar' => 'Approved ضمن Local/Dev.', 'en' => 'Approved within Local/Dev.'])],
                ['ar' => 'لا تغير الخطوط أو الإجمالي من هذه الشاشة.', 'en' => 'Do not change lines or totals from this screen.'],
                ['ar' => 'الضريبة والخصم والدفع والطباعة الرسمية PENDING.', 'en' => 'Tax, discount, payment, and official printing remain PENDING.'],
                ['ar' => 'إذا اختلف الإجمالي، أوقف المراجعة وارجع للمصدر.', 'en' => 'If the total differs, stop and return to the source.'],
                ['ar' => 'بعد المطابقة، استخدم Print baseline فقط لأغراض Local/Dev.', 'en' => 'After reconciliation, use Print baseline for Local/Dev evidence only.'],
                ['ar' => 'هل زر Print اعتماد رسمي؟ لا.', 'en' => 'Is Print an official approval? No.']
            ),
            'UI-RET-005' => self::readiness('pos.financial-readiness', 'جاهزية الشؤون المالية لنقطة البيع', 'POS Financial Readiness', 'financial', 'TSK-024', 'POSF-01..04 and BLK-008'),
            'UI-RET-007' => self::readiness('pos.offline-readiness', 'جاهزية العمل دون اتصال', 'Offline Readiness', 'offline', 'TSK-026', 'OFF-01..05 and NFR-04'),
            'UI-POS-003' => self::shiftOpening(),
            'UI-POS-012' => self::posCashierWorkflow(),
            'UI-POS-007' => self::receipt(),
            'UI-PTY-001' => self::readiness('party.readiness', 'جاهزية حجوزات الحفلات والفاتورة العاملة', 'Party Booking and Working Invoice Readiness', 'party', 'TSK-031', 'PTY-01..03'),
            'UI-RPT-001' => self::readiness('reports.readiness', 'جاهزية لوحات المعلومات وتقارير المطابقة', 'Dashboards and Reconciled Reports Readiness', 'reports', 'TSK-038', 'RPT-01..03'),
            'UI-RPT-002' => self::readiness('exports.audit.readiness', 'جاهزية مركز التصدير والتدقيق', 'Export Center and Audit Views Readiness', 'export-audit', 'TSK-040', 'RPT-03'),
            'UI-SYS-008' => self::readiness('master-data.migration.readiness', 'جاهزية استيراد البيانات الأساسية والقطع', 'Master Data Import and Cutover Readiness', 'master-data-migration', 'TSK-041', 'MD-06'),
            'UI-SYS-009' => self::readiness('operations.readiness', 'جاهزية التشغيل والإحالة', 'Production Operations and Handover Readiness', 'operations', 'TSK-042', 'NFR-04'),
            'UI-SYS-010' => self::readiness('uat.readiness', 'جاهزية الاختبار اليدوي وقاعدة الأدلة', 'Manual UAT and Evidence Readiness', 'uat', 'TSK-043', 'AC-UI-01..05'),
            'UI-SYS-011' => self::readiness('release.readiness', 'جاهزية القطع والإطلاق والإحالة', 'Controlled Go-Live and Handover Readiness', 'release', 'TSK-044', 'NFR-04..07'),
            'UI-SYS-007' => self::readiness('alerts.readiness', 'جاهزية التنبيهات التشغيلية وطابور الاستثناءات', 'Operational Alerts and Exception Queue Readiness', 'alerts', 'TSK-039', 'RPT-02'),
            'UI-QTN-001' => self::readiness('quotations.readiness', 'جاهزية عروض الأسعار والمقترحات', 'Quotations and Proposals Readiness', 'quotations', 'TSK-037', 'QTN-01..03'),
            'UI-PTY-015' => self::readiness('party.final-close.readiness', 'جاهزية الإغلاق والتسوية النهائية للحفلات', 'Party Final Close and Settlement Readiness', 'party-final-close', 'TSK-036', 'PTY-06'),
            'UI-PTY-012' => self::readiness('party.asset-events.readiness', 'جاهزية تلف وفقد وصيانة الأصول', 'Asset Damage, Loss, Maintenance and Depreciation Readiness', 'party-asset-events', 'TSK-035', 'AST-04'),
            'UI-PTY-007' => self::readiness('party.assets.readiness', 'جاهزية أصول التأجير والتقويم', 'Rental Assets and Calendar Readiness', 'party-assets', 'TSK-034', 'AST-01..03'),
            'UI-PTY-005' => self::readiness('party.operating.readiness', 'جاهزية أوامر التشغيل والمواد الاستهلاكية', 'Party Operating Orders and Consumables Readiness', 'party-operating', 'TSK-033', 'PTY-05 / AST-05'),
            'UI-PTY-004' => self::readiness('party.payments.readiness', 'جاهزية دفعات الحفلات ورصيد الطرف', 'Party Payments and Balance Readiness', 'party-payments', 'TSK-032', 'PTY-04 / CUS-05'),
            'UI-POS-008' => self::readiness('returns.readiness', 'جاهزية المرتجعات والاستبدال', 'Returns and Exchanges Readiness', 'returns', 'TSK-030', 'RET-01..03'),
            'UI-POS-010' => self::readiness('gift.receipts', 'إيصالات الهدايا', 'Gift Receipts', 'gift-receipts', 'TSK-029', 'POS-07 / RET-04'),
            'UI-POS-011' => self::readiness('gift.cards', 'بطاقات الهدايا', 'Gift Cards', 'gift-cards', 'TSK-029', 'validity, holder, redemption, and void'),
            'UI-CUS-001' => self::customerMaster(),
            'UI-CUS-002' => self::readiness('admin.settings.customer-loyalty', 'إعدادات سياسات العملاء', 'Customer Policy Settings', 'settings', 'TSK-027', 'Owner approval'),
            'UI-CUS-004' => self::wallet('wallets.product', 'محفظة المنتجات', 'Product Wallet', 'product', 'product_wallet.view'),
            'UI-CUS-005' => self::wallet('wallets.party', 'محفظة الأطراف', 'Party Wallet', 'party', 'party_wallet.view'),
        ];

        if (! isset($definitions[$screenId])) {
            throw new LogicException("Unknown retail tutorial [{$screenId}].");
        }

        $definition = $definitions[$screenId];
        $definition['sections'] = [
            'steps' => $definition['steps'],
            'fields' => $definition['fields'],
            'notes' => $definition['notes'],
            'warnings' => $definition['warnings'],
            'errors' => $definition['errors'],
            'next_step' => $definition['next_step'],
            'faq' => $definition['faq'],
        ];
        $definition['tour_steps'] = array_slice($definition['steps'], 0, 5);
        unset($definition['steps'], $definition['fields'], $definition['notes'], $definition['warnings'], $definition['errors'], $definition['next_step'], $definition['faq']);

        $definition = self::polishUserCopy($definition);

        return ['screen_id' => $screenId] + $definition + ['version' => '1.0.0', 'updated_at' => '2026-08-07'];
    }

    /**
     * Keep internal requirement IDs and selectors intact while making copy
     * shown in the page guide suitable for a customer-facing product.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private static function polishUserCopy(array $definition): array
    {
        foreach (['title', 'purpose', 'when_to_use', 'notes', 'warnings', 'errors', 'next_step', 'faq'] as $key) {
            if (array_key_exists($key, $definition)) {
                $definition[$key] = self::polishLocalizedValue($definition[$key]);
            }
        }

        foreach ($definition['approved_actions'] ?? [] as $index => $action) {
            if (isset($action['label'])) {
                $definition['approved_actions'][$index]['label'] = self::polishLocalizedValue($action['label']);
            }
        }

        foreach ($definition['sections']['steps'] ?? [] as $index => $step) {
            $definition['sections']['steps'][$index] = self::polishLocalizedValue($step);
        }

        foreach ($definition['sections']['fields'] ?? [] as $index => $field) {
            $definition['sections']['fields'][$index] = self::polishLocalizedValue($field);
        }

        foreach (['notes', 'warnings', 'errors', 'next_step', 'faq'] as $key) {
            if (array_key_exists($key, $definition['sections'] ?? [])) {
                $definition['sections'][$key] = self::polishLocalizedValue($definition['sections'][$key]);
            }
        }

        foreach ($definition['tour_steps'] ?? [] as $index => $step) {
            $definition['tour_steps'][$index] = self::polishLocalizedValue($step);
        }

        return $definition;
    }

    private static function polishLocalizedValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_key_exists('ar', $value) || array_key_exists('en', $value)) {
            if (isset($value['ar']) && is_string($value['ar'])) {
                $value['ar'] = self::polishString($value['ar'], true);
            }

            if (isset($value['en']) && is_string($value['en'])) {
                $value['en'] = self::polishString($value['en'], false);
            }

            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::polishLocalizedValue($item);
        }

        return $value;
    }

    private static function polishString(string $value, bool $arabic): string
    {
        $value = preg_replace('/\bTSK-\d+\b\s*·?\s*/iu', app()->isLocale('ar-EG') ? 'النطاق التشغيلي ده ' : ($arabic ? 'هذا النطاق التشغيلي ' : 'this operational scope '), $value) ?? $value;

        $replacements = $arabic
            ? [
                'Local/Dev' => 'البيئة المحلية',
                'local online' => 'عبر الإنترنت في البيئة المحلية',
                'online' => 'عبر الإنترنت',
                'checkout cash sale' => 'إتمام البيع النقدي',
                'checkout' => 'إتمام البيع',
                'gateway' => 'بوابة الدفع',
                'PENDING/TBD' => 'قيد المراجعة ولم يُحسم بعد',
                'PENDING' => 'قيد المراجعة',
                'Production-ready' => 'جاهز للإنتاج',
                'Production' => 'الإنتاج',
                'append-only' => 'غير قابل للتعديل بعد الترحيل',
                'mutation' => 'تغيير تشغيلي',
                'readiness' => 'التهيئة',
                'Readiness' => 'التهيئة',
                'version' => 'إصدار',
                'audit' => 'تدقيق',
                'customer' => 'العميل',
                'Product Wallet' => 'محفظة المنتجات',
                'Party Wallet' => 'محفظة الأطراف',
                'expected' => 'المتوقع',
                'actual' => 'الفعلي',
                'Print baseline' => 'نسخة الطباعة المرجعية',
                'Print' => 'الطباعة',
                'TBD' => 'لم يُحسم بعد',
            ]
            : [
                'Local/Dev' => 'local preview',
                'PENDING/TBD' => 'pending review / not yet defined',
                'PENDING' => 'pending review',
                'readiness' => 'enablement review',
                'Readiness' => 'Enablement review',
                'mutation' => 'operational change',
                'TSK-' => '',
            ];

        return strtr($value, $replacements);
    }

    /** @return array<string, mixed> */
    private static function wallet(string $route, string $titleAr, string $titleEn, string $kind, string $permission): array
    {
        $prefix = $kind.'-wallet';
        $other = $kind === 'product' ? 'Party Wallet' : 'Product Wallet';
        $otherAr = $kind === 'product' ? 'محفظة الأطراف' : 'محفظة المنتجات';

        return [
            'route_names' => [$route],
            'title' => ['ar' => $titleAr, 'en' => $titleEn],
            'purpose' => ['ar' => "يوضح هذا الدليل حدود {$titleAr} المنفصلة ضمن TSK-028 دون إنشاء رصيد أو حركة.", 'en' => "Explains the separate {$titleEn} boundary within TSK-028 without creating a balance or entry."],
            'when_to_use' => ['ar' => 'استخدمه لمراجعة السجل المحلي وحدود الصلاحيات والقيم المعلقة.', 'en' => 'Use it to review the local ledger, permission boundary, and pending values.'],
            'permissions' => [$permission],
            'approved_actions' => self::actions([
                ['wallet.review', ['ar' => "مراجعة {$titleAr}", 'en' => "Review {$titleEn}"], $permission],
            ]),
            'stories' => ['TSK-028'],
            'flows' => ['FLW-CUS-04', 'FLW-CUS-05'],
            'acceptance_criteria' => ['AC-CUS-01', 'AC-CUS-02', 'AC-CUS-04'],
            'steps' => [
                self::step('header', "{$prefix}-header", ['ar' => "حدود {$titleAr}", 'en' => "{$titleEn} boundary"], ['ar' => 'هذه شاشة Local/Dev منفصلة؛ لا تعتمد سياسة مالية ولا تنشئ حركة.', 'en' => 'This is a separate Local/Dev screen; it approves no financial policy and creates no entry.']),
                self::step('boundary', "{$prefix}-boundary", ['ar' => 'اقرأ حالة الاعتماد', 'en' => 'Read the approval state'], ['ar' => 'القيم غير المحسومة PENDING، ولا يوجد bypass لاعتماد المالك.', 'en' => 'Unresolved values are PENDING; there is no owner-approval bypass.']),
                self::step('summary', "{$prefix}-summary", ['ar' => 'تحقق من فصل السجل', 'en' => 'Confirm ledger separation'], ['ar' => 'اسم الجدول والصلاحية يثبتان أن هذا السجل ليس محفظة عامة مشتركة.', 'en' => 'Table name and permission confirm that this is not a generic shared wallet.']),
                self::step('ledger', "{$prefix}-ledger", ['ar' => 'راجع السجل دون تعديل', 'en' => 'Review the read-only ledger'], ['ar' => 'لا توجد أزرار إنشاء أو تعديل أو حذف؛ ستظهر الحركات فقط بعد مصدر موثق.', 'en' => 'There are no create, edit, or delete controls; entries appear only after a documented source.']),
                self::step('isolation', "{$prefix}-isolation", ['ar' => 'تحقق من العزل', 'en' => 'Confirm isolation'], ['ar' => "للوصول إلى {$otherAr} صلاحية ومسار منفصلان؛ لا يوجد تحويل عام بينهما.", 'en' => "{$other} has a separate permission and route; no generic transfer exists between them."]),
            ],
            'fields' => [self::field('state', ['ar' => 'الحالة', 'en' => 'State'], ['ar' => 'PENDING تعني أن المصدر أو السياسة أو الاعتماد غير مكتمل.', 'en' => 'PENDING means source, policy, or approval is incomplete.'])],
            'notes' => ['ar' => 'السجل append-only ولا توجد حركة أو رصيد تجريبي.', 'en' => 'The ledger is append-only and no demo entry or balance exists.'],
            'warnings' => ['ar' => 'لا تحول إعداد Local/Dev إلى اعتماد Production.', 'en' => 'Do not convert Local/Dev configuration into Production approval.'],
            'errors' => ['ar' => 'إذا غاب السجل، راجع الصلاحية والنطاق بدل تجاوز الحماية.', 'en' => 'If the ledger is unavailable, review permission and scope instead of bypassing protection.'],
            'next_step' => ['ar' => 'راجع القيم المعلقة من Settings ثم عد إلى هذه الشاشة.', 'en' => 'Review pending values in Settings, then return to this screen.'],
            'faq' => ['ar' => 'هل توجد حركة تحويل بين Product وParty Wallet؟ لا.', 'en' => 'Is there a transfer between Product and Party Wallet? No.'],
        ];
    }

    /** @return array<string, mixed> */
    private static function posCashierWorkflow(): array
    {
        return self::definition(
            ['pos', 'pos.shift', 'pos.suspended'],
            ['ar' => 'دليل تشغيل نقطة البيع للكاشير', 'en' => 'POS Cashier Workflow Guide'],
            ['ar' => 'يوضح هذا الدليل مسار البيع الكامل من تسجيل الدخول حتى إتمام الدفع ومراجعة الإيصال.', 'en' => 'Explains the complete cashier journey from sign-in through payment and receipt review.'],
            ['ar' => 'استخدمه عند بدء الوردية أو تدريب كاشير جديد على شاشة نقطة البيع.', 'en' => 'Use it when starting a shift or training a cashier on the POS screen.'],
            ['pos_sales.view'],
            self::actions([
                ['pos.open', ['ar' => 'فتح نقطة البيع', 'en' => 'Open POS'], 'pos_sales.view'],
                ['shift.open', ['ar' => 'فتح الوردية', 'en' => 'Open the shift'], 'shifts_cash_movements.create'],
                ['sale.complete', ['ar' => 'إتمام البيع', 'en' => 'Complete the sale'], 'pos_sales.create'],
            ]),
            ['US-017', 'US-018'], ['FLW-POS-SETUP', 'FLW-POS-01'], ['AC-UI-08', 'AC-UI-12'],
            [
                self::step('login', 'pos-login-step', ['ar' => '1. سجّل الدخول', 'en' => '1. Sign in'], ['ar' => 'افتح شاشة الدخول واستخدم حساب الكاشير المصرح به. لا تشارك كلمة المرور.', 'en' => 'Open the login screen and sign in with your authorized cashier account. Never share the password.']),
                self::step('scope', 'pos-operational-context', ['ar' => '2. تحقّق من النطاق التشغيلي', 'en' => '2. Confirm operational scope'], ['ar' => 'تحقّق من الفرع والمتجر ودرج النقدية وحالة الوردية. إذا ظهر متجر غير مهيأ، اطلب من المسؤول تعيين نطاقك.', 'en' => 'Confirm branch, selling store, cash drawer, and shift status. If the store is unavailable, ask an administrator to assign your scope.']),
                self::step('shift', 'pos-shift-open-action', ['ar' => '3. افتح الوردية', 'en' => '3. Open the shift'], ['ar' => 'اختر الدرج المعيّن وأدخل الرصيد الافتتاحي الفعلي قبل بدء أي بيع.', 'en' => 'Choose the assigned drawer and enter the actual opening float before starting a sale.']),
                self::step('search', 'pos-products-heading', ['ar' => '4. ابحث عن المنتج', 'en' => '4. Find the product'], ['ar' => 'استخدم الاسم أو رمز الصنف أو الباركود. امسح الباركود في نفس حقل البحث.', 'en' => 'Search by name, item code, or barcode. Scan the barcode into the same search field.']),
                self::step('add', 'pos-products-grid', ['ar' => '5. أضف المنتج', 'en' => '5. Add the product'], ['ar' => 'اضغط إضافة للمنتج ذي السعر المعتمد. المنتجات التي تحتاج خيارات تُراجع قبل الإضافة.', 'en' => 'Select Add for a product with an approved price. Review required options before adding configurable products.']),
                self::step('cart', 'pos-cart-heading', ['ar' => '6. راجع السلة والكمية', 'en' => '6. Review cart and quantity'], ['ar' => 'تأكد من المنتج والكمية والسعر والإجمالي. استخدم ناقص وزائد أو أدخل الكمية مباشرة ثم حدّثها.', 'en' => 'Confirm product, quantity, price, and line total. Use minus/plus or enter a quantity directly and update it.']),
                self::step('customer', 'pos-customer-context', ['ar' => '7. أضف العميل عند الحاجة', 'en' => '7. Add a customer when needed'], ['ar' => 'ابحث بالاسم أو الهاتف، أو أنشئ عميلاً جديداً من داخل البيع. فشل إنشاء العميل لا يمسح السلة.', 'en' => 'Search by name or phone, or create a customer without leaving the sale. A failed customer registration must not clear the cart.']),
                self::step('payment', 'pos-summary-actions', ['ar' => '8. راجع الإجمالي والدفع', 'en' => '8. Review total and payment'], ['ar' => 'راجع الخصم والضريبة والإجمالي النهائي، ثم اختر وسيلة الدفع وأدخل المبلغ والدليل عند طلبه.', 'en' => 'Review discount, tax, and final total. Select the tender and enter the amount and evidence when required.']),
                self::step('checkout', 'pos-payment-form', ['ar' => '9. أتمم البيع', 'en' => '9. Complete the sale'], ['ar' => 'اضغط إتمام البيع مرة واحدة. إذا ظهر مانع، اقرأ السبب وصحح الوردية أو الدفع أو المخزون قبل المحاولة.', 'en' => 'Submit the sale once. If blocked, read the reason and correct the shift, payment, or stock issue before retrying.']),
                self::step('receipt', 'pos-header', ['ar' => '10. راجع المرجع والإيصال', 'en' => '10. Review reference and receipt'], ['ar' => 'بعد النجاح، احتفظ بمرجع البيع وافتح سجل المبيعات لمراجعة التفاصيل والطباعة المصرح بها.', 'en' => 'After success, retain the sale reference and open Sales to review details and authorized printing.']),
            ],
            [
                self::field('cashier_account', ['ar' => 'حساب الكاشير', 'en' => 'Cashier account'], ['ar' => 'حساب مصرح ضمن نطاق الفرع والمتجر.', 'en' => 'An authorized account within branch and store scope.']),
                self::field('opening_float', ['ar' => 'الرصيد الافتتاحي', 'en' => 'Opening float'], ['ar' => 'المبلغ الفعلي الموجود في الدرج عند فتح الوردية.', 'en' => 'The actual cash in the drawer when the shift opens.']),
                self::field('payment_evidence', ['ar' => 'دليل الدفع', 'en' => 'Payment evidence'], ['ar' => 'مطلوب لبعض وسائل الدفع الإلكترونية فقط.', 'en' => 'Required only for electronic methods that require evidence.']),
            ],
            ['ar' => 'الخادم هو المصدر النهائي للسعر والمخزون والإجمالي والصلاحية.', 'en' => 'The server remains authoritative for price, stock, totals, and authorization.'],
            ['ar' => 'لا تبدأ البيع إذا لم يظهر متجر البيع أو درج النقدية أو وردية نشطة.', 'en' => 'Do not start a sale when the selling store, drawer, or active shift is missing.'],
            ['ar' => 'إذا تعطل إجراء، اقرأ رسالة المانع ثم صحح السبب من المسار المصرح.', 'en' => 'When an action is blocked, read the blocker and correct it through the authorized workflow.'],
            ['ar' => 'بعد إتمام البيع، راجع سجل المبيعات وحركة المخزون وفق إجراءات الفرع.', 'en' => 'After completion, review the sales register and inventory movement according to branch procedure.'],
            ['ar' => 'هل فتح الدليل يمنح صلاحية؟ لا، الصلاحيات والتحقق على الخادم.', 'en' => 'Does this guide grant permission? No. Authorization and validation remain server-side.'],
        );
    }

    /** @return array<string, mixed> */
    private static function shiftOpening(): array
    {
        return self::definition(
            ['pos.shift.open'],
            ['ar' => 'دليل فتح وردية نقطة البيع', 'en' => 'POS Shift Opening Guide'],
            ['ar' => 'يوضح فتح وردية على درج معيّن قبل بدء مبيعات الكاشير.', 'en' => 'Explains opening a shift on an assigned drawer before cashier sales begin.'],
            ['ar' => 'استخدمه بعد تعيين الدرج وقبل بدء سلة البيع.', 'en' => 'Use it after drawer assignment and before starting a sales cart.'],
            ['shifts_cash_movements.create'],
            self::actions([
                ['shift.open', ['ar' => 'فتح وردية', 'en' => 'Open a shift'], 'shifts_cash_movements.create'],
            ]),
            ['TSK-025'], ['FLW-POS-SETUP', 'FLW-POS-01'], ['AC-UI-08', 'AC-UI-12'],
            [
                self::step('context', 'pos-shift-context', ['ar' => 'تحقق من الدرج والنطاق', 'en' => 'Confirm drawer and scope'], ['ar' => 'اختر الدرج المعيّن لك ضمن الفرع والمتجر الصحيحين.', 'en' => 'Select the drawer assigned to you in the correct branch and store.']),
                self::step('opening-float', 'pos-shift-opening-float', ['ar' => 'أدخل رصيد البداية', 'en' => 'Enter the opening float'], ['ar' => 'أدخل الرصيد الفعلي المطلوب للدرج قبل حفظ الوردية.', 'en' => 'Enter the actual opening float required for the drawer before saving the shift.']),
                self::step('open', 'pos-shift-open-action', ['ar' => 'افتح وردية واحدة', 'en' => 'Open one shift'], ['ar' => 'لا تتجاوز تعارض وردية نشطة أو درج غير معيّن. صحح التعيين أو أغلق الوردية السابقة بالطريق المصرح.', 'en' => 'Do not bypass an active-shift conflict or an unassigned drawer. Correct the assignment or close the prior shift through the authorized path.']),
            ],
            [self::field('opening_float', ['ar' => 'رصيد البداية', 'en' => 'Opening float'], ['ar' => 'المبلغ الفعلي عند فتح الدرج.', 'en' => 'The actual amount in the drawer when the shift opens.'])],
            ['ar' => 'فتح الوردية لا يمنح صلاحيات نقطة البيع أو يتجاوز نطاق المتجر.', 'en' => 'Opening a shift does not grant POS permissions or bypass store scope.'],
            ['ar' => 'لا تبدأ بيعًا بلا وردية نشطة على الدرج المعيّن.', 'en' => 'Do not begin a sale without an active shift on the assigned drawer.'],
            ['ar' => 'إذا لم يظهر درج، راجع التعيين والصلاحية والنطاق.', 'en' => 'If no drawer appears, review assignment, permission, and scope.'],
            ['ar' => 'بعد الفتح، انتقل إلى نقطة البيع وتأكد من ظهور الوردية في السياق التشغيلي.', 'en' => 'After opening, go to POS and confirm that the shift appears in operational context.'],
            ['ar' => 'هل يستطيع الكاشير فتح ورديتين على الدرج نفسه؟ لا.', 'en' => 'Can a cashier open two shifts on the same drawer? No.'],
        );
    }

    /** @return array<string, mixed> */
    private static function receipt(): array
    {
        return self::definition(
            ['sales.receipt.thermal'],
            ['ar' => 'دليل إيصال البيع', 'en' => 'Sale Receipt Guide'],
            ['ar' => 'يوضح مراجعة وطباعة إيصال بيع معتمد من المسار المصرح فقط.', 'en' => 'Explains reviewing and printing an approved sale receipt through the authorized path only.'],
            ['ar' => 'استخدمه بعد اعتماد البيع ومراجعة مرجعه وتفاصيله.', 'en' => 'Use it after sale approval and after reviewing its reference and details.'],
            ['pos_sales.print'],
            self::actions([
                ['receipt.print', ['ar' => 'طباعة الإيصال', 'en' => 'Print receipt'], 'pos_sales.print'],
            ]),
            ['TSK-023'], ['FLW-POS-SETUP', 'FLW-POS-01'], ['AC-UI-08', 'AC-UI-12'],
            [
                self::step('reference', 'sale-receipt-reference', ['ar' => 'تحقق من مرجع البيع', 'en' => 'Confirm the sale reference'], ['ar' => 'اطبع الإيصال الخاص بالبيع المعتمد الصحيح فقط.', 'en' => 'Print only the receipt for the correct approved sale.']),
                self::step('contents', 'sale-receipt-contents', ['ar' => 'راجع محتوى الإيصال', 'en' => 'Review receipt contents'], ['ar' => 'راجع الخطوط والكميات والأسعار والإجمالي قبل تسليم الإيصال.', 'en' => 'Review lines, quantities, prices, and total before handing over the receipt.']),
                self::step('print', 'sale-receipt-print-action', ['ar' => 'استخدم مسار الطباعة المصرح', 'en' => 'Use the authorized print path'], ['ar' => 'لا تستخدم رابطًا يدويًا أو مسارًا مخفيًا لتجاوز صلاحية الطباعة أو نطاق البيع.', 'en' => 'Do not use a manual link or hidden route to bypass print permission or sale scope.']),
            ],
            [self::field('sale_reference', ['ar' => 'مرجع البيع', 'en' => 'Sale reference'], ['ar' => 'الرقم الذي يربط الإيصال بالبيع المعتمد وسجل التدقيق.', 'en' => 'The number linking the receipt to the approved sale and audit trail.'])],
            ['ar' => 'طباعة الإيصال لا تغيّر خطوط البيع أو المدفوعات أو المخزون.', 'en' => 'Printing a receipt does not change sale lines, payments, or inventory.'],
            ['ar' => 'إعادة الطباعة تخضع للصلاحية والسياسة وسجل التدقيق.', 'en' => 'Reprinting is subject to permission, policy, and the audit trail.'],
            ['ar' => 'إذا تعذر فتح الإيصال، راجع حالة البيع والصلاحية والنطاق بدل طلب رابط مباشر.', 'en' => 'If the receipt cannot open, review sale status, permission, and scope instead of requesting a direct link.'],
            ['ar' => 'بعد الطباعة، احتفظ بمرجع البيع لمراجعة لاحقة عند الحاجة.', 'en' => 'After printing, retain the sale reference for later review when needed.'],
            ['ar' => 'هل يمنح الدليل حق الطباعة؟ لا، يظل التحقق من الخادم هو المرجع.', 'en' => 'Does this guide grant print access? No, server authorization remains authoritative.'],
        );
    }

    /** @return array<string, mixed> */
    private static function customerMaster(): array
    {
        return self::definition(
            ['customers.index'],
            ['ar' => 'سجل العملاء', 'en' => 'Customer Master'],
            ['ar' => 'ينظم هذا الدليل إنشاء ملفات العملاء والبحث فيها ضمن نطاق الفرع والمتجر المصرح به.', 'en' => 'This guide covers creating and searching customer profiles within the authorized branch and store scope.'],
            ['ar' => 'استخدمه لمراجعة الهاتف والاسم والموافقة قبل ربط العميل ببيع POS.', 'en' => 'Use it to review phone, name, and consent before linking a customer to a POS sale.'],
            ['customers.view'],
            self::actions([
                ['customer.search', ['ar' => 'البحث في العملاء', 'en' => 'Search customers'], 'customers.view'],
                ['customer.create', ['ar' => 'إنشاء ملف عميل', 'en' => 'Create a customer profile'], 'customers.create'],
            ]),
            ['TSK-027'], ['FLW-CUS-01', 'FLW-CUS-02'], ['AC-MD-06', 'AC-CUS-01', 'AC-CUS-03'],
            [
                self::step('header', 'customer-list-header', ['ar' => 'نطاق سجل العملاء', 'en' => 'Customer master scope'], ['ar' => 'تظهر هنا الملفات التي يسمح بها نطاق المستخدم فقط؛ إخفاء الزر لا يغني عن فحص الخادم.', 'en' => 'Only profiles in the user scope appear; server authorization remains authoritative.']),
                self::step('search', 'customer-search', ['ar' => 'ابحث بالاسم أو الهاتف', 'en' => 'Search by name or phone'], ['ar' => 'يدعم البحث الاسم العربي والإنجليزي والهاتف المطبّع.', 'en' => 'Search supports Arabic/English names and the normalized phone number.']),
                self::step('profiles', 'customer-table', ['ar' => 'راجع الملفات', 'en' => 'Review profiles'], ['ar' => 'الهاتف فريد، وحالات الموافقة والبيانات الحساسة تخضع للصلاحية.', 'en' => 'Phone numbers are unique, and consent/sensitive data remains permission-controlled.']),
                self::step('create', 'customer-create-action', ['ar' => 'أنشئ ملفًا بموافقة', 'en' => 'Create with consent'], ['ar' => 'يتطلب إنشاء العميل اسمًا ثنائي اللغة وسجل موافقة ومفتاح منع التكرار.', 'en' => 'Creation requires bilingual names, a consent record, and an idempotency key.']),
                self::step('export', 'customer-export-action', ['ar' => 'صدّر ضمن النطاق', 'en' => 'Export within scope'], ['ar' => 'التصدير محدود ومراجع ومتاح للصلاحية الحساسة فقط؛ لا يضم بيانات الأطفال.', 'en' => 'Export is bounded, audited, and sensitive-permission controlled; child data is excluded.']),
            ],
            [
                self::field('phone', ['ar' => 'الهاتف', 'en' => 'Phone'], ['ar' => 'يُطبّع الخادم الرقم قبل فحص التفرد.', 'en' => 'The server normalizes the phone before enforcing uniqueness.']),
                self::field('consent', ['ar' => 'الموافقة', 'en' => 'Consent'], ['ar' => 'تُحفظ نسخة من الغرض والصياغة والإصدار والاحتفاظ.', 'en' => 'Purpose, wording, version, and retention are snapshotted.']),
            ],
            ['ar' => 'لا تستخدم هذا الدليل لتجاوز النطاق أو دمج ملفين دون سبب ومراجعة.', 'en' => 'Do not use this guide to bypass scope or merge profiles without a reason and review.'],
            ['ar' => 'القيم القانونية ومعدلات الولاء النهائية إعدادات مالك وليست قيمًا افتراضية.', 'en' => 'Legal wording and final loyalty rates are owner configuration, not invented defaults.'],
            ['ar' => 'إذا لم يظهر الملف، راجع الصلاحية والنطاق بدل استخدام رابط مباشر غير مصرح.', 'en' => 'If a profile is missing, review permission and scope instead of bypassing a direct route.'],
            ['ar' => 'افتح الملف لمراجعة التاريخ والولاء أو انتقل إلى POS لربط بيع معتمد.', 'en' => 'Open the profile for history and loyalty, or return to POS to link an approved sale.'],
            ['ar' => 'هل يمكن حذف سجل العميل؟ لا، تُحفظ المراجع التاريخية وتستخدم إجراءات تصحيح مسماة.', 'en' => 'Can a customer history row be deleted? No; history is preserved through named correction actions.'],
        );
    }

    /** @return array<string, mixed> */
    private static function readiness(string $route, string $routeTitleAr, string $routeTitleEn, string $kind, string $task, string $pending): array
    {
        $copy = match ($kind) {
            'financial' => [
                'header' => ['ar' => 'حدود الجاهزية المالية', 'en' => 'Financial readiness boundary'],
                'headerBody' => ['ar' => 'هذه شاشة قراءة فقط؛ لا تنشئ خصمًا أو ضريبة أو دفعة أو دليلًا رسميًا.', 'en' => 'This is read-only; it creates no discount, tax, payment, or official evidence.'],
                'warning' => ['ar' => 'اعتماد المالك مطلوب', 'en' => 'Owner approval required'],
                'warningBody' => ['ar' => "قرارات {$pending} ما زالت PENDING.", 'en' => "Decisions {$pending} remain PENDING."],
                'cards' => ['ar' => 'راجع بنود القرار', 'en' => 'Review decision items'],
                'summary' => ['ar' => 'اقرأ الإعداد المرصود', 'en' => 'Read observed configuration'],
            ],
            'shift' => [
                'header' => ['ar' => 'حدود الوردية والدرج', 'en' => 'Shift and drawer boundary'],
                'headerBody' => ['ar' => 'هذه شاشة قراءة فقط؛ لا تفتح وردية ولا تسجل حركة نقدية ولا تعرض expected.', 'en' => 'This is read-only; it opens no shift, records no cash movement, and exposes no expected amount.'],
                'warning' => ['ar' => 'الإغلاق الأعمى محفوظ', 'en' => 'Blind close is preserved'],
                'warningBody' => ['ar' => 'لا تظهر القيم المتوقعة قبل اعتماد مسار actual submission.', 'en' => 'Expected values remain hidden before an approved actual-submission workflow.'],
                'cards' => ['ar' => 'راجع نقاط السياسة', 'en' => 'Review policy points'],
                'summary' => ['ar' => 'اقرأ العدادات فقط', 'en' => 'Read counts only'],
            ],
            'offline' => [
                'header' => ['ar' => 'حدود التشغيل دون اتصال', 'en' => 'Offline boundary'],
                'headerBody' => ['ar' => 'هذه شاشة readiness فقط؛ لا queue أو sync أو replay أو offline sale مفعّل.', 'en' => 'This is readiness only; no queue, sync, replay, or offline sale is enabled.'],
                'warning' => ['ar' => 'Offline POS معطل افتراضيًا', 'en' => 'Transactional offline POS is disabled'],
                'warningBody' => ['ar' => 'لا توجد defaults أو حدود تشغيلية مخترعة.', 'en' => 'No invented operational defaults or limits are applied.'],
                'cards' => ['ar' => 'راجع بند OFF-01', 'en' => 'Review OFF-01'],
                'summary' => ['ar' => 'افهم المسموح والمحظور', 'en' => 'Understand permitted and blocked classes'],
            ],
            'reports' => [
                'header' => ['ar' => 'حدود لوحات المعلومات وتقارير المطابقة', 'en' => 'Dashboards and Reconciled Reports boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للقراءة فقط؛ لا يتم حساب KPI أو إنشاء تقرير أو تصدير أو تنبيه.', 'en' => 'This is read-only readiness; no KPI, report, export, or alert is calculated or created.'],
                'warning' => ['ar' => 'المصدر والنطاق قبل الرقم', 'en' => 'Source and scope before numbers'],
                'warningBody' => ['ar' => 'تبقى الصيغ والمصادر والنطاقات والعملة والدقة والحداثة معلقة؛ لا توجد أرقام معتمدة.', 'en' => 'Formulas, sources, scopes, currency, precision, and freshness remain pending; no certified figures exist.'],
                'cards' => ['ar' => 'راجع كتالوج التقارير', 'en' => 'Review report catalog'],
                'summary' => ['ar' => 'افهم سلالة المصدر', 'en' => 'Understand source lineage'],
            ],
            'uat' => [
                'header' => ['ar' => 'حدود الاختبار اليدوي وقاعدة الأدلة', 'en' => 'Manual UAT and evidence boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية لتخطيط الأدلة فقط؛ لا تسجل نجاح UAT أو اعتمادًا نهائيًا.', 'en' => 'This is evidence-planning readiness only; it records no UAT pass or final acceptance.'],
                'warning' => ['ar' => 'الاختبار والاعتماد معلقان', 'en' => 'Execution and acceptance are pending'],
                'warningBody' => ['ar' => 'تبقى السيناريوهات والمالكون والبيانات والأجهزة والأدلة والعيوب PENDING/TBD.', 'en' => 'Scenarios, owners, data, devices, evidence, and defects remain PENDING/TBD.'],
                'cards' => ['ar' => 'راجع حزمة السيناريوهات', 'en' => 'Review the scenario pack'],
                'summary' => ['ar' => 'افصل الجاهزية عن نتيجة UAT', 'en' => 'Separate readiness from UAT results'],
            ],
            'release' => [
                'header' => ['ar' => 'حدود القطع والإطلاق والإحالة', 'en' => 'Go-live and handover boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية لقائمة الإطلاق فقط؛ لا تنفذ نشرًا أو قطعًا أو اعتمادًا إنتاجيًا.', 'en' => 'This is launch-checklist readiness only; no deployment, cutover, or production approval is executed.'],
                'warning' => ['ar' => 'الإطلاق غير مصرح', 'en' => 'Go-live is not authorized'],
                'warningBody' => ['ar' => 'تبقى الموافقات والبيانات والنسخ الاحتياطي والتراجع والدعم PENDING/TBD.', 'en' => 'Approvals, data, backup, rollback, and support remain PENDING/TBD.'],
                'cards' => ['ar' => 'راجع قائمة القطع', 'en' => 'Review the cutover checklist'],
                'summary' => ['ar' => 'افصل قائمة الإطلاق عن التنفيذ', 'en' => 'Separate the checklist from execution'],
            ],
            'operations' => [
                'header' => ['ar' => 'حدود التشغيل والإحالة', 'en' => 'Operations and handover boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية قراءة فقط للتشغيل؛ لا تعرض أسرارًا ولا تثبت جاهزية الإنتاج.', 'en' => 'This is read-only operations readiness; it exposes no secrets and proves no production readiness.'],
                'warning' => ['ar' => 'الإنتاج غير معتمد', 'en' => 'Production is not approved'],
                'warningBody' => ['ar' => 'تبقى البنية والأجهزة والنسخ الاحتياطي والتدريب والدعم PENDING/TBD.', 'en' => 'Infrastructure, devices, backup, training, and support remain PENDING/TBD.'],
                'cards' => ['ar' => 'راجع حالة التشغيل', 'en' => 'Review operational status'],
                'summary' => ['ar' => 'افصل الدليل المحلي عن اعتماد الإنتاج', 'en' => 'Separate local evidence from production approval'],
            ],
            'master-data-migration' => [
                'header' => ['ar' => 'حدود استيراد البيانات الأساسية والقطع', 'en' => 'Master Data Import and Cutover boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للقراءة فقط؛ لا يتم رفع ملف أو تحليله أو حفظ دفعة أو تنفيذ قطع الإنتاج.', 'en' => 'This is read-only readiness; no file is uploaded, parsed, persisted, or cutover is executed.'],
                'warning' => ['ar' => 'لا يوجد استيراد فعلي', 'en' => 'No operational import'],
                'warningBody' => ['ar' => 'تظل الملفات والمصادر والدفعات وبيانات الإنتاج معلقة حتى اعتمادها.', 'en' => 'Files, sources, batches, and production data remain pending until approved.'],
                'cards' => ['ar' => 'راجع ترتيب التحميل', 'en' => 'Review load order'],
                'summary' => ['ar' => 'راجع مسار البيانات المعتمد', 'en' => 'Review the approved data path'],
                'steps' => [
                    ['title' => ['ar' => 'المصدر المعتمد', 'en' => 'Approved source'], 'body' => ['ar' => 'القوائم والملفات الموقعة مطلوبة قبل أي استيراد فعلي.', 'en' => 'Signed owner-approved lists or files are required before any operational import.'], 'selector' => '[data-guide="master-data-migration-readiness-boundary"]'],
                    ['title' => ['ar' => 'ترتيب التحميل', 'en' => 'Load order'], 'body' => ['ar' => 'الشركة والفروع والمتاجر تسبق المستخدمين والمنتجات والأسعار والمخزون الافتتاحي.', 'en' => 'Company, branches, and stores precede users, products, prices, and opening stock.'], 'selector' => '[data-guide="master-data-migration-readiness-first-card"]'],
                    ['title' => ['ar' => 'المطابقة قبل القطع', 'en' => 'Reconcile before cutover'], 'body' => ['ar' => 'تطابق العدد والمراجع والتفرد والعينات شرط قبل التقدم.', 'en' => 'Count, reference, uniqueness, and sample checks must pass before progression.'], 'selector' => '[data-guide="master-data-migration-readiness-card-6"]'],
                    ['title' => ['ar' => 'لا يوجد استيراد الآن', 'en' => 'No import is enabled'], 'body' => ['ar' => 'لا يوجد رفع أو حفظ دفعات أو تغيير تدميري في هذه المرحلة.', 'en' => 'No upload, batch persistence, or destructive update is enabled in this slice.'], 'selector' => '[data-guide="master-data-migration-readiness-empty"]'],
                ],
            ],
            'export-audit' => [
                'header' => ['ar' => 'حدود مركز التصدير وواجهات التدقيق', 'en' => 'Export Center and Audit Views boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للقراءة فقط؛ لا يتم إنشاء ملف أو تنزيله أو تعديل سجل تدقيق.', 'en' => 'This is read-only readiness; no artifact is generated/downloaded and no audit record is changed.'],
                'warning' => ['ar' => 'التنقيح والنطاق قبل الملف', 'en' => 'Redaction and scope before artifacts'],
                'warningBody' => ['ar' => 'تبقى الصيغ والحدود والاحتفاظ والتنقيح والصلاحيات والتدقيق PENDING.', 'en' => 'Formats, limits, retention, redaction, permissions, and audit remain PENDING.'],
                'cards' => ['ar' => 'راجع عقد التصدير', 'en' => 'Review export contract'],
                'summary' => ['ar' => 'افهم سجل التدقيق', 'en' => 'Understand audit history'],
            ],
            'alerts' => [
                'header' => ['ar' => 'حدود التنبيهات التشغيلية وطابور الاستثناءات', 'en' => 'Operational alerts and exception queue boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للقراءة فقط؛ لا يتم تقييم أو إنشاء أو إرسال أو تغيير أي تنبيه.', 'en' => 'This is read-only readiness; no alert is evaluated, created, delivered, or changed.'],
                'warning' => ['ar' => 'المشغل والمصدر والمالك قبل التنبيه', 'en' => 'Trigger, source, and owner before alerts'],
                'warningBody' => ['ar' => 'لا توجد صفوف تنبيهات أو استثناءات أو إشعارات في هذه الشريحة.', 'en' => 'No alert, exception, or notification rows exist in this slice.'],
                'cards' => ['ar' => 'راجع عقد التنبيهات', 'en' => 'Review alert contract'],
                'summary' => ['ar' => 'افهم حدود الاستثناءات', 'en' => 'Understand exception boundaries'],
            ],
            'quotations' => [
                'header' => ['ar' => 'حدود عروض الأسعار والمقترحات', 'en' => 'Quotations and Proposals boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للقراءة فقط؛ لا يتم إنشاء عرض أو اعتماد أو طباعة أو مشاركة أو تحويل.', 'en' => 'This is read-only readiness; no quote, approval, print, share, or conversion is created.'],
                'warning' => ['ar' => 'لا أثر تشغيلي أو مالي', 'en' => 'No operational or financial effect'],
                'warningBody' => ['ar' => 'تبقى عروض التجزئة والحفلات typed ومنفصلة، ولا تنشئ بيعًا أو فاتورة أو مخزونًا أو محفظة.', 'en' => 'Retail and party quotations remain typed and separate; they create no sale, invoice, inventory, or wallet effect.'],
                'cards' => ['ar' => 'راجع بنود العرض', 'en' => 'Review quotation points'],
                'summary' => ['ar' => 'افهم الحدود المستقبلية', 'en' => 'Understand future boundaries'],
            ],
            'party-final-close' => [
                'header' => ['ar' => 'حدود الإغلاق والتسوية النهائية للحفلات', 'en' => 'Party Final Close and Settlement boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم تجميد فاتورة أو إنشاء إيصال أو حركة محفظة.', 'en' => 'This is review readiness only; no invoice freeze, receipt, or wallet entry is created.'],
                'warning' => ['ar' => 'محفظة الأطراف فقط عند الاعتماد', 'en' => 'Party Wallet only at settlement'],
                'warningBody' => ['ar' => 'تبقى Product Wallet مستبعدة، وتبقى الأرصدة والإيصالات والتسوية معلقة.', 'en' => 'Product Wallet remains excluded; balances, receipts, and settlement remain pending.'],
                'cards' => ['ar' => 'راجع قائمة الإغلاق', 'en' => 'Review close checklist'],
                'summary' => ['ar' => 'افهم ما قبل التجميد', 'en' => 'Understand pre-freeze checks'],
            ],
            'party-asset-events' => [
                'header' => ['ar' => 'حدود تلف وفقد وصيانة الأصول', 'en' => 'Asset Damage, Loss, Maintenance boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم إنشاء حدث أو تسجيل تكلفة أو تغيير حالة.', 'en' => 'This is review readiness only; no event, cost, or state change is created.'],
                'warning' => ['ar' => 'التكلفة تشغيلية فقط', 'en' => 'Operational cost only'],
                'warningBody' => ['ar' => 'تبقى المعالجة التشغيلية منفصلة عن دفتر الأستاذ العام، ولا تعرض هذه الشاشة مبالغ أو أدلة.', 'en' => 'Operational handling remains separate from the general ledger; this screen renders no amounts or evidence.'],
                'cards' => ['ar' => 'راجع الحدث والأدلة', 'en' => 'Review event and evidence'],
                'summary' => ['ar' => 'افهم الاعتماد والتصحيح', 'en' => 'Understand approval and correction'],
            ],
            'party-assets' => [
                'header' => ['ar' => 'حدود أصول التأجير والتقويم', 'en' => 'Rental Assets and Calendar boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم إنشاء أصل أو حجز أو تسجيل خروج أو إرجاع.', 'en' => 'This is review readiness only; no asset, reservation, checkout, or return is created.'],
                'warning' => ['ar' => 'الأصل الفريد منفصل', 'en' => 'Unique assets are separate'],
                'warningBody' => ['ar' => 'تبقى أصول التأجير منفصلة عن المواد الاستهلاكية ومنتجات التجزئة، ولا يتم عرض التكلفة.', 'en' => 'Rental assets remain separate from consumables and retail products; cost is not rendered.'],
                'cards' => ['ar' => 'راجع حالات الأصل', 'en' => 'Review asset states'],
                'summary' => ['ar' => 'تحقق من التعارض والتدقيق', 'en' => 'Confirm conflict and audit'],
            ],
            'party-operating' => [
                'header' => ['ar' => 'حدود أوامر التشغيل والمواد الاستهلاكية', 'en' => 'Party Operating Orders and Consumables boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم إنشاء أمر أو حجز مخزون أو تسجيل إصدار أو إرجاع.', 'en' => 'This is review readiness only; no order, stock reservation, issue, or return is created.'],
                'warning' => ['ar' => 'المخزون التشغيلي منفصل', 'en' => 'Operating stock is separate'],
                'warningBody' => ['ar' => 'لا يعاد استخدام مسارات منتجات التجزئة أو تعديل الرصيد مباشرة لأوامر الحفلات.', 'en' => 'Retail product flows and direct balance edits are not reused for party orders.'],
                'cards' => ['ar' => 'راجع دورة الأمر', 'en' => 'Review order lifecycle'],
                'summary' => ['ar' => 'تحقق من المصدر والتدقيق', 'en' => 'Confirm source and audit'],
            ],
            'party-payments' => [
                'header' => ['ar' => 'حدود دفعات الحفلات ورصيد الطرف', 'en' => 'Party Payments and Balance boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم تسجيل دفعة أو إيصال أو رصيد أو قيد Party Wallet.', 'en' => 'This is review readiness only; no payment, receipt, balance, or Party Wallet entry is posted.'],
                'warning' => ['ar' => 'الفصل المالي مطلوب', 'en' => 'Financial separation is required'],
                'warningBody' => ['ar' => 'تبقى Product Wallet ودفعات التجزئة خارج نطاق تسوية الحفلات.', 'en' => 'Product Wallet and retail payments remain outside party settlement.'],
                'cards' => ['ar' => 'راجع أدلة الدفع', 'en' => 'Review payment evidence'],
                'summary' => ['ar' => 'تحقق من حدود الرصيد', 'en' => 'Confirm balance boundaries'],
            ],
            'party' => [
                'header' => ['ar' => 'حدود الحفلات والفاتورة العاملة', 'en' => 'Party and Working Invoice boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم إنشاء حجز أو عميل أو طفل أو فاتورة عاملة.', 'en' => 'This is review readiness only; no booking, customer, child, or working invoice is created.'],
                'warning' => ['ar' => 'الفصل والخصوصية مطلوبان', 'en' => 'Separation and privacy are required'],
                'warningBody' => ['ar' => 'تبقى متاجر الحفلات والخدمات والجدولة وبيانات الطفل وسياسة الإغلاق معلقة.', 'en' => 'Party stores, services, scheduling, child data, and final-close policy remain pending.'],
                'cards' => ['ar' => 'راجع حدود الحجز', 'en' => 'Review booking boundaries'],
                'summary' => ['ar' => 'تحقق من جاهزية الفاتورة', 'en' => 'Confirm invoice readiness'],
            ],
            'returns' => [
                'header' => ['ar' => 'حدود المرتجعات والاستبدال', 'en' => 'Returns and Exchanges boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية للمراجعة فقط؛ لا يتم إنشاء مرتجع أو استرداد أو استبدال أو حركة مخزون.', 'en' => 'This is review readiness only; no return, refund, exchange, or stock movement is created.'],
                'warning' => ['ar' => 'المصدر والاعتماد مطلوبان', 'en' => 'Source and approval are required'],
                'warningBody' => ['ar' => 'تبقى الفاتورة/إيصال الهدية والنافذة والحالة والتسوية معلقة حتى اعتماد السياسة.', 'en' => 'Invoice/Gift Receipt source, window, condition, and settlement remain pending until policy approval.'],
                'cards' => ['ar' => 'راجع متطلبات المصدر', 'en' => 'Review source requirements'],
                'summary' => ['ar' => 'تحقق من حدود الاستعداد', 'en' => 'Confirm readiness boundary'],
            ],
            'gift-receipts' => [
                'header' => ['ar' => 'حدود إيصال الهدية', 'en' => 'Gift Receipt boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية مرجع بلا أسعار؛ لا إصدار أو تحقق أو إعادة طباعة مفعلة.', 'en' => 'This is price-free reference readiness; issue, validation, and reprint are disabled.'],
                'warning' => ['ar' => 'الخصوصية والسعر غير قابلين للتجاوز', 'en' => 'Privacy and price exclusion are non-negotiable'],
                'warningBody' => ['ar' => 'لا تعرض أي سعر أو حقل يسمح باستنتاج السعر، ولا تنشئ مرجعًا دون مصدر معتمد.', 'en' => 'Do not expose prices or price-inference fields, and do not create a reference without an approved source.'],
                'cards' => ['ar' => 'راجع أهلية المصدر', 'en' => 'Review source eligibility'],
                'summary' => ['ar' => 'تحقق من حالة المرجع', 'en' => 'Confirm reference status'],
            ],
            'gift-cards' => [
                'header' => ['ar' => 'حدود بطاقة الهدية', 'en' => 'Gift Card boundary'],
                'headerBody' => ['ar' => 'هذه جاهزية دفتر فقط؛ لا إصدار أو رصيد أو استخدام أو إبطال مفعّل.', 'en' => 'This is ledger readiness only; issue, balance, redemption, and void are disabled.'],
                'warning' => ['ar' => 'لا توجد بطاقة نشطة', 'en' => 'No active card exists'],
                'warningBody' => ['ar' => 'تبقى الصلاحية والحامل والتزامن وسياسات الاستخدام معلقة.', 'en' => 'Validity, holder, concurrency, and use policies remain pending.'],
                'cards' => ['ar' => 'راجع الصلاحية والمعرف', 'en' => 'Review validity and identifier'],
                'summary' => ['ar' => 'تحقق من حالة الدفتر', 'en' => 'Confirm ledger status'],
            ],
            'customer' => [
                'header' => ['ar' => 'حدود customer والولاء', 'en' => 'Customer and loyalty boundary'],
                'headerBody' => ['ar' => 'القيم المعروضة من Settings، لكنها لا تنفذ customer أو consent أو loyalty mutation.', 'en' => 'Values come from Settings but do not execute customer, consent, or loyalty mutations.'],
                'warning' => ['ar' => 'القيم ليست سياسة معتمدة', 'en' => 'Values are not approved policy'],
                'warningBody' => ['ar' => 'كل قيمة محلية تحتاج owner approval ولا تستهلكها معاملات domain.', 'en' => 'Each local value needs owner approval and is not consumed by domain transactions.'],
                'cards' => ['ar' => 'راجع قيمة قرار واحدة', 'en' => 'Review one decision value'],
                'summary' => ['ar' => 'راجع العقود المؤجلة', 'en' => 'Review deferred contracts'],
            ],
            default => [
                'header' => ['ar' => 'حدود إعدادات القرار', 'en' => 'Decision settings boundary'],
                'headerBody' => ['ar' => 'كل حفظ ينشئ version محلية وaudit؛ لا تتحول القيمة إلى سياسة معتمدة.', 'en' => 'Each save creates a local version and audit; the value does not become approved policy.'],
                'warning' => ['ar' => 'إعداد Local/Dev فقط', 'en' => 'Local/Dev configuration only'],
                'warningBody' => ['ar' => 'لا تدخل أسرارًا أو بيانات شخصية أو صياغة قانونية غير معتمدة.', 'en' => 'Do not enter secrets, personal data, or unapproved legal wording.'],
                'cards' => ['ar' => 'راجع بطاقة قرار', 'en' => 'Review a decision card'],
                'summary' => ['ar' => 'احفظ version محلية فقط', 'en' => 'Save a local version only'],
            ],
        };

        $isSettings = $kind === 'settings';
        $actions = $isSettings
            ? self::actions([
                ['settings.review', ['ar' => 'مراجعة مفاتيح القرار', 'en' => 'Review decision keys'], 'company_settings.view'],
                ['settings.save-local', ['ar' => 'حفظ version محلية', 'en' => 'Save a local version'], 'company_settings.edit'],
            ])
            : self::actions([
                ['readiness.review', ['ar' => 'مراجعة الجاهزية', 'en' => 'Review readiness'], 'pos_sales.view'],
            ]);

        $steps = [
            self::step('header', $kind === 'settings' ? 'customer-settings-header' : ($kind === 'customer' ? 'customer-readiness-header' : "{$kind}-readiness-header"), $copy['header'], $copy['headerBody']),
            self::step('warning', $kind === 'settings' ? 'customer-settings-boundary' : ($kind === 'customer' ? 'customer-readiness-boundary' : "{$kind}-readiness-boundary"), $copy['warning'], $copy['warningBody']),
            self::step('card', $kind === 'settings' ? 'customer-settings-first-card-heading' : ($kind === 'customer' ? 'customer-readiness-first-card-heading' : "{$kind}-readiness-first-card"), $copy['cards'], ['ar' => 'ابدأ من العنصر المحدد ثم راجع بقية العناصر دون تنفيذ mutation غير معتمد.', 'en' => 'Start with the highlighted item, then review the remaining items without performing unapproved mutations.']),
            self::step('summary', $kind === 'settings' ? 'customer-settings-save-action' : ($kind === 'customer' ? 'customer-readiness-deferred' : "{$kind}-readiness-summary"), $copy['summary'], ['ar' => 'اقرأ هذه المنطقة لتثبيت ما هو مرصود وما هو مؤجل.', 'en' => 'Use this area to distinguish observed local data from deferred policy.']),
        ];

        return [
            'route_names' => [$route],
            'title' => ['ar' => $routeTitleAr, 'en' => $routeTitleEn],
            'purpose' => ['ar' => "دليل {$routeTitleAr} ضمن {$task}؛ {$pending} ما زالت معلقة.", 'en' => "Guide for {$routeTitleEn} within {$task}; {$pending} remain pending."],
            'when_to_use' => ['ar' => 'استخدمه للقراءة والتحقق فقط قبل توفر اعتماد المالك.', 'en' => 'Use it for read-only review before owner approval is available.'],
            'permissions' => $isSettings ? ['company_settings.view'] : ['pos_sales.view'],
            'approved_actions' => $actions,
            'stories' => [$task],
            'flows' => ['FLW-READINESS-01'],
            'acceptance_criteria' => ['AC-UI-08', 'AC-UI-12'],
            'steps' => $steps,
            'tour_steps' => $steps,
            'fields' => [self::field('status', ['ar' => 'الحالة', 'en' => 'Status'], ['ar' => 'PENDING تعني أن القيمة أو السياسة لم تعتمد.', 'en' => 'PENDING means the value or policy is not approved.'])],
            'notes' => ['ar' => 'هذه الشاشة لا تمنح صلاحية domain ولا تنشئ سجلًا تشغيليًا.', 'en' => 'This screen grants no domain capability and creates no operational record.'],
            'warnings' => ['ar' => 'لا تحول قيمة Local/Dev إلى قرار Production.', 'en' => 'Do not treat a Local/Dev value as a Production decision.'],
            'errors' => ['ar' => 'إذا غاب عنصر، راجع الصلاحية والنطاق بدل تجاوز الواجهة.', 'en' => 'If an item is missing, review permission and scope instead of bypassing the UI.'],
            'next_step' => ['ar' => 'عد إلى POS أو افتح الدليل المرتبط بعد مراجعة الحدود.', 'en' => 'Return to POS or open the related guide after reviewing the boundary.'],
            'faq' => ['ar' => 'هل هذه الشاشة تنفذ mutation؟ لا.', 'en' => 'Does this screen execute a mutation? No.'],
        ];
    }

    /**
     * @param  list<array{0:string,1:array{ar:string,en:string},2:string}>  $actions
     * @return list<array{key:string,label:array{ar:string,en:string},required_permission:string}>
     */
    private static function actions(array $actions): array
    {
        return array_map(static fn (array $action): array => ['key' => $action[0], 'label' => $action[1], 'required_permission' => $action[2]], $actions);
    }

    /**
     * @param  array{ar:string,en:string}  $title
     * @param  array{ar:string,en:string}  $body
     * @return array{key:string,selector:string,title:array{ar:string,en:string},body:array{ar:string,en:string}}
     */
    private static function step(string $key, string $selector, array $title, array $body): array
    {
        return ['key' => $key, 'selector' => "[data-guide=\"{$selector}\"]", 'title' => $title, 'body' => $body];
    }

    /**
     * @param  array{ar:string,en:string}  $title
     * @param  array{ar:string,en:string}  $body
     * @return array{key:string,title:array{ar:string,en:string},body:array{ar:string,en:string}}
     */
    private static function field(string $key, array $title, array $body): array
    {
        return compact('key', 'title', 'body');
    }

    /**
     * @param  list<string>  $routeNames
     * @param  array<string,mixed>  $title
     * @param  array<string,mixed>  $purpose
     * @param  array<string,mixed>  $whenToUse
     * @param  list<string>  $permissions
     * @param  list<array<string,mixed>>  $approvedActions
     * @param  list<string>  $stories
     * @param  list<string>  $flows
     * @param  list<string>  $acceptanceCriteria
     * @param  list<array<string,mixed>>  $steps
     * @param  list<array<string,mixed>>  $fields
     * @param  array<string,mixed>  $notes
     * @param  array<string,mixed>  $warnings
     * @param  array<string,mixed>  $errors
     * @param  array<string,mixed>  $nextStep
     * @param  array<string,mixed>  $faq
     * @return array<string,mixed>
     */
    private static function definition(array $routeNames, array $title, array $purpose, array $whenToUse, array $permissions, array $approvedActions, array $stories, array $flows, array $acceptanceCriteria, array $steps, array $fields, array $notes, array $warnings, array $errors, array $nextStep, array $faq): array
    {
        return compact('routeNames', 'title', 'purpose', 'whenToUse', 'permissions', 'approvedActions', 'stories', 'flows', 'acceptanceCriteria', 'steps', 'fields', 'notes', 'warnings', 'errors', 'nextStep', 'faq') + ['route_names' => $routeNames, 'when_to_use' => $whenToUse, 'approved_actions' => $approvedActions, 'acceptance_criteria' => $acceptanceCriteria, 'next_step' => $nextStep, 'purpose' => $purpose, 'title' => $title];
    }
}
