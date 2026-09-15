<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class TutorialModuleRegistry
{
    /** @return array{key:string,title:array{ar:string,en:string},description:array{ar:string,en:string}} */
    public static function forRoute(?string $routeName): array
    {
        $routeName ??= '';

        $module = match (true) {
            $routeName === 'dashboard' => 'dashboard',
            str_starts_with($routeName, 'pos.shift') || str_starts_with($routeName, 'shifts') || str_contains($routeName, 'shift') => 'shifts',
            str_starts_with($routeName, 'pos') || str_starts_with($routeName, 'sales') || str_starts_with($routeName, 'payments') || str_starts_with($routeName, 'payment-evidence') => 'sales',
            str_starts_with($routeName, 'customers') || str_starts_with($routeName, 'wallets') => 'customers',
            str_starts_with($routeName, 'catalog') || str_starts_with($routeName, 'products') || str_starts_with($routeName, 'brands') || str_starts_with($routeName, 'categories') => 'catalog',
            str_starts_with($routeName, 'pricing') => 'pricing',
            str_starts_with($routeName, 'purchasing') => 'purchasing',
            str_starts_with($routeName, 'inventory') => 'inventory',
            str_starts_with($routeName, 'party') || str_starts_with($routeName, 'rental') => 'parties',
            str_starts_with($routeName, 'quotations') || str_contains($routeName, 'quotation') => 'quotations',
            str_starts_with($routeName, 'reports') || str_starts_with($routeName, 'alerts') || str_starts_with($routeName, 'exports') => 'reports',
            str_starts_with($routeName, 'admin') || str_starts_with($routeName, 'settings') || str_starts_with($routeName, 'users') || str_starts_with($routeName, 'roles') => 'administration',
            default => 'control',
        };

        return self::all()[$module];
    }

    /** @return array<string, array{key:string,title:array{ar:string,en:string},description:array{ar:string,en:string}}> */
    public static function all(): array
    {
        return [
            'dashboard' => [
                'key' => 'dashboard',
                'title' => ['ar' => 'لوحة التحكم', 'en' => 'Dashboard'],
                'description' => ['ar' => 'نظرة تشغيلية سريعة على العمل، مع روابط واضحة للمهام اليومية والتنبيهات المهمة.', 'en' => 'A quick operational view of the business, with clear routes to daily work and important alerts.'],
            ],
            'sales' => [
                'key' => 'sales',
                'title' => ['ar' => 'المبيعات ونقطة البيع', 'en' => 'Sales and POS'],
                'description' => ['ar' => 'ابدأ بالوردية، ابحث عن الصنف أو امسحه، راجع السلة، ثم أكمل البيع وفق صلاحياتك.', 'en' => 'Start the shift, find or scan a product, review the cart, and complete the sale within your permissions.'],
            ],
            'shifts' => [
                'key' => 'shifts',
                'title' => ['ar' => 'الورديات والخزينة', 'en' => 'Shifts and cash'],
                'description' => ['ar' => 'تدير الوردية رصيد الافتتاح والحركات النقدية والإغلاق والمراجعة مع فصل واضح للصلاحيات.', 'en' => 'Manage opening float, cash movements, close, and review with clear separation of duties.'],
            ],
            'customers' => [
                'key' => 'customers',
                'title' => ['ar' => 'العملاء والمحافظ', 'en' => 'Customers and wallets'],
                'description' => ['ar' => 'حافظ على ملف العميل وتاريخه وولائه، مع فصل محفظة المنتجات عن محفظة الحفلات.', 'en' => 'Maintain customer profiles, history, and loyalty while keeping product and party wallets separate.'],
            ],
            'catalog' => [
                'key' => 'catalog',
                'title' => ['ar' => 'دليل المنتجات', 'en' => 'Product catalog'],
                'description' => ['ar' => 'أدر المنتجات والتصنيفات والباركود والبيانات التي تعتمد عليها المبيعات والمخزون.', 'en' => 'Manage products, categories, barcodes, and the data used by sales and inventory.'],
            ],
            'pricing' => [
                'key' => 'pricing',
                'title' => ['ar' => 'التسعير', 'en' => 'Pricing'],
                'description' => ['ar' => 'راجع قوائم الأسعار وفترات سريانها واعتمادها قبل ظهورها في نقطة البيع.', 'en' => 'Review price lists, effective periods, and approvals before prices appear at POS.'],
            ],
            'purchasing' => [
                'key' => 'purchasing',
                'title' => ['ar' => 'المشتريات', 'en' => 'Purchasing'],
                'description' => ['ar' => 'تنتقل المشتريات من المورد والطلب إلى الاستلام والفاتورة وفق الاعتماد المطلوب.', 'en' => 'Purchasing moves from supplier and order to receiving and invoice review with the required approvals.'],
            ],
            'inventory' => [
                'key' => 'inventory',
                'title' => ['ar' => 'المخزون', 'en' => 'Inventory'],
                'description' => ['ar' => 'توضح شاشات المخزون الرصيد والحركات والتحويلات والجرد دون إخفاء سبب التغيير.', 'en' => 'Inventory screens make balances, movements, transfers, and counts traceable.'],
            ],
            'parties' => [
                'key' => 'parties',
                'title' => ['ar' => 'الحفلات والأصول', 'en' => 'Parties and assets'],
                'description' => ['ar' => 'تربط عمليات الحفلات بالحجز والفاتورة التشغيلية والدفعات والأصول مع إبقاء النطاق منفصلًا عن التجزئة.', 'en' => 'Connect party bookings to working invoices, payments, and assets while keeping the scope separate from retail.'],
            ],
            'quotations' => [
                'key' => 'quotations',
                'title' => ['ar' => 'عروض الأسعار', 'en' => 'Quotations'],
                'description' => ['ar' => 'توضح عروض الأسعار الشروط والمصدر والحالة قبل أي تحويل تشغيلي أو مالي.', 'en' => 'Quotations clarify source, terms, and status before any operational or financial conversion.'],
            ],
            'reports' => [
                'key' => 'reports',
                'title' => ['ar' => 'التقارير والتدقيق', 'en' => 'Reports and audit'],
                'description' => ['ar' => 'استخدم عوامل التصفية والنطاق والمصدر لفهم الأرقام والنتائج القابلة للمراجعة.', 'en' => 'Use filters, scope, and source context to understand reviewable figures and results.'],
            ],
            'administration' => [
                'key' => 'administration',
                'title' => ['ar' => 'الإدارة والإعدادات', 'en' => 'Administration and settings'],
                'description' => ['ar' => 'تضبط الإدارة الصلاحيات والإعدادات والسياسات التي تتحكم في بقية الوحدات.', 'en' => 'Administration controls permissions, settings, and policies used by the rest of the product.'],
            ],
            'control' => [
                'key' => 'control',
                'title' => ['ar' => 'التحكم والنظام', 'en' => 'Control and system'],
                'description' => ['ar' => 'تجمع هذه المساحة أدوات النظام والمساعدة وسجل الأحداث والضوابط العامة.', 'en' => 'This area brings together system tools, help, audit history, and general controls.'],
            ],
        ];
    }
}
