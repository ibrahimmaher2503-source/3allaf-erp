<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class V022RequirementFamiliesTest extends TestCase
{
    private function s(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_rbac_cashier_and_scope_family(): void
    {
        $seed=$this->s('database/seeders/ProductionSeeder.php'); $shift=$this->s('app/Modules/Retail/Actions/OpenShiftAction.php'); $customer=$this->s('app/Modules/Customer/Models/Customer.php');
        foreach(['pos_sales.create','pos_sales.payment_create','shifts_cash_movements.create'] as $p) self::assertStringContainsString($p,$seed);
        self::assertStringContainsString('visibleTo($cashier)', $shift); self::assertStringContainsString("where('cashier_id', \$cashier->id)", $shift);
        self::assertStringContainsString("whereIn('company_id'",$customer); self::assertStringNotContainsString("where('created_store_id'",substr($customer,strpos($customer,'scopeVisibleTo')));
    }

    public function test_setup_order_actions_and_persistent_sequence_family(): void
    {
        $status=$this->s('app/Modules/Platform/Support/InitialSetupStatus.php'); $context=$this->s('resources/views/components/setup/context.blade.php'); $setup=$this->s('resources/views/platform/initial-setup.blade.php');
        $stepBlock = substr($status, strpos($status, '$steps = ['), strpos($status, 'foreach ($steps', strpos($status, '$steps = [')) - strpos($status, '$steps = ['));
        self::assertSame(20, substr_count($stepBlock, '$this->step('));
        foreach(['basics','settings','operations'] as $v) self::assertStringContainsString($v,$context.$setup);
        self::assertStringContainsString('opening-configuration', $status);
        foreach(['Previous','Next','Setup Center','setup_step'] as $v) self::assertStringContainsString($v,$context);
        foreach(['skipped','deferred','Complete later'] as $v) self::assertStringContainsString($v,$setup.$status);
        self::assertStringNotContainsString('product-import',substr($status,strpos($status,'private function groups')));
    }

    public function test_printing_import_export_family(): void
    {
        $settings=$this->s('resources/views/platform/admin/settings.blade.php'); $save=$this->s('app/Modules/Platform/Actions/SaveLocalSettingsAction.php'); $customer=$this->s('routes/customers.php'); $catalog=$this->s('routes/catalog-data-exchange.php').$this->s('routes/catalog.php');
        foreach(['Printing Template Library','print_template_id','updatedPrinterFormScopeType','label_50x30mm','a5'] as $v) self::assertStringContainsString($v,$settings.$save);
        foreach(['customers.import.template','customers.export','customers.groups.export'] as $v) self::assertStringContainsString($v,$customer);
        foreach(['products/import/template.xlsx','catalog.data-exchange.export','suppliers/import/template'] as $v) self::assertStringContainsString($v,$catalog);
        self::assertGreaterThanOrEqual(4,substr_count($customer.$catalog,'limit(5000)'));
    }

    public function test_customer_geography_hierarchy_duplicate_and_approval_family(): void
    {
        $create=$this->s('app/Modules/Customer/Actions/CreateCustomerAction.php'); $update=$this->s('app/Modules/Customer/Actions/UpdateCustomerAction.php'); $import=$this->s('app/Modules/Customer/Actions/StageCustomerImportAction.php'); $merge=$this->s('app/Modules/Customer/Actions/MergeCustomersAction.php'); $routes=$this->s('routes/customers.php'); $views=$this->s('resources/views/pages/customers/show.blade.php').$this->s('resources/views/pages/customers/groups.blade.php');
        foreach(['ResidenceGeography::validate','customer_group_id','forCompany((int) $store->company_id)','active()'] as $v) self::assertStringContainsString($v,$create.$update);
        self::assertStringNotContainsString("whereDoesntHave('children')", $create.$update);
        self::assertGreaterThanOrEqual(2, substr_count($routes, 'GroupHierarchy::flatten($allGroups)'));
        foreach(['duplicate_existing','duplicate_file','PhoneNormalizer','active()'] as $v) self::assertStringContainsString($v,$import);
        foreach(['sales','loyalty','wallet','children','CustomerMergeEvent','DB::transaction'] as $v) self::assertStringContainsString($v,$merge);
        foreach(['governorate_id','city_id','address_ar','address_en','limit(5000)'] as $v) self::assertStringContainsString($v,$routes);
        self::assertStringNotContainsString('Back to Customers',$views); self::assertStringNotContainsString('Customer Approval Log',$views);
    }

    public function test_supplier_group_and_financial_readiness_family(): void
    {
        $save=$this->s('app/Modules/Catalog/Actions/SaveSupplierAction.php'); $group=$this->s('app/Modules/Catalog/Actions/SaveSupplierGroupAction.php'); $status=$this->s('app/Modules/Platform/Support/InitialSetupStatus.php'); $view=$this->s('resources/views/catalog/suppliers.blade.php');
        foreach(['settlement_method','settlement_other_description','payment_terms'] as $v) self::assertStringContainsString($v,$save.$view);
        foreach(['parent_id','ancestor','suppliers_count','sort_order'] as $v) self::assertStringContainsStringIgnoringCase($v,$group.$view);
        $method=substr($status,strpos($status,'private function suppliersReady'),strpos($status,'/**',strpos($status,'private function suppliersReady'))-strpos($status,'private function suppliersReady'));
        self::assertStringContainsString('settlement_method',$method); self::assertStringNotContainsString('payment_terms',$method); self::assertStringNotContainsString('product',$method);
    }

    public function test_product_filter_card_barcode_and_import_family(): void
    {
        $form=$this->s('resources/views/catalog/product-form.blade.php'); $options=$this->s('resources/views/catalog/product-options.blade.php'); $save=$this->s('app/Modules/Catalog/Actions/SaveProductAction.php'); $barcode=$this->s('app/Modules/Catalog/Actions/AddBarcodeAction.php'); $import=$this->s('app/Modules/Catalog/Actions/ImportProductCardsWorkbookAction.php');
        foreach(['Add Product Card','barcode_registration_type','model_number','average_cost','sale_price','category_id','brand_id','open_price','product_type'] as $v) self::assertStringContainsString($v,$form.$save);
        foreach(['image','filter','length','weight','sell_online','short_description','full_description','battery_required'] as $v) self::assertStringContainsStringIgnoringCase($v,$form.$save);
        foreach(['lockForUpdate','allocation_key','next_serial'] as $v) self::assertStringContainsString($v,$barcode);
        self::assertStringContainsString('HEADERS',$import); self::assertStringContainsString('rejections',$import); self::assertStringContainsString('Product Filters',$options);
    }

    public function test_pricing_resolution_approval_and_readiness_family(): void
    {
        $list=$this->s('app/Modules/Pricing/Actions/SavePriceListAction.php'); $override=$this->s('app/Modules/Pricing/Actions/SaveProductPriceOverrideAction.php'); $resolver=$this->s('app/Modules/Pricing/Services/PriceListResolver.php'); $approval=$this->s('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php'); $ready=$this->s('app/Modules/Platform/Support/ProductPricingReadiness.php').$this->s('app/Modules/Platform/Support/InitialSetupStatus.php');
        foreach(['list_number','percentage_increase','effective_from','status'] as $v) self::assertStringContainsString($v,$list);
        foreach(['lockForUpdate','RecordAuditEvent','delete'] as $v) self::assertStringContainsString($v,$override);
        foreach(['branch','list_number', 'override'] as $v) self::assertStringContainsStringIgnoringCase($v,$resolver);
        foreach(['previous_product_cost','previous_base_consumer_price','base_consumer_price','RecordAuditEvent'] as $v) self::assertStringContainsString($v,$approval);
        foreach(['affected_products','invalid_base_price','outlets'] as $v) self::assertStringContainsStringIgnoringCase($v,$ready);
    }

    public function test_opening_inventory_family(): void
    {
        $route=$this->s('routes/opening-inventory.php'); $view=$this->s('resources/views/inventory/opening.blade.php'); $approve=$this->s('app/Modules/Inventory/Actions/ApproveOpeningInventoryAction.php');
        foreach(['inventory.opening.index','zero','template','import','approve'] as $v) self::assertStringContainsStringIgnoringCase($v,$route.$view);
        foreach(['DB::transaction','lockForUpdate','PostInventoryMovement','RecordAuditEvent'] as $v) self::assertStringContainsString($v,$approve);
    }

    public function test_purchasing_distribution_transfer_label_and_transit_family(): void
    {
        $invoice=$this->s('resources/views/purchasing/invoices.blade.php'); $save=$this->s('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php'); $approve=$this->s('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php'); $receive=$this->s('app/Modules/Inventory/Actions/ReceiveStockTransferAction.php'); $labelRoute=$this->s('routes/pricing.php'); $labelService=$this->s('app/Modules/Pricing/Services/BarcodeLabelService.php'); $labelView=$this->s('resources/views/pricing/label-print.blade.php'); $migration=$this->s('database/migrations/2026_09_01_000102_link_purchase_distributions_to_transfers.php');
        foreach(['limit(20)','productSearchRequest','Stage 1','Stage 2','Remaining','createQuickProduct'] as $v) self::assertStringContainsString($v,$invoice);
        foreach(['visibleTo($actor)','temporary receiving warehouse cannot be a final destination','resolveWithBasePrice'] as $v) self::assertStringContainsString($v,$save);
        foreach(['DB::transaction','purchase_invoice_id','StockTransfer','adjustInTransit',"'status' => 'in_transit'",'zero remaining quantity'] as $v) self::assertStringContainsString($v,$approve.$migration);
        foreach(['transfer_receipt','adjustInTransit','difference_review'] as $v) self::assertStringContainsString($v,$receive);
        foreach(['barcode_label_invoice_context','purchase_invoices.print_labels','PurchaseInvoice','destination_store_id'] as $v) self::assertStringContainsString($v,$labelRoute);
        foreach(['PriceListResolver','active product barcode','copies','labels'] as $v) self::assertStringContainsStringIgnoringCase($v,$labelService.$labelView);
    }
}
