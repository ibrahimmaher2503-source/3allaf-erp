<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use LogicException;

final class InventoryTutorialFactory
{
    /** @return array<string, mixed> */
    public static function make(string $screenId): array
    {
        $definitions = [
            'UI-INV-001' => self::definition(
                ['inventory.index'],
                ['ar' => 'دليل مركز تحكم المخزون', 'en' => 'Inventory Control Center Guide'],
                ['ar' => 'يعرض الرصيد المتاح، الحركات، التحويلات، التسويات، وخطط الجرد ضمن نطاق المتاجر المصرح بها.', 'en' => 'Shows balances, availability, movements, transfers, adjustments, and count sessions within the user’s authorized store scope.'],
                ['ar' => 'استخدمه كبداية لمراجعة حالة المخزون المحلية قبل فتح بطاقة منتج أو تنفيذ workflow.', 'en' => 'Use it as the starting point for reviewing local inventory before opening a stock card or running a workflow.'],
                ['inventory_stock_card.view'],
                self::actions([
                    ['inventory.overview', ['ar' => 'مراجعة ملخص المخزون والتوافر', 'en' => 'Review inventory and availability summary'], 'inventory_stock_card.view'],
                    ['inventory.stock-card', ['ar' => 'فتح بطاقة منتج', 'en' => 'Open a product stock card'], 'inventory_stock_card.view'],
                    ['inventory.workflows', ['ar' => 'مراجعة التحويلات والتسويات والجرد', 'en' => 'Review transfers, adjustments, and counts'], 'inventory_stock_card.view'],
                ]),
                ['US-013'], ['FLW-INV-01', 'FLW-INV-02', 'FLW-INV-03', 'FLW-INV-06'], ['AC-UI-08', 'AC-UI-12'],
                [
                    self::step('hero', 'inventory-hero', ['ar' => 'ابدأ من حدود الشاشة', 'en' => 'Start with the screen boundary'], ['ar' => 'هذه شاشة Local Demo؛ لا تعني الأرقام موافقة Production أو UAT أو opening-stock cutover.', 'en' => 'This is a Local Demo screen; its numbers do not approve Production, UAT, or opening-stock cutover.']),
                    self::step('summary', 'inventory-summary', ['ar' => 'اقرأ الملخص', 'en' => 'Read the summary'], ['ar' => 'استخدم العدادات لمعرفة وجود أرصدة وحركات وتحويلات وتسويات وخطط جرد قبل الانتقال للتفاصيل.', 'en' => 'Use the counters to see whether balances, movements, transfers, adjustments, and counts exist before opening details.']),
                    self::step('balances', 'inventory-balances-heading', ['ar' => 'افهم المتاح', 'en' => 'Understand availability'], ['ar' => 'المتاح يساوي on-hand ناقص reserved، بينما in-transit منفصل حتى الاستلام.', 'en' => 'Available equals on-hand minus reserved; in-transit stays separate until receipt.']),
                    self::step('workflows', 'inventory-transfers', ['ar' => 'تابع الحالات', 'en' => 'Follow workflow states'], ['ar' => 'راجع حالة التحويل أو التسوية أو الجرد قبل الضغط على أي إجراء؛ كل إجراء مقيد بالصلاحية والحالة.', 'en' => 'Review transfer, adjustment, or count state before acting; every action is permission- and state-gated.']),
                    self::step('ledger', 'inventory-movements', ['ar' => 'ارجع إلى الدفتر', 'en' => 'Trace the ledger'], ['ar' => 'استخدم الدفتر لمطابقة كل حركة مع مصدرها. الحركات append-only ولا تعدل الرصيد مباشرة.', 'en' => 'Use the ledger to trace each movement to its source. Movements are append-only and do not directly edit balances.']),
                ],
                [
                    self::field('scope', ['ar' => 'نطاق المتاجر', 'en' => 'Store scope'], ['ar' => 'تعرض الشاشة المتاجر المرئية للمستخدم فقط، مع تجاوز صريح للمسؤول الأعلى.', 'en' => 'The screen shows only stores visible to the user, with an explicit super-admin bypass.']),
                    self::field('cost', ['ar' => 'WAC والتكلفة', 'en' => 'WAC and cost'], ['ar' => 'تظهر التكلفة فقط عند امتلاك صلاحية cost view؛ غيابها لا يمنع مراجعة الكميات.', 'en' => 'Cost appears only with the cost-view permission; its absence does not block quantity review.']),
                ],
                ['ar' => 'لا تستخدم العدادات كبديل عن مراجعة المصدر أو التدقيق.', 'en' => 'Do not use counters as a substitute for source or audit review.'],
                ['ar' => 'بيانات Demo لا تمثل مخزونًا فعليًا أو اعتمادًا ماليًا.', 'en' => 'Demo data is not real stock or financial approval.'],
                ['ar' => 'إذا اختفى متجر أو إجراء، راجع الصلاحية والنطاق بدل محاولة تجاوز الواجهة.', 'en' => 'If a store or action is missing, review permission and scope instead of bypassing the UI.'],
                ['ar' => 'بعد قراءة الملخص، افتح بطاقة المنتج أو workflow المطلوب ثم عد إلى الدفتر للمطابقة.', 'en' => 'After reading the summary, open the required stock card or workflow, then return to the ledger for reconciliation.'],
                ['ar' => 'هل المتاح هو الرصيد الفعلي؟ لا، هو on-hand ناقص reserved. هل in-transit داخل on-hand؟ لا. هل يمكن تعديل الرصيد من الشاشة؟ لا، الحركة تمر عبر posting append-only.', 'en' => 'Is available the physical balance? No, it is on-hand minus reserved. Is in-transit included in on-hand? No. Can the screen edit balances directly? No, posting is append-only.']
            ),
            'UI-INV-002' => self::definition(
                ['inventory.stock-card'],
                ['ar' => 'دليل بطاقة المخزون', 'en' => 'Stock Card Guide'],
                ['ar' => 'تربط بطاقة المنتج الرصيد والحركات والتكلفة بالمخزن المرئي للمستخدم.', 'en' => 'The stock card connects product balance, movements, and cost for visible stores.'],
                ['ar' => 'استخدمها عند تحليل منتج واحد أو مراجعة WAC ومصدر الحركات.', 'en' => 'Use it when analyzing one product or reviewing WAC and movement sources.'],
                ['inventory_stock_card.view'], self::actions([
                    ['stock-card.balance', ['ar' => 'مراجعة رصيد المنتج', 'en' => 'Review product balance'], 'inventory_stock_card.view'],
                    ['stock-card.ledger', ['ar' => 'مراجعة دفتر المنتج', 'en' => 'Review product ledger'], 'inventory_stock_card.view'],
                    ['stock-card.cost', ['ar' => 'عرض WAC عند السماح', 'en' => 'View WAC when allowed'], 'inventory_stock_card.cost_view'],
                ]), ['US-013'], ['FLW-INV-01'], ['AC-UI-08'],
                [
                    self::step('header', 'inventory-hero', ['ar' => 'تحقق من المنتج', 'en' => 'Confirm the product'], ['ar' => 'تحقق من كود المنتج قبل تفسير أي كمية أو حركة.', 'en' => 'Confirm the product code before interpreting any quantity or movement.']),
                    self::step('balance', 'inventory-balances', ['ar' => 'راجع الرصيد حسب المتجر', 'en' => 'Review balance by store'], ['ar' => 'قارن on-hand وreserved وavailable وin-transit لكل متجر مرئي.', 'en' => 'Compare on-hand, reserved, available, and in-transit for each visible store.']),
                    self::step('cost', 'inventory-balances', ['ar' => 'اقرأ WAC بحذر', 'en' => 'Read WAC carefully'], ['ar' => 'WAC معلومة محمية بالصلاحية ولا تعني اعتماد تكلفة Production.', 'en' => 'WAC is permission-protected and does not approve Production costing.']),
                    self::step('ledger', 'inventory-movements', ['ar' => 'طابق الحركات', 'en' => 'Reconcile movements'], ['ar' => 'استخدم مصدر الحركة والكمية والإشارة لمطابقة الرصيد.', 'en' => 'Use movement source, quantity, and sign to reconcile the balance.']),
                ],
                [self::field('product', ['ar' => 'كود المنتج', 'en' => 'Product code'], ['ar' => 'الكود هو المعرف التشغيلي المختلط الاتجاه؛ اقرأه كما هو.', 'en' => 'The code is a mixed-direction operational identifier; read it exactly as shown.']), self::field('wac', ['ar' => 'WAC', 'en' => 'WAC'], ['ar' => 'لا يظهر إلا لصاحب صلاحية التكلفة.', 'en' => 'It appears only to users with cost permission.'])],
                ['ar' => 'لا تفسر حركة سالبة وحدها دون قراءة source.', 'en' => 'Do not interpret a negative movement without reading its source.'], ['ar' => 'التكلفة Demo وليست قيمة محاسبية نهائية.', 'en' => 'Demo cost is not a final accounting value.'], ['ar' => 'إذا لم يظهر WAC، فهذا غالبًا قيد صلاحية وليس خطأ في الكمية.', 'en' => 'If WAC is hidden, it is usually a permission boundary, not a quantity error.'], ['ar' => 'بعد المطابقة، انتقل إلى التحويل أو التسوية فقط إذا كان لديك workflow معتمد.', 'en' => 'After reconciliation, move to a transfer or adjustment only when you have an authorized workflow.'], ['ar' => 'هل البطاقة تعدل المنتج؟ لا، هي قراءة scoped فقط.', 'en' => 'Does the card edit the product? No, it is a scoped read-only view.']
            ),
            'UI-INV-003' => self::definition(
                ['inventory.movements'],
                ['ar' => 'دليل دفتر حركات المخزون', 'en' => 'Inventory Movement Ledger Guide'],
                ['ar' => 'يعرض الدفتر الحركات غير القابلة للتعديل ومصدر كل ترحيل.', 'en' => 'The ledger displays immutable movements and the source of each posting.'],
                ['ar' => 'استخدمه بعد أي workflow لمراجعة الأثر append-only.', 'en' => 'Use it after any workflow to review its append-only effect.'],
                ['inventory_stock_card.view'], self::actions([
                    ['movements.trace', ['ar' => 'تتبع مصدر الحركة', 'en' => 'Trace movement source'], 'inventory_stock_card.view'],
                    ['movements.reconcile', ['ar' => 'مطابقة الحركة مع الرصيد', 'en' => 'Reconcile movement to balance'], 'inventory_stock_card.view'],
                ]), ['US-013', 'US-032'], ['FLW-INV-01'], ['AC-UI-08', 'AC-UI-12'],
                [self::step('header', 'inventory-hero', ['ar' => 'ابدأ بالمرجع', 'en' => 'Start with the reference'], ['ar' => 'كل حركة يجب أن تكون مرتبطة بمصدر أو Demo opening reference.', 'en' => 'Each movement must have a source or a Demo opening reference.']), self::step('table', 'inventory-movements', ['ar' => 'اقرأ الإشارة', 'en' => 'Read the sign'], ['ar' => 'الموجب يدخل إلى الرصيد والسالب يستهلك منه؛ لا تحول الإشارة يدويًا.', 'en' => 'Positive quantities add to stock and negative quantities consume it; do not reinterpret the sign manually.']), self::step('source', 'inventory-movements', ['ar' => 'افتح المصدر المنطقي', 'en' => 'Trace the logical source'], ['ar' => 'استخدم نوع المصدر والمعرف لمراجعة التحويل أو التسوية أو الجرد.', 'en' => 'Use source type and identifier to review the transfer, adjustment, or count.']), self::step('safety', 'inventory-balances', ['ar' => 'تحقق من الرصيد', 'en' => 'Check the balance'], ['ar' => 'طابق مجموع الحركات مع on-hand ضمن نفس المتجر والمنتج.', 'en' => 'Match movement totals to on-hand for the same store and product.'])], [self::field('posted', ['ar' => 'وقت الترحيل', 'en' => 'Posted time'], ['ar' => 'وقت الترحيل يساعد في فهم reference time ولا يعيد ترتيب الحركة يدويًا.', 'en' => 'Posted time helps interpret reference time; it does not allow manual reordering.'])], ['ar' => 'لا يوجد تعديل مباشر من هذه الشاشة.', 'en' => 'There is no direct edit from this screen.'], ['ar' => 'الدفتر المحلي لا يمثل دفتر Production نهائيًا.', 'en' => 'The local ledger is not a final Production ledger.'], ['ar' => 'إذا وجدت فرقًا، راجع المصدر وسجل التدقيق قبل طلب correction.', 'en' => 'If you find a difference, review the source and audit log before requesting a correction.'], ['ar' => 'بعد تحديد المصدر، افتح الشاشة التشغيلية المرتبطة به.', 'en' => 'After identifying the source, open its related operational screen.'], ['ar' => 'هل يمكن حذف حركة؟ لا، posting append-only.', 'en' => 'Can a movement be deleted? No, posting is append-only.']
            ),
            'UI-INV-004' => self::definition(
                ['inventory.transfers'], ['ar' => 'دليل تحويلات المخزون', 'en' => 'Stock Transfers Guide'], ['ar' => 'تدير شاشة التحويلات دورة نقل المخزون بين المتاجر المرئية.', 'en' => 'The transfers screen manages stock movement between visible stores.'], ['ar' => 'استخدمها لمراجعة حالة التحويل قبل الاعتماد أو الإرسال.', 'en' => 'Use it to review transfer state before approval or dispatch.'], ['transfers.view'], self::actions([
                    ['transfers.review', ['ar' => 'مراجعة التحويلات', 'en' => 'Review transfers'], 'transfers.view'], ['transfers.approve', ['ar' => 'اعتماد تحويل مرسل', 'en' => 'Approve a submitted transfer'], 'transfers.approve'], ['transfers.dispatch', ['ar' => 'إرسال تحويل معتمد', 'en' => 'Dispatch an approved transfer'], 'transfers.dispatch'], ['transfers.receive', ['ar' => 'تسجيل الاستلام', 'en' => 'Record receipt'], 'transfers.receive'], ['transfers.difference', ['ar' => 'إغلاق مراجعة الفرق', 'en' => 'Resolve difference review'], 'transfers.difference'],
                ]), ['US-014'], ['FLW-INV-02'], ['AC-UI-08', 'AC-UI-12'],
                [self::step('header', 'inventory-hero', ['ar' => 'اقرأ اتجاه النقل', 'en' => 'Read the transfer direction'], ['ar' => 'تحقق من source وdestination قبل أي اعتماد أو إرسال.', 'en' => 'Confirm source and destination before approval or dispatch.']), self::step('state', 'inventory-transfers', ['ar' => 'احترم تسلسل الحالات', 'en' => 'Respect the state sequence'], ['ar' => 'التسلسل المحلي هو submitted ثم approved ثم in_transit ثم received أو difference_review.', 'en' => 'The local sequence is submitted, approved, in_transit, then received or difference_review.']), self::step('action', 'inventory-transfer-actions', ['ar' => 'نفذ الإجراء الظاهر فقط', 'en' => 'Use only the visible action'], ['ar' => 'الإجراء الظاهر يعكس الحالة والصلاحية؛ لا تعيد إرسال نموذج مخفي.', 'en' => 'The visible action reflects state and permission; do not replay hidden forms.']), self::step('audit', 'inventory-movements', ['ar' => 'راجع الأثر', 'en' => 'Review the effect'], ['ar' => 'بعد الإرسال أو الاستلام، راجع dispatch/receipt movements في الدفتر.', 'en' => 'After dispatch or receipt, review dispatch/receipt movements in the ledger.'])], [self::field('state', ['ar' => 'الحالة', 'en' => 'State'], ['ar' => 'الحالة تمنع الانتقال غير المسموح server-side.', 'en' => 'State prevents invalid transitions server-side.']), self::field('difference', ['ar' => 'الفرق', 'en' => 'Difference'], ['ar' => 'الفرق يحتاج نوعًا وسببًا قبل الإغلاق.', 'en' => 'A difference needs a type and reason before resolution.'])], ['ar' => 'لا تعتمد تحويلًا خارج نطاق المتاجر المرئية.', 'en' => 'Do not approve a transfer outside visible store scope.'], ['ar' => 'الاستلام الجزئي يذهب إلى مراجعة فرق ولا يصبح استلامًا كاملًا.', 'en' => 'Partial receipt enters difference review and is not a full receipt.'], ['ar' => 'إذا اختفى الزر، راجع الحالة والصلاحية والنطاق.', 'en' => 'If an action is missing, review state, permission, and scope.'], ['ar' => 'بعد dispatch، افتح صفحة receipt عند وصول الشحنة.', 'en' => 'After dispatch, open the receipt page when the shipment arrives.'], ['ar' => 'هل يمكن الاستلام بعد difference_review؟ لا، يجب حل الفرق أولًا.', 'en' => 'Can receipt be recorded after difference_review? No, resolve the difference first.']
            ),
            'UI-INV-005' => self::definition(
                ['inventory.transfers.dispatch-page'], ['ar' => 'دليل إرسال التحويل', 'en' => 'Transfer Dispatch Guide'], ['ar' => 'تؤكد شاشة الإرسال خروج الكمية من المصدر ونقلها إلى حالة in-transit.', 'en' => 'The dispatch screen confirms source departure and moves the transfer to in-transit.'], ['ar' => 'استخدمها فقط بعد اعتماد التحويل والتحقق من المصدر.', 'en' => 'Use it only after approval and source verification.'], ['transfers.view'], self::actions([
                    ['dispatch.review', ['ar' => 'مراجعة خطوط الإرسال', 'en' => 'Review dispatch lines'], 'transfers.view'], ['dispatch.post', ['ar' => 'إرسال التحويل', 'en' => 'Dispatch transfer'], 'transfers.dispatch'],
                ]), ['US-014'], ['FLW-INV-02'], ['AC-UI-08'], [self::step('context', 'inventory-transfers', ['ar' => 'تحقق من المصدر', 'en' => 'Confirm the source'], ['ar' => 'تأكد من أن المتجر المصدر هو المخزن الصحيح وأن الحالة approved.', 'en' => 'Confirm the source store is correct and the state is approved.']), self::step('action', 'inventory-transfer-actions', ['ar' => 'أرسل مرة واحدة', 'en' => 'Dispatch once'], ['ar' => 'الإرسال idempotent ومقيد بالحالة؛ لا تكرر الطلب عند بطء الصفحة.', 'en' => 'Dispatch is idempotent and state-gated; do not repeat a request during a slow response.']), self::step('result', 'inventory-movements', ['ar' => 'راجع حركة الإرسال', 'en' => 'Review dispatch movement'], ['ar' => 'تحقق من حركة transfer_dispatch ومن انتقال التحويل إلى in-transit.', 'en' => 'Confirm the transfer_dispatch movement and the move to in-transit.'])], [self::field('quantity', ['ar' => 'كمية الإرسال', 'en' => 'Dispatch quantity'], ['ar' => 'تؤخذ من خطوط التحويل ولا تُدخل من مصدر غير موثوق.', 'en' => 'It comes from transfer lines and is not sourced from an untrusted input.'])], ['ar' => 'الإرسال يغير حالة التحويل ويؤثر على in-transit.', 'en' => 'Dispatch changes transfer state and affects in-transit.'], ['ar' => 'لا يعني الإرسال أن الوجهة استلمت البضاعة.', 'en' => 'Dispatch does not mean the destination received the goods.'], ['ar' => 'إذا فشل الإرسال، اقرأ الرسالة العامة وراجع السجل الداخلي بدل إعادة المحاولة العشوائية.', 'en' => 'If dispatch fails, read the generic message and review internal logs instead of retrying blindly.'], ['ar' => 'بعد نجاح الإرسال، سلّم المهمة إلى مستخدم الوجهة لفتح receipt.', 'en' => 'After dispatch, hand off to the destination user for receipt.'], ['ar' => 'هل يزيد on-hand في الوجهة فورًا؟ لا، يبقى in-transit حتى receipt.', 'en' => 'Does destination on-hand increase immediately? No, it stays in-transit until receipt.']
            ),
            'UI-INV-006' => self::definition(
                ['inventory.transfers.receive-page'], ['ar' => 'دليل استلام التحويل', 'en' => 'Transfer Receipt Guide'], ['ar' => 'تسجل شاشة الاستلام الكمية الفعلية لكل خط وتحدد إن كان هناك فرق.', 'en' => 'The receipt screen records actual quantity for every line and determines whether a difference exists.'], ['ar' => 'استخدمها عند وصول الشحنة إلى متجر الوجهة وبعد التحقق من الكميات.', 'en' => 'Use it when the shipment arrives at the destination and quantities are verified.'], ['transfers.view'], self::actions([
                    ['receive.review', ['ar' => 'مراجعة كميات كل خط', 'en' => 'Review every line quantity'], 'transfers.view'], ['receive.post', ['ar' => 'تسجيل الاستلام', 'en' => 'Record receipt'], 'transfers.receive'],
                ]), ['US-014'], ['FLW-INV-02'], ['AC-UI-08', 'AC-UI-12'], [self::step('lines', 'inventory-receipt-form', ['ar' => 'أدخل كل خط', 'en' => 'Enter every line'], ['ar' => 'لكل خط input مستقل؛ لا تستخدم كمية واحدة تمثل التحويل كله.', 'en' => 'Each line has its own input; do not use one quantity for the whole transfer.']), self::step('difference', 'inventory-receipt-form', ['ar' => 'سجل الفرق', 'en' => 'Record the difference'], ['ar' => 'إذا كانت الكمية أقل، اختر النوع وأدخل سببًا واضحًا.', 'en' => 'If quantity is lower, choose a type and enter a clear reason.']), self::step('submit', 'inventory-transfer-actions', ['ar' => 'سجل مرة واحدة', 'en' => 'Submit once'], ['ar' => 'بعد الاستلام ينتقل التحويل إلى received أو difference_review؛ لا تعيد الإرسال بعد ذلك.', 'en' => 'Receipt moves the transfer to received or difference_review; do not submit again afterward.']), self::step('verify', 'inventory-movements', ['ar' => 'طابق الحركة', 'en' => 'Reconcile the movement'], ['ar' => 'راجع transfer_receipt والرصيد وin-transit بعد نجاح العملية.', 'en' => 'Review transfer_receipt, balance, and in-transit after success.'])], [self::field('received', ['ar' => 'الكمية المستلمة', 'en' => 'Received quantity'], ['ar' => 'يجب أن تكون بين صفر والكمية المرسلة لكل خط.', 'en' => 'It must be between zero and dispatched quantity for each line.']), self::field('reason', ['ar' => 'سبب الفرق', 'en' => 'Difference reason'], ['ar' => 'مطلوب عند وجود shortage أو damage أو refusal.', 'en' => 'Required for shortage, damage, or refusal.'])], ['ar' => 'لا تسجل كمية تقديرية؛ افحص الشحنة أولًا.', 'en' => 'Do not record an estimated quantity; inspect the shipment first.'], ['ar' => 'الاستلام الجزئي لا يغلق الفرق تلقائيًا.', 'en' => 'Partial receipt does not resolve the difference automatically.'], ['ar' => 'إذا رفض backend الطلب، لا تعالج ذلك بتغيير HTML؛ راجع الحالة والكمية.', 'en' => 'If the backend rejects the request, do not alter HTML; review state and quantity.'], ['ar' => 'بعد difference_review افتح شاشة المراجعة بدل صفحة الاستلام.', 'en' => 'After difference_review, open the review screen instead of receipt.'], ['ar' => 'هل يمكن تسجيل خط واحد فقط؟ لا، يعالج backend كل الخطوط.', 'en' => 'Can only one line be recorded? No, the backend processes every line.']
            ),
            'UI-INV-007' => self::definition(
                ['inventory.transfers.differences'], ['ar' => 'دليل مراجعة فرق التحويل', 'en' => 'Transfer Difference Review Guide'], ['ar' => 'تغلق شاشة الفرق shortage أو damage أو refusal بعد مراجعة السبب والصلاحية.', 'en' => 'The difference screen closes a shortage, damage, or refusal after reason and authorization review.'], ['ar' => 'استخدمها فقط عندما تكون الحالة difference_review وdifference_status قيد المراجعة.', 'en' => 'Use it only when state is difference_review and difference_status is under review.'], ['transfers.difference'], self::actions([
                    ['difference.inspect', ['ar' => 'فحص كمية الفرق', 'en' => 'Inspect difference quantity'], 'transfers.difference'], ['difference.resolve', ['ar' => 'إغلاق مراجعة الفرق', 'en' => 'Resolve difference review'], 'transfers.difference'],
                ]), ['US-014'], ['FLW-INV-02'], ['AC-UI-08', 'AC-UI-12'], [self::step('state', 'inventory-transfers', ['ar' => 'تأكد من الحالة', 'en' => 'Confirm the state'], ['ar' => 'لا يظهر resolver إلا في حالة المراجعة الصحيحة.', 'en' => 'The resolver appears only in the correct review state.']), self::step('lines', 'inventory-difference-form', ['ar' => 'راجع كل الخطوط', 'en' => 'Review every line'], ['ar' => 'افحص الفرق لكل خط، لا تكتفِ بأول منتج.', 'en' => 'Inspect difference for every line, not only the first product.']), self::step('reason', 'inventory-difference-form', ['ar' => 'اختر النوع والسبب', 'en' => 'Choose type and reason'], ['ar' => 'استخدم shortage أو damage أو refusal مع سبب قابل للتدقيق.', 'en' => 'Use shortage, damage, or refusal with an auditable reason.']), self::step('audit', 'inventory-movements', ['ar' => 'راجع الأثر', 'en' => 'Review the audit effect'], ['ar' => 'بعد الإغلاق، تحقق من الحالة وسجل التدقيق ولا تتوقع receipt جديدًا.', 'en' => 'After resolution, verify state and audit; do not expect another receipt.'])], [self::field('type', ['ar' => 'نوع الفرق', 'en' => 'Difference type'], ['ar' => 'القيم المقبولة محددة server-side وليست مجرد خيارات HTML.', 'en' => 'Accepted values are server-side controlled, not merely HTML options.']), self::field('reason', ['ar' => 'سبب الإغلاق', 'en' => 'Resolution reason'], ['ar' => 'يجب أن يشرح السبب التشغيلي للإغلاق.', 'en' => 'It must explain the operational reason for closure.'])], ['ar' => 'الإغلاق قرار مراجعة وليس استلامًا جديدًا.', 'en' => 'Resolution is a review decision, not a new receipt.'], ['ar' => 'لا توجد صلاحية الفرق في كل الأدوار.', 'en' => 'The difference permission is not granted to every role.'], ['ar' => 'إذا لم يسمح النظام بالإغلاق، راجع الحالة والصلاحية والسبب.', 'en' => 'If resolution is denied, review state, permission, and reason.'], ['ar' => 'بعد الإغلاق، استخدم الدفتر ومراجعة التدقيق للتوثيق.', 'en' => 'After resolution, use the ledger and audit review for documentation.'], ['ar' => 'هل يضيف resolver حركة جديدة؟ لا، يغلق المراجعة ويثبت السبب.', 'en' => 'Does the resolver add a new movement? No, it closes review and records the reason.']
            ),
            'UI-INV-008' => self::definition(
                ['inventory.counts'], ['ar' => 'دليل جلسات الجرد', 'en' => 'Stock Counts Guide'], ['ar' => 'تخطط الشاشة لجرد full أو partial وتعرض حالة كل جلسة.', 'en' => 'The screen plans full or partial counts and shows each session state.'], ['ar' => 'استخدمها لمتابعة جلسة الجرد قبل إدخال الكميات أو المطابقة.', 'en' => 'Use it to track a count session before entry or reconciliation.'], ['inventory_stock_card.view'], self::actions([
                    ['counts.review', ['ar' => 'مراجعة جلسات الجرد', 'en' => 'Review count sessions'], 'inventory_stock_card.view'], ['counts.submit', ['ar' => 'إرسال جلسة الجرد', 'en' => 'Submit a count session'], 'stock_counts.submit'], ['counts.reconcile', ['ar' => 'مطابقة جلسة الجرد', 'en' => 'Reconcile a count session'], 'stock_counts.reconcile'],
                ]), ['US-016'], ['FLW-INV-06', 'FLW-INV-07'], ['AC-UI-08', 'AC-UI-12'], [self::step('scope', 'inventory-counts', ['ar' => 'راجع نطاق الجرد', 'en' => 'Review count scope'], ['ar' => 'تحقق من المتجر ونوع الجرد full أو partial قبل البدء.', 'en' => 'Confirm store and full or partial type before starting.']), self::step('state', 'inventory-counts', ['ar' => 'تابع الحالة', 'en' => 'Track the state'], ['ar' => 'المسار هو draft/in_progress ثم submitted ثم reconciled.', 'en' => 'The path is draft/in_progress, then submitted, then reconciled.']), self::step('uncounted', 'inventory-counts', ['ar' => 'احمِ غير المعدود', 'en' => 'Protect uncounted items'], ['ar' => 'المنتجات غير المعدودة لا تتحول تلقائيًا إلى صفر.', 'en' => 'Uncounted products are never automatically changed to zero.']), self::step('next', 'inventory-counts', ['ar' => 'افتح الإدخال أو المطابقة', 'en' => 'Open entry or reconciliation'], ['ar' => 'اختر الشاشة التالية حسب الحالة والصلاحية.', 'en' => 'Choose the next screen based on state and permission.'])], [self::field('count-type', ['ar' => 'نوع الجرد', 'en' => 'Count type'], ['ar' => 'full يغطي النطاق المحدد، وpartial يحفظ ما تم عده فقط.', 'en' => 'full covers the defined scope; partial preserves only what was counted.'])], ['ar' => 'لا تعتمد جلسة جرد دون مراجعة غير المعدود.', 'en' => 'Do not approve a count without reviewing uncounted items.'], ['ar' => 'الجرد المحلي ليس قبول hardware أو UAT.', 'en' => 'The local count is not hardware or UAT acceptance.'], ['ar' => 'إذا لم تظهر جلسة، راجع النطاق والصلاحية والحالة.', 'en' => 'If a session is missing, review scope, permission, and state.'], ['ar' => 'بعد الإرسال، انتقل إلى صفحة المطابقة للمراجعة النهائية.', 'en' => 'After submission, move to reconciliation for final review.'], ['ar' => 'هل partial يصفر غير المعدود؟ لا.', 'en' => 'Does partial count zero uncounted items? No.']
            ),
            'UI-INV-009' => self::definition(
                ['inventory.counts.entry'], ['ar' => 'دليل إدخال الجرد', 'en' => 'Count Entry Guide'], ['ar' => 'تسجل شاشة الإدخال الكميات المقروءة يدويًا أو بالباركود ضمن جلسة الجرد.', 'en' => 'The entry screen records manual or barcode quantities within a count session.'], ['ar' => 'استخدمها أثناء العد الفعلي، مع إبقاء reference balance غير مكشوف حسب السياسة.', 'en' => 'Use it during counting while keeping reference balance hidden according to policy.'], ['stock_counts.view'], self::actions([
                    ['count-entry.record', ['ar' => 'تسجيل كمية معدودة', 'en' => 'Record counted quantity'], 'stock_counts.view'], ['count-entry.review', ['ar' => 'مراجعة المعدود وغير المعدود', 'en' => 'Review counted and uncounted lines'], 'stock_counts.view'],
                ]), ['US-016'], ['FLW-INV-06'], ['AC-UI-08'], [self::step('context', 'inventory-counts', ['ar' => 'تحقق من الجلسة', 'en' => 'Confirm the session'], ['ar' => 'ابدأ من رقم الجرد والمتجر والنطاق قبل إدخال أي كمية.', 'en' => 'Start with count number, store, and scope before entering quantities.']), self::step('input', 'inventory-counts', ['ar' => 'أدخل الكمية', 'en' => 'Enter the quantity'], ['ar' => 'سجل الكمية الفعلية فقط، وتجنب تكرار السطر دون مراجعة.', 'en' => 'Record the physical quantity and avoid duplicate entries without review.']), self::step('status', 'inventory-counts', ['ar' => 'راجع حالة السطر', 'en' => 'Review line state'], ['ar' => 'تحقق من counted وuncounted قبل الإرسال.', 'en' => 'Check counted and uncounted state before submission.'])], [self::field('counted', ['ar' => 'الكمية المعدودة', 'en' => 'Counted quantity'], ['ar' => 'هذه نتيجة العد وليست reference balance ولا تعني اعتمادًا بعد.', 'en' => 'This is the count result, not the reference balance or final approval.'])], ['ar' => 'لا تستخدم reference balance لتخمين الكمية.', 'en' => 'Do not use reference balance to guess quantity.'], ['ar' => 'الأجهزة والماسحات خارج قبول Local Demo.', 'en' => 'Devices and scanners are outside Local Demo acceptance.'], ['ar' => 'صحح duplicate أو validation من الشاشة الحالية قبل الإرسال.', 'en' => 'Correct duplicate or validation issues in the current screen before submission.'], ['ar' => 'بعد اكتمال العد، أرسل الجلسة وافتح المطابقة.', 'en' => 'After counting is complete, submit the session and open reconciliation.'], ['ar' => 'هل إدخال الجرد يرحل حركة فورًا؟ لا، الترحيل بعد reconciliation.', 'en' => 'Does count entry post a movement immediately? No, posting happens after reconciliation.']
            ),
            'UI-INV-010' => self::definition(
                ['inventory.counts.reconcile-page'], ['ar' => 'دليل مطابقة الجرد', 'en' => 'Count Reconciliation Guide'], ['ar' => 'تقارن شاشة المطابقة snapshot والعد والحركات اللاحقة قبل إنشاء adjustment.', 'en' => 'The reconciliation screen compares snapshot, count, and subsequent movements before creating an adjustment.'], ['ar' => 'استخدمها بعد إرسال الجرد وبصلاحية المطابقة فقط.', 'en' => 'Use it after count submission and only with reconciliation permission.'], ['stock_counts.reconcile'], self::actions([
                    ['reconcile.compare', ['ar' => 'مقارنة الفروقات', 'en' => 'Compare variances'], 'stock_counts.reconcile'], ['reconcile.post', ['ar' => 'مطابقة وترحيل الفرق', 'en' => 'Reconcile and post variance'], 'stock_counts.reconcile'],
                ]), ['US-016'], ['FLW-INV-06', 'FLW-INV-07'], ['AC-UI-08', 'AC-UI-12'], [self::step('compare', 'inventory-counts', ['ar' => 'قارن النتائج', 'en' => 'Compare results'], ['ar' => 'راجع expected وcounted وvariance مع reference time.', 'en' => 'Review expected, counted, and variance with reference time.']), self::step('uncounted', 'inventory-counts', ['ar' => 'راجع غير المعدود', 'en' => 'Review uncounted'], ['ar' => 'لا تجعل عدم العد فرقًا صفريًا تلقائيًا؛ قرر وفق سياسة الجلسة.', 'en' => 'Do not turn uncounted into zero automatically; decide according to session policy.']), self::step('posting', 'inventory-counts', ['ar' => 'افهم الترحيل', 'en' => 'Understand posting'], ['ar' => 'المطابقة المعتمدة تنشئ adjustment movement وتثبت السبب.', 'en' => 'Approved reconciliation creates an adjustment movement and records the reason.']), self::step('verify', 'inventory-movements', ['ar' => 'تحقق من الدفتر', 'en' => 'Verify the ledger'], ['ar' => 'بعد النجاح، طابق حركة count_reconciliation مع الفرق.', 'en' => 'After success, match count_reconciliation movement to the variance.'])], [self::field('variance', ['ar' => 'الفرق', 'en' => 'Variance'], ['ar' => 'الفرق هو النتيجة الحسابية بين expected وcounted بعد الحركة المرجعية.', 'en' => 'Variance is calculated between expected and counted after reference-time movement.'])], ['ar' => 'المطابقة إجراء مؤثر ويحتاج صلاحية وتدقيقًا.', 'en' => 'Reconciliation is a mutating action requiring permission and audit.'], ['ar' => 'Production tolerances وowner approval خارج النطاق.', 'en' => 'Production tolerances and owner approval are out of scope.'], ['ar' => 'إذا وجدت conflict، أوقف الترحيل وراجع reference time والمصدر.', 'en' => 'If you find a conflict, stop posting and review reference time and source.'], ['ar' => 'بعد المطابقة، افتح الدفتر وراجع movement الناتجة.', 'en' => 'After reconciliation, open the ledger and review the resulting movement.'], ['ar' => 'هل يرحل كل فرق غير معدود؟ لا، غير المعدود محفوظ ولا يصفر تلقائيًا.', 'en' => 'Are all uncounted differences posted? No, uncounted lines remain preserved and are not zeroed automatically.']
            ),
            'UI-INV-011' => self::definition(
                ['inventory.adjustments'], ['ar' => 'دليل إدخالات وخروجات وتسويات المخزون', 'en' => 'Inventory Adjustments Guide'], ['ar' => 'تراجع شاشة التسويات الإدخالات والخروجات والتعديلات المسببة مع approval وaudit.', 'en' => 'The adjustments screen reviews reasoned entries, exits, and corrections with approval and audit.'], ['ar' => 'استخدمها لتصحيح فرق موثق أو تسجيل حركة محلية مصرح بها، وليس لتغيير الرصيد مباشرة.', 'en' => 'Use it for a documented correction or authorized local movement, not to edit balances directly.'], ['inventory_stock_card.view'], self::actions([
                    ['adjustments.review', ['ar' => 'مراجعة التسويات', 'en' => 'Review adjustments'], 'inventory_stock_card.view'], ['adjustments.submit', ['ar' => 'إرسال تسوية للمراجعة', 'en' => 'Submit adjustment for review'], 'inventory_stock_card.submit'], ['adjustments.approve', ['ar' => 'اعتماد وترحيل التسوية', 'en' => 'Approve and post adjustment'], 'inventory_stock_card.approve'],
                ]), ['US-015'], ['FLW-INV-03', 'FLW-INV-04', 'FLW-INV-05'], ['AC-UI-08', 'AC-UI-12'], [self::step('type', 'inventory-adjustments', ['ar' => 'حدد نوع الحركة', 'en' => 'Identify the movement type'], ['ar' => 'اقرأ إن كانت entry أو exit أو adjustment قبل مراجعة الكمية.', 'en' => 'Identify entry, exit, or adjustment before reviewing quantity.']), self::step('reason', 'inventory-adjustments', ['ar' => 'راجع السبب', 'en' => 'Review the reason'], ['ar' => 'كل تعديل يحتاج سببًا قابلًا للتدقيق ولا يسمح بالسالب افتراضيًا.', 'en' => 'Every adjustment needs an auditable reason and negative stock is blocked by default.']), self::step('state', 'inventory-adjustments', ['ar' => 'اتبع approval', 'en' => 'Follow approval'], ['ar' => 'draft ثم submitted ثم approved؛ الترحيل يحدث عند الاعتماد.', 'en' => 'The path is draft, submitted, approved; posting occurs on approval.']), self::step('verify', 'inventory-movements', ['ar' => 'طابق الحركة', 'en' => 'Reconcile the movement'], ['ar' => 'بعد الاعتماد، راجع inventory_exit أو inventory_entry في الدفتر.', 'en' => 'After approval, review inventory_exit or inventory_entry in the ledger.'])], [self::field('reason', ['ar' => 'السبب', 'en' => 'Reason'], ['ar' => 'السبب business evidence وليس ملاحظة تجميلية.', 'en' => 'The reason is business evidence, not decorative text.']), self::field('approval', ['ar' => 'الاعتماد', 'en' => 'Approval'], ['ar' => 'لا يعني submit ترحيلًا؛ الترحيل بعد approve فقط.', 'en' => 'Submit does not post; posting occurs only after approval.'])], ['ar' => 'لا تستخدم التسوية لتجاوز نقص بيانات الشراء أو الجرد.', 'en' => 'Do not use adjustments to bypass missing purchasing or count evidence.'], ['ar' => 'override وnegative stock وProduction limits خارج baseline المحلي.', 'en' => 'Override, negative stock, and Production limits remain outside the local baseline.'], ['ar' => 'إذا رفض النظام، راجع state والسبب والنطاق والصلاحية.', 'en' => 'If rejected, review state, reason, scope, and permission.'], ['ar' => 'بعد الاعتماد، راجع الدفتر وسجل التدقيق قبل متابعة العملية.', 'en' => 'After approval, review ledger and audit before continuing.'], ['ar' => 'هل submit يغير الرصيد؟ لا، approve هو الذي يرحل الحركة.', 'en' => 'Does submit change the balance? No, approval is what posts the movement.']
            ),
        ];

        if (! isset($definitions[$screenId])) {
            throw new LogicException("Unknown inventory tutorial [{$screenId}].");
        }

        $definition = $definitions[$screenId];
        $steps = $definition['steps'];
        $definition['sections'] = [
            'steps' => $steps,
            'fields' => $definition['fields'],
            'notes' => $definition['notes'],
            'warnings' => $definition['warnings'],
            'errors' => $definition['errors'],
            'next_step' => $definition['next_step'],
            'faq' => $definition['faq'],
        ];
        $definition['tour_steps'] = array_map(
            static fn (array $step): array => $step,
            array_slice($steps, 0, 4),
        );
        unset($definition['steps'], $definition['fields'], $definition['notes'], $definition['warnings'], $definition['errors'], $definition['next_step'], $definition['faq']);

        return ['screen_id' => $screenId] + $definition + ['version' => '1.0.0', 'updated_at' => '2026-08-07'];
    }

