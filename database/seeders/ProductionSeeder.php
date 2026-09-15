<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Support\CompanyOwnerPermissionProfile;
use App\Modules\Pricing\Actions\EnsureBasePriceList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

final class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedAuthorization();
            $this->seedBootstrapAdministrator();
            $this->seedOperationalBaseline();
        });

        if (config('production-seeding.setup_data.enabled') === true) {
            $this->call(ProductionSetupSeeder::class);
        }
    }

    /**
     * Installs only the canonical roles, permissions, and role grants.
     *
     * Compatibility seeders and isolated fixtures must not receive the
     * bootstrap administrator or operational baseline as a side effect.
     */
    public function seedAuthorizationOnly(): void
    {
        DB::transaction(function (): void {
            $this->seedAuthorization();
        });
    }

    /** Install the reusable authorization baseline with its bootstrap owner. */
    public function seedCanonicalAuthorizationAndBootstrapAdministrator(): void
    {
        DB::transaction(function (): void {
            $this->seedAuthorization();
            $this->seedBootstrapAdministrator();
        });
    }

    private function seedAuthorization(): void
    {
        $roles = [
            'system-administrator' => ['مسؤول النظام', 'System Administrator', null, null],
            'branch-manager' => ['مدير الفرع', 'Branch Manager', null, null],
            'cashier' => ['أمين الصندوق', 'Cashier', 'تشغيل نقطة البيع والتحصيل والوردية المعيّنة فقط ضمن الفرع ومنفذ البيع وخزينة الكاشير المصرح بها.', 'Operates POS sales, collections, and assigned shifts only within authorized branch, Sales Outlet, and Cashier Till scopes.'],
            'purchasing-officer' => ['مسؤول المشتريات', 'Purchasing Officer', null, null],
            'warehouse-manager' => ['مدير المخزن', 'Warehouse Manager', null, null],
            'pricing-officer' => ['مسؤول التسعير', 'Pricing Officer', null, null],
            'party-manager' => ['مدير الحفلات', 'Party Manager', null, null],
            'stock-counter' => ['مراقب المخزون', 'Stock Counter', null, null],
            'accountant-reviewer' => ['المحاسب / المراجع', 'Accountant / Reviewer', null, null],
        ];

        foreach ($roles as $code => [$nameAr, $nameEn, $descriptionAr, $descriptionEn]) {
            Role::query()->updateOrCreate(['code' => $code], ['name_ar' => $nameAr, 'name_en' => $nameEn, 'description_ar' => $descriptionAr, 'description_en' => $descriptionEn, 'status' => 'active']);
        }

        $permissions = [
            'manage-settings' => ['company_settings', 'manage', 'sensitive'],
            'manage-branches-stores' => ['branches_stores', 'manage', 'sensitive'],
            'view-authorization-baseline' => ['authorization', 'view', 'sensitive'],
            'manage-authorization' => ['authorization', 'manage', 'sensitive'],
            'view-platform-status' => ['platform', 'view', 'normal'],
            'view-ui-showcase' => ['platform', 'view_patterns', 'normal'],
        ];

        foreach ($permissions as $code => [$module, $action, $sensitivity]) {
            Permission::query()->updateOrCreate(['code' => $code], compact('module', 'action', 'sensitivity') + ['status' => 'active']);
        }

        $modules = [
            'company_settings', 'branches_stores', 'drawers_payments_tax_numbering_printers', 'users_roles_permissions', 'products_categories_brands', 'suppliers', 'purchase_orders', 'purchase_invoices_supplier_returns', 'purchase_returns', 'pricing_labels', 'inventory_stock_card', 'transfers', 'stock_counts', 'pos_sales', 'suspended_sales', 'shifts_cash_movements', 'customers_children', 'loyalty', 'product_wallet', 'party_wallet', 'returns_exchanges_gift_instruments', 'party_bookings_invoices', 'party_operating_orders_consumables', 'rental_assets', 'quotations', 'dashboard_reports', 'audit_logs', 'offline_queue_conflicts',
        ];
        foreach ($modules as $module) {
            foreach (['view', 'create', 'edit', 'submit', 'logical_delete', 'print', 'approve', 'reject', 'export', 'reverse', 'cancel', 'override'] as $action) {
                Permission::query()->updateOrCreate(['code' => $module.'.'.$action], ['module' => $module, 'action' => $action, 'sensitivity' => in_array($action, ['approve', 'submit', 'reject', 'export', 'reverse', 'cancel', 'override', 'logical_delete'], true) ? 'sensitive' : 'normal', 'status' => 'active']);
            }
        }

        Permission::query()->updateOrCreate(['code' => 'purchase_returns.approve_over_limit'], ['module' => 'purchase_returns', 'action' => 'approve_over_limit', 'sensitivity' => 'sensitive', 'status' => 'active']);
        foreach (['draft', 'distribute', 'approve', 'reverse', 'view_cost', 'change_cost', 'change_list0_price', 'print_labels'] as $action) {
            Permission::query()->updateOrCreate(['code' => 'purchase_invoices.'.$action], ['module' => 'purchase_invoices', 'action' => $action, 'sensitivity' => in_array($action, ['approve', 'reverse', 'change_cost', 'change_list0_price'], true) ? 'sensitive' : 'normal', 'status' => 'active']);
        }
        foreach ([
            ['code' => 'suppliers.preferred_change', 'module' => 'suppliers', 'action' => 'preferred_change'],
            ['code' => 'inventory_stock_card.cost_view', 'module' => 'inventory_stock_card', 'action' => 'cost_view'],
            ['code' => 'transfers.dispatch', 'module' => 'transfers', 'action' => 'dispatch'],
            ['code' => 'transfers.receive', 'module' => 'transfers', 'action' => 'receive'],
            ['code' => 'transfers.difference', 'module' => 'transfers', 'action' => 'difference'],
            ['code' => 'stock_counts.reconcile', 'module' => 'stock_counts', 'action' => 'reconcile'],
            ['code' => 'drawers_payments_tax_numbering_printers.override', 'module' => 'drawers_payments_tax_numbering_printers', 'action' => 'override'],
            ['code' => 'pos_sales.apply_tax', 'module' => 'pos_sales', 'action' => 'apply_tax'],
            ['code' => 'pos_sales.apply_discount', 'module' => 'pos_sales', 'action' => 'apply_discount'],
            ['code' => 'pos_sales.discount_approve', 'module' => 'pos_sales', 'action' => 'discount_approve'],
            ['code' => 'pos_sales.open_price', 'module' => 'pos_sales', 'action' => 'open_price'],
            ['code' => 'pos_sales.open_price_approve', 'module' => 'pos_sales', 'action' => 'open_price_approve'],
            ['code' => 'pos_sales.payment_view', 'module' => 'pos_sales', 'action' => 'payment_view'],
            ['code' => 'pos_sales.payment_create', 'module' => 'pos_sales', 'action' => 'payment_create'],
            ['code' => 'pos_sales.payment_evidence_upload', 'module' => 'pos_sales', 'action' => 'payment_evidence_upload'],
            ['code' => 'pos_sales.payment_evidence_view', 'module' => 'pos_sales', 'action' => 'payment_evidence_view'],
            ['code' => 'product_wallet.settle', 'module' => 'product_wallet', 'action' => 'settle'],
            ['code' => 'product_wallet.adjust', 'module' => 'product_wallet', 'action' => 'adjust'],
            ['code' => 'party_wallet.settle', 'module' => 'party_wallet', 'action' => 'settle'],
            ['code' => 'party_wallet.adjust', 'module' => 'party_wallet', 'action' => 'adjust'],
        ] as $permission) {
            Permission::query()->updateOrCreate(['code' => $permission['code']], $permission + ['sensitivity' => 'sensitive', 'status' => 'active']);
        }

        // US-019..US-021: the legacy broad readiness permission remains a
        // view boundary, while mutations use explicit server-side abilities.
        foreach ([
            ['code' => 'gift_receipts.view', 'module' => 'gift_receipts', 'action' => 'view', 'sensitivity' => 'normal'],
            ['code' => 'gift_receipts.issue', 'module' => 'gift_receipts', 'action' => 'issue', 'sensitivity' => 'sensitive'],
            ['code' => 'gift_receipts.print', 'module' => 'gift_receipts', 'action' => 'print', 'sensitivity' => 'normal'],
            ['code' => 'gift_receipts.reprint', 'module' => 'gift_receipts', 'action' => 'reprint', 'sensitivity' => 'sensitive'],
            ['code' => 'gift_receipts.validate', 'module' => 'gift_receipts', 'action' => 'validate', 'sensitivity' => 'normal'],
            ['code' => 'returns.view', 'module' => 'returns', 'action' => 'view', 'sensitivity' => 'normal'],
            ['code' => 'returns.create', 'module' => 'returns', 'action' => 'create', 'sensitivity' => 'sensitive'],
            ['code' => 'returns.submit', 'module' => 'returns', 'action' => 'submit', 'sensitivity' => 'sensitive'],
            ['code' => 'returns.approve', 'module' => 'returns', 'action' => 'approve', 'sensitivity' => 'sensitive'],
            ['code' => 'returns.complete', 'module' => 'returns', 'action' => 'complete', 'sensitivity' => 'sensitive'],
            ['code' => 'returns.print', 'module' => 'returns', 'action' => 'print', 'sensitivity' => 'normal'],
            ['code' => 'gift_cards.view', 'module' => 'gift_cards', 'action' => 'view', 'sensitivity' => 'normal'],
            ['code' => 'gift_cards.print', 'module' => 'gift_cards', 'action' => 'print', 'sensitivity' => 'normal'],
            ['code' => 'gift_cards.issue', 'module' => 'gift_cards', 'action' => 'issue', 'sensitivity' => 'sensitive'],
            ['code' => 'gift_cards.redeem', 'module' => 'gift_cards', 'action' => 'redeem', 'sensitivity' => 'sensitive'],
            ['code' => 'gift_cards.void', 'module' => 'gift_cards', 'action' => 'void', 'sensitivity' => 'sensitive'],
            ['code' => 'gift_cards.expire', 'module' => 'gift_cards', 'action' => 'expire', 'sensitivity' => 'sensitive'],
        ] as $permission) {
            Permission::query()->updateOrCreate(['code' => $permission['code']], $permission + ['status' => 'active']);
        }

        foreach (['rental_assets.reserve', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect', 'rental_assets.status', 'rental_assets.cost_view', 'rental_assets.cost_edit', 'quotations.issue', 'quotations.share', 'dashboard_reports.edit', 'dashboard_reports.export_xlsx', 'dashboard_reports.export_pdf'] as $code) {
            [$module, $action] = explode('.', $code, 2);
            Permission::query()->updateOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'sensitivity' => in_array($action, ['cost_view', 'cost_edit', 'edit'], true) ? 'sensitive' : 'normal', 'status' => 'active']);
        }

        foreach ([
            ['code' => 'customers.view', 'module' => 'customers_children', 'action' => 'customer_view', 'sensitivity' => 'normal'],
            ['code' => 'customers.create', 'module' => 'customers_children', 'action' => 'customer_create', 'sensitivity' => 'normal'],
            ['code' => 'customers.edit', 'module' => 'customers_children', 'action' => 'customer_edit', 'sensitivity' => 'normal'],
            ['code' => 'customers.sensitive', 'module' => 'customers_children', 'action' => 'customer_sensitive', 'sensitivity' => 'sensitive'],
            ['code' => 'customers.merge', 'module' => 'customers_children', 'action' => 'customer_merge', 'sensitivity' => 'sensitive'],
            ['code' => 'customers.export', 'module' => 'customers_children', 'action' => 'customer_export', 'sensitivity' => 'sensitive'],
            ['code' => 'loyalty.earn', 'module' => 'loyalty', 'action' => 'earn', 'sensitivity' => 'sensitive'],
            ['code' => 'loyalty.redeem', 'module' => 'loyalty', 'action' => 'redeem', 'sensitivity' => 'sensitive'],
            ['code' => 'loyalty.adjust', 'module' => 'loyalty', 'action' => 'adjust', 'sensitivity' => 'sensitive'],
            ['code' => 'loyalty.approve', 'module' => 'loyalty', 'action' => 'approve', 'sensitivity' => 'sensitive'],
            ['code' => 'loyalty.export', 'module' => 'loyalty', 'action' => 'export', 'sensitivity' => 'sensitive'],
            ['code' => 'loyalty.expire', 'module' => 'loyalty', 'action' => 'expire', 'sensitivity' => 'sensitive'],
        ] as $permission) {
            Permission::query()->updateOrCreate(['code' => $permission['code']], $permission + ['status' => 'active']);
        }

        $rolePermissions = [
            'system-administrator' => [
                'company_settings.view', 'company_settings.create', 'company_settings.edit', 'company_settings.approve', 'company_settings.logical_delete',
                'branches_stores.view', 'branches_stores.create', 'branches_stores.edit', 'branches_stores.logical_delete',
                'drawers_payments_tax_numbering_printers.view', 'drawers_payments_tax_numbering_printers.create', 'drawers_payments_tax_numbering_printers.edit',
                'drawers_payments_tax_numbering_printers.override', 'drawers_payments_tax_numbering_printers.logical_delete',
                'users_roles_permissions.view', 'users_roles_permissions.create', 'users_roles_permissions.edit',
                'dashboard_reports.view', 'audit_logs.view', 'audit_logs.export', 'product_wallet.view', 'party_wallet.view', 'party_wallet.settle', 'party_wallet.adjust', 'party_wallet.approve', 'party_wallet.export', 'party_bookings_invoices.view', 'party_bookings_invoices.create', 'party_bookings_invoices.edit', 'party_bookings_invoices.print', 'party_bookings_invoices.approve', 'party_bookings_invoices.reject', 'party_bookings_invoices.export', 'party_bookings_invoices.reverse', 'party_bookings_invoices.cancel', 'party_bookings_invoices.override', 'party_operating_orders_consumables.view', 'party_operating_orders_consumables.create', 'party_operating_orders_consumables.edit', 'party_operating_orders_consumables.print', 'party_operating_orders_consumables.approve', 'party_operating_orders_consumables.reject', 'party_operating_orders_consumables.export', 'party_operating_orders_consumables.reverse', 'party_operating_orders_consumables.cancel', 'party_operating_orders_consumables.override', 'returns_exchanges_gift_instruments.view', 'products_categories_brands.view',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'suppliers.preferred_change',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.cancel', 'purchase_orders.print', 'purchase_orders.approve',
                'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.create', 'purchase_invoices_supplier_returns.edit', 'purchase_invoices_supplier_returns.print', 'purchase_invoices_supplier_returns.approve', 'purchase_invoices_supplier_returns.export', 'purchase_invoices_supplier_returns.reverse', 'purchase_invoices_supplier_returns.cancel', 'purchase_invoices_supplier_returns.override',
                'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.edit', 'purchase_returns.print', 'purchase_returns.approve', 'purchase_returns.approve_over_limit', 'purchase_returns.reject', 'purchase_returns.reverse', 'purchase_returns.cancel', 'pricing_labels.view', 'pricing_labels.create', 'pricing_labels.edit', 'pricing_labels.submit', 'pricing_labels.reject', 'pricing_labels.override', 'pricing_labels.print', 'pricing_labels.export', 'inventory_stock_card.view', 'inventory_stock_card.cost_view', 'transfers.view', 'transfers.approve', 'transfers.dispatch', 'transfers.receive', 'transfers.difference', 'stock_counts.view', 'stock_counts.reconcile',
                'pos_sales.view', 'pos_sales.print', 'pos_sales.apply_tax', 'pos_sales.apply_discount', 'pos_sales.discount_approve', 'pos_sales.open_price', 'pos_sales.open_price_approve', 'pos_sales.payment_view', 'pos_sales.payment_create', 'pos_sales.payment_evidence_upload', 'pos_sales.payment_evidence_view',
                'customers.view', 'customers.create', 'customers.edit', 'customers.sensitive', 'customers.merge', 'customers.export', 'loyalty.view', 'loyalty.earn', 'loyalty.redeem', 'loyalty.adjust', 'loyalty.approve', 'loyalty.export', 'loyalty.expire', 'rental_assets.view', 'rental_assets.create', 'rental_assets.edit', 'rental_assets.print', 'rental_assets.approve', 'rental_assets.reject', 'rental_assets.export', 'rental_assets.reserve', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect', 'rental_assets.status', 'rental_assets.cost_view', 'rental_assets.cost_edit', 'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.print', 'quotations.approve', 'quotations.export', 'quotations.cancel', 'quotations.issue', 'quotations.share', 'dashboard_reports.export', 'dashboard_reports.edit',
            ],
            'branch-manager' => ['branches_stores.view', 'pos_sales.view', 'pos_sales.print', 'pos_sales.discount_approve', 'pos_sales.open_price', 'pos_sales.open_price_approve', 'pos_sales.payment_view', 'pos_sales.payment_evidence_view', 'purchase_orders.view', 'purchase_invoices_supplier_returns.view', 'purchase_returns.view', 'purchase_returns.print', 'purchase_returns.approve', 'purchase_returns.reverse', 'inventory_stock_card.view', 'shifts_cash_movements.view', 'shifts_cash_movements.approve', 'shifts_cash_movements.reject', 'shifts_cash_movements.print', 'customers.view', 'customers.sensitive', 'customers.merge', 'customers.export', 'loyalty.view', 'loyalty.earn', 'loyalty.redeem', 'loyalty.adjust', 'loyalty.approve', 'loyalty.export', 'loyalty.expire', 'rental_assets.view', 'rental_assets.create', 'rental_assets.print', 'rental_assets.reserve', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect', 'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.print', 'quotations.issue', 'quotations.share', 'dashboard_reports.view'],
            'cashier' => ['pos_sales.view', 'pos_sales.create', 'pos_sales.print', 'pos_sales.apply_tax', 'pos_sales.apply_discount', 'pos_sales.payment_view', 'pos_sales.payment_create', 'pos_sales.payment_evidence_upload', 'pos_sales.payment_evidence_view', 'suspended_sales.view', 'products_categories_brands.view', 'inventory_stock_card.view', 'shifts_cash_movements.view', 'shifts_cash_movements.create', 'shifts_cash_movements.edit', 'shifts_cash_movements.submit', 'shifts_cash_movements.print', 'customers.view', 'customers.create', 'customers.edit', 'loyalty.view', 'loyalty.earn', 'loyalty.redeem', 'product_wallet.view', 'product_wallet.settle'],
            'purchasing-officer' => ['products_categories_brands.view', 'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.preferred_change', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.cancel', 'purchase_orders.print', 'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.create', 'purchase_invoices_supplier_returns.edit', 'purchase_invoices_supplier_returns.print', 'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.edit', 'purchase_returns.print'],
            'warehouse-manager' => ['products_categories_brands.view', 'suppliers.view', 'purchase_orders.view', 'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.approve', 'purchase_returns.view', 'purchase_returns.approve', 'inventory_stock_card.view', 'inventory_stock_card.cost_view', 'inventory_stock_card.create', 'inventory_stock_card.edit', 'inventory_stock_card.submit', 'inventory_stock_card.approve', 'inventory_stock_card.override', 'inventory_stock_card.reverse', 'transfers.view', 'transfers.create', 'transfers.edit', 'transfers.submit', 'transfers.approve', 'transfers.dispatch', 'transfers.receive', 'transfers.difference', 'stock_counts.view', 'stock_counts.create', 'stock_counts.edit', 'stock_counts.reconcile', 'party_operating_orders_consumables.view', 'party_operating_orders_consumables.create', 'party_operating_orders_consumables.edit', 'party_operating_orders_consumables.print', 'party_operating_orders_consumables.approve'],
            'pricing-officer' => ['products_categories_brands.view', 'pricing_labels.view', 'pricing_labels.create', 'pricing_labels.edit', 'pricing_labels.submit', 'pricing_labels.approve'],
            'stock-counter' => ['products_categories_brands.view', 'inventory_stock_card.view', 'stock_counts.view', 'stock_counts.create', 'stock_counts.edit', 'stock_counts.submit'],
            // Party-side customer/loyalty rules are downstream. Do not grant
            // a Party Manager access to the TSK-027 retail loyalty ledger.
            'party-manager' => ['customers.view', 'customers.create', 'customers.edit', 'customers.sensitive', 'party_bookings_invoices.view', 'party_bookings_invoices.create', 'party_bookings_invoices.edit', 'party_bookings_invoices.print', 'party_bookings_invoices.approve', 'party_bookings_invoices.cancel', 'party_operating_orders_consumables.view', 'party_operating_orders_consumables.create', 'party_operating_orders_consumables.edit', 'party_operating_orders_consumables.print', 'party_operating_orders_consumables.approve', 'party_operating_orders_consumables.cancel', 'party_wallet.view', 'party_wallet.settle', 'party_wallet.adjust', 'rental_assets.view', 'rental_assets.create', 'rental_assets.print', 'rental_assets.reserve', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect', 'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.print', 'quotations.issue', 'quotations.share'],
            'accountant-reviewer' => ['shifts_cash_movements.view', 'shifts_cash_movements.export', 'dashboard_reports.view', 'dashboard_reports.export', 'dashboard_reports.edit', 'audit_logs.view', 'pos_sales.view', 'pos_sales.payment_view', 'pos_sales.payment_evidence_view', 'product_wallet.view', 'product_wallet.export', 'product_wallet.approve', 'party_wallet.view', 'party_wallet.export', 'party_wallet.approve', 'party_bookings_invoices.view', 'party_bookings_invoices.print', 'party_bookings_invoices.approve', 'party_bookings_invoices.export', 'party_operating_orders_consumables.view', 'party_operating_orders_consumables.print', 'party_operating_orders_consumables.approve', 'party_operating_orders_consumables.export', 'returns_exchanges_gift_instruments.view', 'products_categories_brands.view', 'pricing_labels.view', 'pricing_labels.reject', 'pricing_labels.override', 'pricing_labels.print', 'pricing_labels.export', 'inventory_stock_card.view', 'inventory_stock_card.cost_view', 'inventory_stock_card.export', 'suppliers.view', 'purchase_orders.view', 'purchase_orders.print', 'purchase_orders.approve', 'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.print', 'purchase_invoices_supplier_returns.approve', 'purchase_invoices_supplier_returns.export', 'purchase_returns.view', 'purchase_returns.print', 'purchase_returns.approve', 'purchase_returns.reverse', 'customers.view', 'customers.sensitive', 'customers.merge', 'customers.export', 'loyalty.view', 'loyalty.adjust', 'loyalty.approve', 'loyalty.export', 'loyalty.expire', 'rental_assets.view', 'rental_assets.print', 'rental_assets.export', 'rental_assets.cost_view', 'quotations.view', 'quotations.print', 'quotations.export'],
        ];

        $rolePermissions['system-administrator'] = [...$rolePermissions['system-administrator'], 'dashboard_reports.export_xlsx', 'dashboard_reports.export_pdf'];
        $rolePermissions['accountant-reviewer'] = [...$rolePermissions['accountant-reviewer'], 'dashboard_reports.export_xlsx', 'dashboard_reports.export_pdf'];
        $rolePermissions['system-administrator'] = [...$rolePermissions['system-administrator'], 'purchase_invoices.draft', 'purchase_invoices.distribute', 'purchase_invoices.approve', 'purchase_invoices.reverse', 'purchase_invoices.view_cost', 'purchase_invoices.change_cost', 'purchase_invoices.change_list0_price', 'purchase_invoices.print_labels'];
        $rolePermissions['purchasing-officer'] = [...$rolePermissions['purchasing-officer'], 'purchase_invoices.draft', 'purchase_invoices.distribute', 'purchase_invoices.view_cost', 'purchase_invoices.change_cost', 'purchase_invoices.change_list0_price', 'purchase_invoices.print_labels'];
        $rolePermissions['warehouse-manager'] = [...$rolePermissions['warehouse-manager'], 'purchase_invoices.distribute', 'purchase_invoices.approve', 'purchase_invoices.view_cost', 'purchase_invoices.print_labels'];
        $rolePermissions['accountant-reviewer'] = [...$rolePermissions['accountant-reviewer'], 'purchase_invoices.approve', 'purchase_invoices.reverse', 'purchase_invoices.view_cost'];

        $instrumentViewer = ['gift_receipts.view', 'gift_receipts.print', 'gift_receipts.reprint', 'gift_receipts.validate', 'returns.view', 'returns.print', 'gift_cards.view', 'gift_cards.print'];
        $cashierInstrumentPermissions = [...$instrumentViewer, 'gift_receipts.issue', 'returns.create', 'returns.submit', 'returns.complete', 'gift_cards.issue', 'gift_cards.redeem'];
        $approverInstrumentPermissions = [...$instrumentViewer, 'gift_receipts.issue', 'returns.create', 'returns.submit', 'returns.approve', 'returns.complete', 'gift_cards.issue', 'gift_cards.redeem', 'gift_cards.void', 'gift_cards.expire'];
        $rolePermissions['system-administrator'] = array_values(array_unique([...$rolePermissions['system-administrator'], ...$approverInstrumentPermissions]));
        $rolePermissions['branch-manager'] = array_values(array_unique([...$rolePermissions['branch-manager'], ...$approverInstrumentPermissions]));
        $rolePermissions['cashier'] = array_values(array_unique([...$rolePermissions['cashier'], ...$cashierInstrumentPermissions]));
        $rolePermissions['accountant-reviewer'] = array_values(array_unique([...$rolePermissions['accountant-reviewer'], ...$instrumentViewer]));

        $effectiveRolePermissions = self::productionSafeRolePermissions();

        foreach ($roles as $code => $_) {
            $role = Role::query()->where('code', $code)->firstOrFail();
            $codes = $code === 'system-administrator'
                ? Permission::query()->where('status', 'active')->pluck('code')->all()
                : ($effectiveRolePermissions[$code] ?? []);
            $role->permissions()->sync(Permission::query()->whereIn('code', $codes)->pluck('id')->all());
        }

        app(CompanyOwnerPermissionProfile::class)->synchronize();
    }

    private function seedBootstrapAdministrator(): void
    {
        $bootstrapAdmin = $this->bootstrapAdministratorConfig();

        $administrator = User::query()
            ->where('username', $bootstrapAdmin['username'])
            ->orWhere('email', $bootstrapAdmin['email'])
            ->first();

        if ($administrator === null) {
            $administrator = new User([
                'name' => $bootstrapAdmin['name'],
                'username' => $bootstrapAdmin['username'],
                'email' => $bootstrapAdmin['email'],
                'password' => Hash::make($bootstrapAdmin['password']),
                'status' => 'active',
            ]);
            $administrator->forceFill([
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ])->save();
        } elseif ($administrator->username !== $bootstrapAdmin['username'] || $administrator->email !== $bootstrapAdmin['email']) {
            throw new LogicException('The baseline administrator username or email belongs to another user. Seeding was rolled back.');
        }

        $administrator->roles()->syncWithoutDetaching([
            Role::query()->where('code', 'system-administrator')->firstOrFail()->id,
        ]);
    }

    /** @return array{name: string, username: string, email: string, password: string} */
    private function bootstrapAdministratorConfig(): array
    {
        $bootstrapAdmin = config('production-seeding.admin');

        foreach (['name', 'username', 'email', 'password'] as $field) {
            if (! is_string($bootstrapAdmin[$field] ?? null) || trim($bootstrapAdmin[$field]) === '') {
                throw new LogicException("Missing required Production administrator configuration: {$field}.");
            }
        }

        /** @var array{name: string, username: string, email: string, password: string} $bootstrapAdmin */
        return $bootstrapAdmin;
    }

    private function seedOperationalBaseline(): void
    {
        $company = Company::query()->firstOrCreate(['code' => 'TOY-JOY'], [
            'name_ar' => 'توي آند جوي', 'name_en' => 'Toy & Joy', 'legal_name' => 'Toy & Joy',
            'currency_code' => 'EGP', 'currency_symbol' => 'E£', 'timezone' => 'Africa/Cairo',
            'locale_default' => 'ar', 'email' => 'admin@instaparty.online', 'status' => 'active',
        ]);
        app(EnsureBasePriceList::class)->execute($company);
        $branch = Branch::query()->firstOrCreate(['code' => 'MAIN'], [
            'company_id' => $company->id, 'name_ar' => 'الفرع الرئيسي', 'name_en' => 'Main Branch',
            'timezone' => 'Africa/Cairo', 'status' => 'active',
        ]);
        $sellingStore = Store::query()->firstOrCreate(['code' => 'MAIN-SALES'], [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'selling',
            'name_ar' => 'متجر المبيعات الرئيسي', 'name_en' => 'Main Sales Store', 'status' => 'active',
            'allows_negative_stock' => false,
        ]);
        Store::query()->firstOrCreate(['code' => 'MAIN-WAREHOUSE'], [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse',
            'name_ar' => 'المخزن الرئيسي', 'name_en' => 'Main Warehouse', 'status' => 'active',
            'allows_negative_stock' => false,
        ]);
        BranchSellingStore::query()->firstOrCreate(['branch_id' => $branch->id, 'store_id' => $sellingStore->id], [
            'status' => 'active', 'effective_from' => now(),
        ]);
        CashDrawer::query()->firstOrCreate(['branch_id' => $sellingStore->branch_id, 'code' => 'MAIN-01'], [
            'company_id' => $sellingStore->company_id, 'store_id' => $sellingStore->id,
            'name_ar' => 'درج النقدية الرئيسي', 'name_en' => 'Main Cash Drawer', 'status' => 'active',
        ]);

        foreach ([
            ['code' => 'CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'offline_eligible' => true],
            ['code' => 'CARD', 'name_ar' => 'بطاقة', 'name_en' => 'Card', 'type' => 'card', 'requires_evidence' => false, 'offline_eligible' => false],
        ] as $method) {
            PaymentMethod::query()->firstOrCreate(['code' => $method['code']], $method + ['status' => 'active']);
        }
        TaxSetting::query()->firstOrCreate(['code' => 'ZERO'], [
            'name_ar' => 'ضريبة صفرية', 'name_en' => 'Zero Rated', 'rate' => '0.00',
            'treatment' => 'zero_rated', 'is_default' => true, 'is_tax_inclusive' => true, 'status' => 'active',
        ]);
        foreach ([
            'retail_sale' => 'SAL-', 'purchase_order' => 'PO-', 'purchase_invoice' => 'PIN-',
            'supplier_return' => 'SR-', 'inventory_adjustment' => 'ADJ-', 'stock_transfer' => 'TRF-',
            'stock_count' => 'CNT-', 'shift_close' => 'SHC-', 'gift_receipt' => 'GR-',
            'party_booking' => 'PB-', 'party_invoice' => 'PI-', 'party_operating_order' => 'POO-',
            'party_final_invoice' => 'PFI-', 'party_final_receipt' => 'PFR-',
            'party_payment_receipt' => 'PPR-', 'quotation' => 'QT-',
        ] as $documentType => $prefix) {
            DocumentSequence::query()->firstOrCreate(['document_type' => $documentType, 'scope_key' => 'company'], [
                'scope_type' => 'company', 'scope_id' => null,
                'prefix' => $prefix, 'padding_length' => 6, 'next_value' => 1,
                'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1,
            ]);
        }
        PrinterConfiguration::query()->firstOrCreate(['name' => 'DEFAULT-THERMAL'], [
            'printer_type' => 'thermal', 'paper_size' => '80mm', 'template_name' => 'default_thermal',
            'connection_type' => 'browser', 'is_default' => true, 'status' => 'active',
        ]);
    }

    /**
     * Production grants for operational roles other than System Administrator.
     *
     * docs/04-roles-permissions.md line 12: "P and R entries are not production
     * grants." This is exactly the TSK-008 Foundation scope DEC-038 froze as the
     * approved-without-amendment Canonical Authorization Matrix. The bootstrap
     * System Administrator receives every active permission so the owner can
     * complete Production setup through guarded UI flows. Every other module
     * implemented since (TSK-014..TSK-022: suppliers, purchase orders/invoices/
     * returns, pricing, inventory, transfers, stock counts) was authorized
     * Local/Dev-only (DEC-051/052/054/058/059) and must not reach a Production
     * grant until the owner ratifies a docs/04 amendment. See
     * testing/results/DEFECTS.md QA-002.
     *
     * @return array<string, list<string>>
     */
    public static function productionSafeRolePermissions(): array
    {
        return [
            'system-administrator' => [],
            'branch-manager' => ['branches_stores.view', 'pos_sales.view'],
            'cashier' => [
                'pos_sales.view', 'pos_sales.create', 'pos_sales.print',
                'pos_sales.payment_view', 'pos_sales.payment_create',
                'pos_sales.payment_evidence_upload', 'pos_sales.payment_evidence_view',
                'suspended_sales.view',
                'shifts_cash_movements.view', 'shifts_cash_movements.create',
                'shifts_cash_movements.edit', 'shifts_cash_movements.submit', 'shifts_cash_movements.print',
                'products_categories_brands.view', 'inventory_stock_card.view',
                'customers.view', 'customers.create', 'customers.edit',
            ],
            'accountant-reviewer' => ['dashboard_reports.view', 'audit_logs.view'],
            'purchasing-officer' => ['suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.preferred_change'],
            'warehouse-manager' => [],
            'pricing-officer' => [],
            'party-manager' => [],
            'stock-counter' => [],
        ];
    }
}
