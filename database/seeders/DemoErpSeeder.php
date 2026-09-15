<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Actions\SavePosFinancialSettingAction;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A compact, local-only, deterministic data set for exercising real ERP actions.
 *
 * Transactional documents below deliberately go through the same action classes
 * used by the UI, rather than inserting sale, stock, or purchasing rows directly.
 */
final class DemoErpSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('DemoErpSeeder is local/testing only and cannot generate products in production.');
        }

        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'admin')->firstOrFail();
            $approver = $this->workflowApprover();

            Auth::login($administrator);
            [$branch, $salesStore, $warehouseStore] = $this->locations();
            $this->scopeApprover($approver, $branch, $salesStore, $warehouseStore);
            $this->customerAndPosPolicies();
            $this->catalogReferenceData();

            $category = $this->category();
            $supplier = $this->supplier();
            $product = $this->product($category);
            $this->productSupplier($product, $supplier);
            $this->price($product, $salesStore, $approver);
            $customer = $this->customer($administrator, $salesStore);

            $purchaseInvoice = $this->purchaseReceipt($administrator, $approver, $supplier, $warehouseStore, $product);
            $this->purchaseReturn($administrator, $approver, $purchaseInvoice);
            $this->transfer($administrator, $approver, $warehouseStore, $salesStore, $product);
            $this->posSale($administrator, $salesStore, $customer, $product);

            Auth::logout();
        });
    }

    /** @return array{Branch, Store, Store} */
    private function locations(): array
    {
        $branch = Branch::query()->where('code', 'DEMO')->first();
        if ($branch === null) {
            $branch = app(SaveBranchAction::class)->execute([
                'code' => 'DEMO',
                'name_ar' => 'فرع العرض التجريبي',
                'name_en' => 'Demo Branch',
                'phone' => '01000000001',
                'email' => 'demo-branch@example.test',
                'address' => 'Demo data only',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'policy_notes' => 'DEMO: isolated local ERP workflow data.',
            ]);
        }

        $salesStore = Store::query()->where('code', 'DEMO-SALES')->first();
        if ($salesStore === null) {
            $salesStore = app(SaveStoreAction::class)->execute([
                'branch_id' => $branch->id,
                'code' => 'DEMO-SALES',
                'type' => 'selling',
                'name_ar' => 'نقطة بيع العرض التجريبي',
                'name_en' => 'Demo Sales Store',
                'status' => 'active',
                'allows_negative_stock' => false,
                'policy_notes' => 'DEMO: POS demonstration location.',
            ]);
        } else {
            $salesStore->forceFill(['status' => 'active', 'allows_negative_stock' => false])->save();
        }

        $warehouseStore = Store::query()->where('code', 'DEMO-WAREHOUSE')->first();
        if ($warehouseStore === null) {
            $warehouseStore = app(SaveStoreAction::class)->execute([
                'branch_id' => $branch->id,
                'code' => 'DEMO-WAREHOUSE',
                'type' => 'warehouse',
                'name_ar' => 'مخزن العرض التجريبي',
                'name_en' => 'Demo Warehouse',
                'status' => 'active',
                'allows_negative_stock' => false,
                'policy_notes' => 'DEMO: procurement receipt location.',
            ]);
        } else {
            $warehouseStore->forceFill(['status' => 'active', 'allows_negative_stock' => false])->save();
        }

        app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $salesStore->id, 'DEMO: local POS mapping.');

        return [$branch, $salesStore, $warehouseStore];
    }

    private function workflowApprover(): User
    {
        $approver = User::query()->where('username', 'demo-seed-approver')->first();
        if ($approver === null) {
            $approver = User::query()->create([
                'name' => 'Demo Workflow Approver',
                'username' => 'demo-seed-approver',
                'email' => 'demo-seed-approver@example.test',
                'password' => Hash::make(Str::password(48)),
                'status' => 'active',
            ]);
            $approver->forceFill(['email_verified_at' => now()])->save();
        }

        $approver->roles()->syncWithoutDetaching([
            Role::query()->where('code', 'system-administrator')->value('id'),
        ]);

        return $approver->fresh();
    }

    private function scopeApprover(User $approver, Branch $branch, Store ...$stores): void
    {
        UserBranchScope::query()->firstOrCreate([
            'user_id' => $approver->id,
            'branch_id' => $branch->id,
        ], ['status' => 'active']);

        foreach ($stores as $store) {
            UserStoreScope::query()->firstOrCreate([
                'user_id' => $approver->id,
                'store_id' => $store->id,
            ], ['status' => 'active']);
        }
    }

    private function customerAndPosPolicies(): void
    {
        $customerPolicies = [
            'customer.phone_normalization' => 'digits_only',
            'customer.consent.purpose' => '["service","loyalty"]',
            'customer.consent.wording' => '{"version":"DEMO-V1","text":"Demo data consent for local workflow testing."}',
            'customer.consent.retention' => '{"days":365}',
            'customer.children.purpose_scope' => '["birthday"]',
            'loyalty.retail_rule' => '{"earn_points_per_currency":"1","redeem_currency_per_point":"0.01"}',
            'loyalty.expiry_policy' => '{"days":30}',
            'loyalty.rounding_policy' => '{"earn":"floor","redeem":"floor"}',
            'loyalty.approval_policy' => '{"adjustment_requires_approval":true}',
            'loyalty.ledger_integrity' => '{"enabled":true}',
        ];

        foreach ($customerPolicies as $key => $value) {
            $latestValue = CustomerPolicySettingVersion::query()
                ->where('key', $key)
                ->latest('version')
                ->value('value');

            if (trim((string) $latestValue) === '') {
                app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'DEMO: local ERP seed prerequisite.');
            }
        }

        if (! PosFinancialSettingVersion::query()->where('key', PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION)->exists()) {
            app(SavePosFinancialSettingAction::class)->execute(
                PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
                '5.0000',
                'DEMO: local ERP seed prerequisite.',
            );
        }
    }

    /** Seed only the reusable catalog values required to exercise product cards. */
    private function catalogReferenceData(): void
    {
        foreach ([
            ['code' => 'BOYS', 'name_ar' => 'أولاد', 'name_en' => 'Boys'],
            ['code' => 'GIRLS', 'name_ar' => 'بنات', 'name_en' => 'Girls'],
            ['code' => 'BOYS_GIRLS', 'name_ar' => 'أولاد وبنات', 'name_en' => 'Boys & Girls'],
        ] as $index => $row) {
            Gender::query()->updateOrCreate(['code' => $row['code']], [...$row, 'status' => 'active', 'sort_order' => $index + 1]);
        }

        foreach ([
            ['code' => 'AGE_0_6_MONTHS', 'name_ar' => 'من 0 إلى 6 أشهر', 'name_en' => '0–6 months'],
            ['code' => 'AGE_6_12_MONTHS', 'name_ar' => 'من 6 إلى 12 شهرًا', 'name_en' => '6–12 months'],
            ['code' => 'AGE_0_1_YEAR', 'name_ar' => 'من 0 إلى سنة', 'name_en' => '0–1 year (Baby)'],
            ['code' => 'AGE_1_3_YEARS', 'name_ar' => 'من سنة إلى 3 سنوات', 'name_en' => '1–3 years (Toddler)'],
            ['code' => 'AGE_3_6_YEARS', 'name_ar' => 'من 3 إلى 6 سنوات', 'name_en' => '3–6 years (Preschool)'],
            ['code' => 'AGE_6_12_YEARS', 'name_ar' => 'من 6 إلى 12 سنة', 'name_en' => '6–12 years (Kids)'],
            ['code' => 'AGE_12_PLUS', 'name_ar' => '12 سنة فأكثر', 'name_en' => '12+ years (Teens & Adults)'],
        ] as $index => $row) {
            AgeLabel::query()->updateOrCreate(['code' => $row['code']], [...$row, 'status' => 'active', 'sort_order' => $index + 1]);
        }

        foreach ([
            ['code' => 'RED', 'name_ar' => 'أحمر', 'name_en' => 'Red'],
            ['code' => 'BLUE', 'name_ar' => 'أزرق', 'name_en' => 'Blue'],
            ['code' => 'YELLOW', 'name_ar' => 'أصفر', 'name_en' => 'Yellow'],
            ['code' => 'GREEN', 'name_ar' => 'أخضر', 'name_en' => 'Green'],
            ['code' => 'BLACK', 'name_ar' => 'أسود', 'name_en' => 'Black'],
            ['code' => 'WHITE', 'name_ar' => 'أبيض', 'name_en' => 'White'],
        ] as $index => $row) {
            Colour::query()->updateOrCreate(['code' => $row['code']], [...$row, 'status' => 'active', 'sort_order' => $index + 1]);
        }
    }

    private function category(): Category
    {
        $category = Category::query()->where('code', 'DEMO-TOYS')->first();

        return $category ?? app(SaveCategoryAction::class)->execute([
            'code' => 'DEMO-TOYS',
            'name_ar' => 'ألعاب العرض التجريبي',
            'name_en' => 'Demo Toys',
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 999,
        ]);
    }

    private function supplier(): Supplier
    {
        $supplier = Supplier::query()->where('code', 'DEMO-SUPPLIER-001')->first();

        return $supplier ?? app(SaveSupplierAction::class)->execute([
            'code' => 'DEMO-SUPPLIER-001',
            'name_ar' => 'مورد العرض التجريبي',
            'name_en' => 'Demo Supplier',
            'contact_name' => 'Demo Contact',
            'email' => 'demo-supplier@example.test',
            'phone' => '01000000002',
            'payment_terms' => 'Demo only; no AP module exists.',
            'address' => 'Demo data only',
            'status' => 'active',
        ]);
    }

    private function product(Category $category): Product
    {
        $product = Product::query()->where('item_code', 'DEMO-PRODUCT-001')->first();

        if ($product !== null) {
            $product->forceFill(['status' => 'active'])->save();

            return $product;
        }

        return app(SaveProductAction::class)->execute([
            'item_code' => 'DEMO-PRODUCT-001',
            'name_ar' => 'مكعبات بناء تجريبية',
            'name_en' => 'Demo Building Blocks',
            'description_ar' => 'منتج عرض لتدفق الشراء والمخزون والبيع.',
            'description_en' => 'Demo product for procurement, inventory, and POS.',
            'product_type' => 'standard',
            'unit_of_measure' => 'piece',
            'category_id' => $category->id,
            'status' => 'active',
            'average_cost' => '10.0000',
            'reorder_threshold' => '2.0000',
            'fractional_quantity' => false,
        ]);
    }

    private function productSupplier(Product $product, Supplier $supplier): void
    {
        if (ProductSupplier::query()->where('product_id', $product->id)->where('supplier_id', $supplier->id)->exists()) {
            return;
        }

        app(SaveProductSupplierAction::class)->execute([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_item_code' => 'DEMO-SUPPLIER-SKU-001',
            'is_preferred' => true,
            'notes' => 'DEMO: preferred supplier link.',
        ]);
    }

    private function price(Product $product, Store $salesStore, User $approver): void
    {
        if (PriceLine::query()->where('product_id', $product->id)->where('store_id', $salesStore->id)->whereNotNull('active_key')->exists()) {
            return;
        }

        $proposal = app(CreatePriceProposalAction::class)->execute(
            $product,
            $salesStore,
            'DEMO-RETAIL',
            'قائمة أسعار العرض التجريبي',
            'Demo Retail Price List',
            '15.00',
            'product_card',
            'DEMO-PRICE-001',
            null,
            null,
            'DEMO: local retail price for POS.',
        );
        app(SubmitPriceProposalAction::class)->execute($proposal);

        Auth::login($approver);
        app(ApprovePriceProposalAction::class)->execute($proposal);
        Auth::login(User::query()->where('username', 'admin')->firstOrFail());
    }

    private function customer(User $administrator, Store $salesStore): Customer
    {
        return app(CreateCustomerAction::class)->execute($administrator, $salesStore, [
            'idempotency_key' => 'demo-customer-001',
            'phone' => '01000000003',
            'name_ar' => 'عميل العرض التجريبي',
            'name_en' => 'Demo Customer',
            'email' => 'demo-customer@example.test',
            'address_ar' => 'بيانات عرض فقط',
            'address_en' => 'Demo data only',
            'consents' => [['purpose' => 'service', 'status' => 'granted', 'source' => 'profile_create']],
        ]);
    }

    private function purchaseReceipt(User $administrator, User $approver, Supplier $supplier, Store $warehouse, Product $product): PurchaseInvoice
    {
        $order = PurchaseOrder::query()->where('notes', 'demo:purchase-order-001')->first();
        if ($order === null) {
            $order = app(SavePurchaseOrderAction::class)->execute([
                'supplier_id' => $supplier->id,
                'store_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'payment_terms' => 'Demo only; no AP payment module exists.',
                'notes' => 'demo:purchase-order-001',
            ], [[
                'product_id' => $product->id,
                'quantity_ordered' => '12.000000',
                'unit_cost' => '10.0000',
                'notes' => 'DEMO: full receipt required by the implemented workflow.',
            ]]);
            $order = app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Auth::login($approver);
            app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Auth::login($administrator);
        }

        $invoice = PurchaseInvoice::query()->where('supplier_reference', 'DEMO-SUPPLIER-INVOICE-001')->first();
        if ($invoice !== null) {
            return $invoice;
        }

        $order = $order->fresh('lines');
        $invoice = app(SavePurchaseInvoiceAction::class)->execute([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'store_id' => $warehouse->id,
            'supplier_reference' => 'DEMO-SUPPLIER-INVOICE-001',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EGP',
            'notes' => 'DEMO: procurement receipt posts stock through the approved invoice action.',
        ], [[
            'product_id' => $product->id,
            'purchase_order_line_id' => $order->lines->firstOrFail()->id,
            'quantity' => '12.000000',
            'unit_cost' => '10.0000',
            'discount_type' => null,
            'discount_value' => '0.0000',
            'tax_rate' => '0.0000',
        ]]);
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        Auth::login($approver);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        Auth::login($administrator);

        return $invoice;
    }

    private function purchaseReturn(User $administrator, User $approver, PurchaseInvoice $invoice): void
    {
        $reason = SupplierReturnReason::query()->firstOrCreate(['code' => 'DEMO-QUALITY'], [
            'label_ar' => 'مرتجع عرض تجريبي',
            'label_en' => 'Demo quality return',
            'is_active' => true,
        ]);
        $return = PurchaseReturn::query()->where('idempotency_key', 'demo-purchase-return-001')->first();
        if ($return !== null) {
            return;
        }

        $invoice = $invoice->fresh('lines');
        $return = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
            'purchase_invoice_line_id' => $invoice->lines->firstOrFail()->id,
            'quantity' => '1.000000',
        ]], 'demo-purchase-return-001');
        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        Auth::login($approver);
        app(ApprovePurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        Auth::login($administrator);
    }

    private function transfer(User $administrator, User $approver, Store $warehouse, Store $salesStore, Product $product): void
    {
        $transfer = StockTransfer::query()->where('idempotency_key', 'demo-transfer-001')->first();
        if ($transfer !== null) {
            return;
        }

        $transfer = app(CreateStockTransferDraftAction::class)->execute(
            $warehouse->id,
            $salesStore->id,
            [['product_id' => $product->id, 'quantity_requested' => '10.000000']],
            'demo_replenishment',
            'DEMO: warehouse to POS replenishment.',
            'demo-transfer-001',
        );
        $transfer = app(SubmitStockTransferAction::class)->execute($transfer->id);
        Auth::login($approver);
        $transfer = app(ApproveStockTransferAction::class)->execute($transfer->id);
        Auth::login($administrator);
        $transfer = app(DispatchStockTransferAction::class)->execute($transfer->id);
        $line = $transfer->fresh('lines')->lines->firstOrFail();
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '10.000000'], null, null);
    }

    private function posSale(User $administrator, Store $salesStore, Customer $customer, Product $product): void
    {
        $drawer = CashDrawer::query()->where('code', 'DEMO-POS-01')->first();
        if ($drawer === null) {
            $drawer = CashDrawer::query()->create([
                'company_id' => $salesStore->company_id,
                'branch_id' => $salesStore->branch_id,
                'store_id' => $salesStore->id,
                'assigned_user_id' => $administrator->id,
                'code' => 'DEMO-POS-01',
                'name_ar' => 'درج نقدية العرض التجريبي',
                'name_en' => 'Demo POS Cash Drawer',
                'status' => 'active',
                'policy_notes' => 'DEMO: dedicated drawer for local POS workflow.',
            ]);
        }

        if (! PosShift::query()->where('idempotency_key', 'demo-pos-shift-001')->exists()) {
            app(OpenShiftAction::class)->execute($administrator, $drawer, '100.00', 'demo-pos-shift-001');
        }

        if (Sale::query()->where('idempotency_key', 'demo-sale-001')->exists()) {
            return;
        }

        $cash = PaymentMethod::query()->where('code', 'CASH')->where('status', 'active')->firstOrFail();
        app(RetailSaleAction::class)->create(
            $administrator,
            $salesStore,
            [['product_id' => $product->id, 'quantity' => '1.000000']],
            'demo-sale-001',
            false,
            [['method' => $cash, 'amount' => '15.00', 'tendered' => '15.00']],
            ['tax_applicable' => false],
            $customer,
        );
    }
}
