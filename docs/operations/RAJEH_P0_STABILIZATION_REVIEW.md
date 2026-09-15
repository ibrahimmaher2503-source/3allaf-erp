# مراجعة تثبيت P0 — راجح

**التاريخ:** 2026-08-25
**النطاق:** مراجعة ساكنة فقط؛ لم يُنفذ نشر أو migration أو seeding أو اختبار أو تغيير في التطبيق أو Production.

## تصنيف الحالة غير الملتزمة

| الملف | التصنيف | الخطر | القرار |
|---|---|---|---|
| `.ai/SESSION_SUMMARY.md` | توثيق فقط | يسجل نشرًا وإعادة بناء Production، لكنه يتضمن تفاصيل تشغيلية دقيقة ويظل خارج مصدر الحالة الحالي الفارغ. | **يُحفظ بعد المراجعة**؛ يُنقح لاحقًا لإزالة التفاصيل غير اللازمة وربطه بحالة/قرار رسمي، دون تغيير الوقائع. |
| `database/migrations/2026_08_10_000052_add_gift_card_to_sale_payments.php` | إصلاح حي مطلوب | ترتيب rollback الأصلي قد يحاول حذف العمود قبل فك المفتاح الأجنبي؛ التعديل يفك FK ثم index ثم العمود. لا يؤثر في `up()`، لكنه غير متحقق على MariaDB معزولة. | **يُحفظ ويُراجع**؛ لا يُعتمد قبل rollback/forward مركز على قاعدة disposable وبنسخة schema مطابقة. |
| `database/seeders/ProductionSeeder.php` | إصلاح حي مطلوب | يزيل هوية/كلمة مرور bootstrap ثابتة ويجعل المدخلات إلزامية ويفشل مغلقًا. الخطر المتبقي: اعتماد التشغيل على config cache ومدخلات نشر غير موثقة، ورسائل `LogicException` تشغيلية. | **يُحفظ ويُراجع**؛ يلزم فحص ساكن واختبار seeder معزول لحالات missing/mismatch/idempotency قبل أي استخدام لاحق. |
| `routes/web.php` | workaround غير آمن للاعتماد الدائم | مسار عام `build/{path}` يخدم أي ملف موجود داخل `public/build` عبر PHP، بما فيه `map/json`، ويتجاوز خدمة الويب المعتادة ويزيد الحمل وسطح الكشف. حارس `realpath` يمنع traversal المعروف، لكنه لا يعالج أصل مشكلة document root. | **يُراجع ثم يُزال** بعد إصلاح إعداد خادم الويب؛ إن تعذر ذلك مؤقتًا، يُقيد بقائمة ملفات assets المطلوبة، يمنع source maps/JSON، ويوثق كاستثناء نشر مؤقت. |
| `error_log` | أثر قابل للإزالة | ملف غير متتبع صغير بصلاحيات أوسع من اللازم؛ يحتوي Fatal واحدًا مرتبطًا بتحميل bootstrap بتاريخ لاحق للنشر. لا يظهر من الفحص النمطي وجود أسرار، لكن لا يجوز نشره أو تتبعه. | **يُحفظ خارج web root فقط إن لزم كدليل حادث، ثم يُزال** من المستودع ويُمنع توليد السجلات داخل جذر التطبيق. |
| `.ea-php-cli.cache` | أثر قابل للإزالة | symlink غير متتبع خاص ببيئة الاستضافة، وقد يربط سلوك CLI بإصدار PHP محلي غير موثق. | **يُزال من جذر المستودع** بعد تأكيد مالك الاستضافة، أو يُدار خارج Git عبر إعداد المنصة؛ لا يُلتزم به. |

لا توجد تغييرات staged. يجب عدم خلط أي بند أعلاه مع دفعة UI.

## تسريب معلومات الاستثناءات

لا يضيف `bootstrap/app.php` renderer مخصصًا؛ لذلك الخطر ليس صفحة Laravel العامة فقط، بل مسارات تمسك الاستثناء ثم تعرض `getMessage()` مباشرة للمستخدم.

**خطر حرج — التقاط `Throwable` وعرض الرسالة الخام:**