    /**
     * @param  list<array{0:string,1:array{ar:string,en:string},2:string}>  $actions
     * @return list<array{key:string,label:array{ar:string,en:string},required_permission:string}>
     */
    private static function actions(array $actions): array
    {
        return array_map(static fn (array $action): array => [
            'key' => $action[0],
            'label' => $action[1],
            'required_permission' => $action[2],
        ], $actions);
    }

    /**
     * @param  array{ar:string,en:string}  $title
     * @param  array{ar:string,en:string}  $body
     * @return array{key:string,selector:string,title:array{ar:string,en:string},body:array{ar:string,en:string}}
     */
    private static function step(string $key, string $selector, array $title, array $body): array
    {
        $selector = sprintf('[data-guide="%s"]', $selector);

        return compact('key', 'selector', 'title', 'body');
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
     * @param  array{ar:string,en:string}  $title
     * @param  array{ar:string,en:string}  $purpose
     * @param  array{ar:string,en:string}  $whenToUse
     * @param  list<string>  $permissions
     * @param  list<array{key:string,label:array{ar:string,en:string},required_permission:string}>  $approvedActions
     * @param  list<string>  $stories
     * @param  list<string>  $flows
     * @param  list<string>  $acceptanceCriteria
     * @param  list<array{key:string,selector:string,title:array{ar:string,en:string},body:array{ar:string,en:string}}>  $steps
     * @param  list<array{key:string,title:array{ar:string,en:string},body:array{ar:string,en:string}}>  $fields
     * @param  array{ar:string,en:string}  $notes
     * @param  array{ar:string,en:string}  $warnings
     * @param  array{ar:string,en:string}  $errors
     * @param  array{ar:string,en:string}  $nextStep
     * @param  array{ar:string,en:string}  $faq
     * @return array<string, mixed>
     */
    private static function definition(array $routeNames, array $title, array $purpose, array $whenToUse, array $permissions, array $approvedActions, array $stories, array $flows, array $acceptanceCriteria, array $steps, array $fields, array $notes, array $warnings, array $errors, array $nextStep, array $faq): array
    {
        return compact('routeNames', 'title', 'purpose', 'whenToUse', 'permissions', 'approvedActions', 'stories', 'flows', 'acceptanceCriteria', 'steps', 'fields', 'notes', 'warnings', 'errors', 'nextStep', 'faq') + [
            'route_names' => $routeNames,
            'when_to_use' => $whenToUse,
            'approved_actions' => $approvedActions,
            'acceptance_criteria' => $acceptanceCriteria,
            'next_step' => $nextStep,
            'purpose' => $purpose,
            'title' => $title,
        ];
    }
}