- `routes/returns-gifts.php` في إنشاء/إرسال/اعتماد/إكمال المرتجع، وإصدار إيصال/بطاقة الهدية وإبطالها.
- `resources/views/purchasing/{orders,invoices,returns,invoice-import}.blade.php`.
- `resources/views/catalog/{brands,categories,products,product-form,product-import,suppliers}.blade.php`، مع مواضع إضافية في `product-options.blade.php` و`product-variations.blade.php` تعرض رسائل استثناء مباشرة.
- `resources/views/platform/admin/{branches,stores,drawers,roles,role-permissions}.blade.php`.

هذه المسارات قادرة على كشف SQL وأسماء الجداول/الأعمدة ومسارات الملفات ورسائل أخطاء الحزم، وقد سبق أن سجل المشروع ظهور `SQLSTATE` في واجهة مورد.

**خطر مرتفع — رسائل استثناء مباشرة ولو كانت الأنواع أضيق:**

- `routes/customers.php`: يعرض `InvalidArgumentException` و`ValidationException`، ويعرض أيضًا `UniqueConstraintViolationException` الخام في المجموعات/العميل.
- `routes/retail.php`: POS، الوردية، Offline، السلة، الدفع والاستئناف؛ بعض المسارات تشمل `RuntimeException` و`LogicException`.
- `routes/party.php` و`routes/inventory.php`.
- `app/Livewire/Pos/{Cart,ProductBrowser}.php` و`app/Modules/Retail/Support/PosCartSnapshot.php`.
- مسارات validation غير المباشرة في `PhoneNormalizer.php` و`StageCustomerImportAction.php` و`SavePosFinancialSettingAction.php`.

الرسائل المترجمة المقصودة داخل domain exceptions قد تكون صالحة للمستخدم، لكن النوع وحده لا يضمن السلامة. المطلوب عقد أخطاء صريح: رسائل عامة للمستخدم، `report()` مع request ID داخلي، وتحويل أخطاء domain المعتمدة فقط إلى validation messages. يمنع عرض `Throwable` أو أخطاء قاعدة البيانات/الملفات/الحزم خامًا.

## التسلسل الأدنى الآمن للمعالجة

1. تجميد النشر وعمليات البيانات والخدمات، وإبقاء التغييرات الستة منفصلة وغير ملتزمة.
2. حفظ دليل الحادث خارج web root بصلاحيات مقيدة عند الحاجة، ثم إزالة `error_log` وsymlink البيئي من جذر المشروع بعد موافقة مالك الاستضافة.
3. معالجة تسريب الاستثناءات أولًا: `returns-gifts` ثم POS/الدفع والمخزون والمحافظ، ثم المشتريات والكتالوج والإدارة؛ تسجيل الاستثناء داخليًا وإظهار رسالة آمنة مع request ID.
4. إصلاح document root/static asset serving في طبقة خادم الويب، ثم إزالة route الـassets. لا يُقبل workaround دائم داخل Laravel.
5. التحقق على MariaDB disposable مسماة فقط من rollback/forward الخاص ببطاقة الهدية ومن حالات Production Seeder؛ لا SQLite ولا Production.
6. بعد تفويض الاختبارات صراحة: فحوص مركزة لتسريب SQL، authorization/scope، المعاملات وidempotency، ثم browser smoke عربي/إنجليزي.
7. تحديث ملفات الحالة والقرار ونتائج الاختبار بأدلة فعلية قبل أي commit أو نشر مستقل.

## خط أساس آمن لأعمال UI الإنتاجية

- نقطة البداية هي `origin/master@610058c` مع استثناءات P0 أعلاه موثقة؛ لا تُبنى أعمال UI فوق route الـassets أو ملفات البيئة غير المتتبعة.
- أعمال UI التالية تكون عرضية فقط: ترجمة واتساق RTL/LTR، responsive، loading/empty/error/permission/validation/confirmation/unsaved states، دون تغيير schema أو posting أو authorization أو readiness rules.
- كل دفعة صغيرة ومحددة بملفات، وتفصل عن migrations/seeders/deployment؛ لا تعرض رسائل استثناء خامًا.
- التحقق الأولي ساكن، ثم browser يدوي مصادق على بيئة غير إنتاجية عند التفويض. الاختبارات الآلية لا تُنشأ أو تُشغّل قبل إذن صريح وقاعدة MariaDB disposable مسجلة الاسم.
- لا تُعد شاشة readiness دليلًا على اكتمال الوحدة، ولا تُدّعى جاهزية Production أو UAT من HTTP 200 أو نجاح build فقط.
