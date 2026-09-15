<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Assets\Actions\CreateAssetAction;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Party\Actions\ConfirmPartyBookingAction;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Actions\CreatePartyOperatingOrderAction;
use App\Modules\Party\Actions\ReleasePartyOperatingOrderAction;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Actions\SaveUserAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceLine;
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
use LogicException;

/**
 * Opt-in, isolated remediation fixtures. This seeder is intentionally absent
 * from DatabaseSeeder and refuses every database other than the named fixture.
 */
final class RemediationSeeder extends Seeder
{
    private const TARGET_DATABASE = 'toyjoy_phase1_remediation_20260818';

    public function run(): void
    {
        $password = $this->guardRuntime();
        $previousUser = Auth::user();

        try {
            DB::transaction(function () use ($password): void {
                app(CanonicalAuthorizationSeeder::class)->run();
                $this->grantFixturePermissions();

                $company = $this->company();
                $administrator = $this->administrator($password);
                Auth::login($administrator);

                [$branch, $sales, $warehouse, $party, $altBranch, $altSales] = $this->locations();
                $users = $this->users($password, $branch, $sales, $warehouse, $party, $altBranch, $altSales);
                $this->drawersAndShifts($company, $branch, $sales, $users);
                $this->configuration();
                $this->catalogAndCustomers($sales, $party, $users);
                $this->pricesAndStock($sales, $warehouse, $users);
                $this->sourceSale($sales, $users);
                $this->partyPrerequisites($party, $users);
            });
        } finally {
            $previousUser === null ? Auth::logout() : Auth::login($previousUser);
        }
    }

    private function guardRuntime(): string
    {
        if (! app()->environment(config('remediation.environments'))) {
            throw new LogicException('RemediationSeeder may only run in local or testing environments.');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        if ($database !== self::TARGET_DATABASE || config('remediation.database') !== self::TARGET_DATABASE) {
            throw new LogicException('RemediationSeeder requires the toyjoy_phase1_remediation_20260818 database.');
        }

        $password = getenv('REMEDIATION_FIXTURE_PASSWORD');
        if (! is_string($password) || mb_strlen($password) < 16) {
            throw new LogicException('REMEDIATION_FIXTURE_PASSWORD must be set at runtime and contain at least 16 characters.');
        }

        return $password;
    }

    private function company(): Company
    {
        return Company::query()->firstOrCreate(['code' => 'REM-COMPANY'], [
            'name_ar' => 'شركة معالجة المراجعة',
            'name_en' => 'Remediation Fixture Company',
            'legal_name' => 'Remediation Fixture Company',
            'currency_code' => 'EGP',
            'currency_symbol' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale_default' => 'en',
            'status' => 'active',
            'policy_notes' => 'Isolated remediation fixture only.',
        ]);
    }

    private function grantFixturePermissions(): void
    {
        $permissions = [
            'shifts_cash_movements.create', 'shifts_cash_movements.view', 'shifts_cash_movements.submit',
            'offline_queue_conflicts.create', 'offline_queue_conflicts.submit', 'offline_queue_conflicts.view',
            'offline_queue_conflicts.approve',
        ];
        foreach ($permissions as $code) {
            [$module, $action] = explode('.', $code, 2);
            Permission::query()->firstOrCreate(['code' => $code], [
                'module' => $module,
                'action' => $action,
                'sensitivity' => 'sensitive',
                'status' => 'active',
            ]);
        }

        foreach ([
            'cashier' => ['shifts_cash_movements.create', 'shifts_cash_movements.view', 'shifts_cash_movements.submit', 'pos_sales.payment_create', 'loyalty.earn', 'offline_queue_conflicts.create', 'offline_queue_conflicts.submit', 'offline_queue_conflicts.view', 'returns_exchanges_gift_instruments.view', 'gift_receipts.issue', 'gift_receipts.print', 'returns.create', 'returns.submit', 'returns.complete', 'returns.print', 'gift_cards.issue', 'gift_cards.redeem', 'gift_cards.print'],
            'accountant-reviewer' => ['offline_queue_conflicts.view', 'offline_queue_conflicts.approve', 'returns.approve'],
            'pricing-officer' => ['pricing_labels.create', 'pricing_labels.submit'],
            'purchasing-officer' => [
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit',
                'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.create', 'purchase_invoices_supplier_returns.edit',
                'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.edit',
                'inventory_stock_card.view', 'inventory_stock_card.create', 'inventory_stock_card.submit',
                'transfers.view', 'transfers.create', 'transfers.submit', 'transfers.dispatch',
                'stock_counts.view', 'stock_counts.create', 'stock_counts.edit', 'stock_counts.submit',
            ],
            'warehouse-manager' => ['inventory_stock_card.create', 'inventory_stock_card.submit', 'transfers.create', 'transfers.submit', 'transfers.dispatch', 'transfers.receive'],
            'accountant-reviewer' => ['offline_queue_conflicts.view', 'offline_queue_conflicts.approve', 'returns.approve', 'purchase_orders.approve', 'purchase_invoices_supplier_returns.approve', 'purchase_returns.approve', 'inventory_stock_card.approve', 'transfers.approve', 'stock_counts.reconcile'],
            'party-manager' => [
                'customers.create',
                'rental_assets.create', 'rental_assets.reserve', 'rental_assets.view', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect',
                'quotations.view', 'quotations.create', 'quotations.print',
                'party_bookings_invoices.view', 'party_bookings_invoices.create', 'party_bookings_invoices.approve', 'party_bookings_invoices.print',
                'party_operating_orders_consumables.view', 'party_operating_orders_consumables.create', 'party_operating_orders_consumables.approve',
            ],
        ] as $roleCode => $codes) {
            Role::query()->where('code', $roleCode)->firstOrFail()->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', $codes)->pluck('id')->all(),
            );
        }
    }

    private function administrator(string $password): User
    {
        $administrator = User::query()->firstOrNew(['username' => 'rem-admin']);
        $administrator->forceFill([
            'name' => 'Remediation Administrator',
            'email' => 'rem-admin@example.test',
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => now(),
            'is_super_admin' => true,
        ])->save();
        $administrator->roles()->sync([Role::query()->where('code', 'system-administrator')->value('id')]);

        return $administrator->fresh();
    }

    /** @return array{Branch, Store, Store, Store, Branch, Store} */
    private function locations(): array
    {
        $branch = $this->branch('REM-BRANCH', 'فرع المعالجة', 'Remediation Branch');
        $sales = $this->store($branch, 'REM-SALES', 'selling', 'مبيعات المعالجة', 'Remediation Sales');
        $warehouse = $this->store($branch, 'REM-WAREHOUSE', 'warehouse', 'مخزن المعالجة', 'Remediation Warehouse');
        $party = $this->store($branch, 'REM-PARTY', 'party', 'حفلات المعالجة', 'Remediation Party');
        app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $sales->id, 'Remediation fixture POS mapping.');

        $altBranch = $this->branch('REM-ALT-BRANCH', 'فرع معالجة بديل', 'Remediation Alternate Branch');
        $altSales = $this->store($altBranch, 'REM-ALT-SALES', 'selling', 'مبيعات معالجة بديلة', 'Remediation Alternate Sales');
        app(SaveBranchSellingStoreMappingAction::class)->execute($altBranch->id, $altSales->id, 'Remediation fixture alternate POS mapping.');

        return [$branch, $sales, $warehouse, $party, $altBranch, $altSales];
    }

    private function branch(string $code, string $nameAr, string $nameEn): Branch
    {
        return Branch::query()->where('code', $code)->first()
            ?? app(SaveBranchAction::class)->execute(['code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'timezone' => 'Africa/Cairo', 'status' => 'active']);
    }

    private function store(Branch $branch, string $code, string $type, string $nameAr, string $nameEn): Store
    {
        return Store::query()->where('code', $code)->first()
            ?? app(SaveStoreAction::class)->execute(['branch_id' => $branch->id, 'code' => $code, 'type' => $type, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'status' => 'active', 'allows_negative_stock' => false]);
    }

    /** @return array<string, User> */
    private function users(string $password, Branch $branch, Store $sales, Store $warehouse, Store $party, Branch $altBranch, Store $altSales): array
    {
        $definitions = [
            'rem-requester' => ['purchasing-officer', [], [$warehouse->id]],
            'rem-reviewer' => ['accountant-reviewer', [$branch->id], [$sales->id]],
            'rem-approver' => ['accountant-reviewer', [$branch->id], [$warehouse->id, $sales->id]],
            'rem-receiver' => ['warehouse-manager', [], [$sales->id]],
            'rem-cashier' => ['cashier', [$branch->id], [$sales->id]],
            'rem-close-cashier' => ['cashier', [$branch->id], [$sales->id]],
            'rem-warehouse' => ['warehouse-manager', [$branch->id], [$warehouse->id]],
            'rem-pricing' => ['pricing-officer', [$branch->id], [$sales->id]],
            'rem-counter' => ['stock-counter', [$branch->id], [$warehouse->id]],
            'rem-party' => ['party-manager', [$branch->id], [$party->id]],
            'rem-cross-branch-denied' => ['cashier', [$altBranch->id], [$altSales->id]],
        ];
        $users = ['rem-admin' => User::query()->where('username', 'rem-admin')->firstOrFail()];

        foreach ($definitions as $username => [$role, $branchIds, $storeIds]) {
            $existing = User::query()->where('username', $username)->first();
            $users[$username] = app(SaveUserAction::class)->execute([
                'name' => ucwords(str_replace('-', ' ', $username)),
                'username' => $username,
                'email' => $username.'@example.test',
                'password' => $password,
                'status' => 'active',
            ], [Role::query()->where('code', $role)->value('id')], $branchIds, $storeIds, $existing);
        }

        return $users;
    }

    /** @param array<string, User> $users */
    private function drawersAndShifts(Company $company, Branch $branch, Store $sales, array $users): void
    {
        foreach ([['REM-DRAWER-01', 'rem-cashier', 'remediation-shift-cashier-001'], ['REM-DRAWER-02', 'rem-close-cashier', 'remediation-shift-close-cashier-001']] as [$code, $username, $idempotencyKey]) {
            $existingDrawer = CashDrawer::query()->where('code', $code)->first();
            $drawer = app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $sales->branch_id,
                'store_id' => $sales->id,
                'assigned_user_id' => $users[$username]->id,
                'code' => $code,
                'name_ar' => 'درج معالجة',
                'name_en' => $code,
                'status' => 'active',
            ], $existingDrawer?->id);

            if (! PosShift::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                $cashier = $users[$username];
                Auth::login($cashier);
                app(OpenShiftAction::class)->execute($cashier, $drawer->fresh(), '100.00', $idempotencyKey);
                Auth::login($users['rem-admin']);
            }
        }
    }

    /** @param array<string, User> $users */
    private function pricesAndStock(Store $sales, Store $warehouse, array $users): void
    {
        $normal = Product::query()->where('item_code', 'REM-NORMAL-001')->firstOrFail();
        $open = Product::query()->where('item_code', 'REM-OPEN-PRICE-001')->firstOrFail();

        foreach ([
            [$normal, '25.000', false, null, null, null, 'remediation-normal-price-001'],
            [$open, '100.000', true, '100.000', '80.0000', '120.0000', 'remediation-open-price-001'],
        ] as [$product, $amount, $openAllowed, $reference, $minimum, $maximum, $referenceKey]) {
            if (PriceLine::query()->where('product_id', $product->id)->where('store_id', $sales->id)->exists()) {
                continue;
            }
            Auth::login($users['rem-pricing']);
            $proposal = app(CreatePriceProposalAction::class)->execute(
                $product, $sales, 'REM-RETAIL', 'Remediation prices', 'Remediation retail prices', $amount,
                'product_card', $referenceKey, null, null, 'Remediation fixture approved price.',
                $reference, $openAllowed, $minimum, $maximum,
            );
            $proposal = app(SubmitPriceProposalAction::class)->execute($proposal);
            Auth::login($users['rem-admin']);
            app(ApprovePriceProposalAction::class)->execute($proposal);
        }

        if (! InventoryAdjustment::query()->where('idempotency_key', 'remediation-opening-adjustment-001')->exists()) {
            Auth::login($users['rem-warehouse']);
            $adjustment = app(SaveInventoryAdjustmentAction::class)->execute([
                'store_id' => $warehouse->id,
                'adjustment_type' => 'entry',
                'reason_code' => 'opening_stock',
                'reason_notes' => 'Remediation source stock for approved transfer and source sale.',
                'idempotency_key' => 'remediation-opening-adjustment-001',
            ], [
                ['product_id' => $normal->id, 'quantity_delta' => '10', 'unit_cost' => '10.0000'],
                ['product_id' => $open->id, 'quantity_delta' => '10', 'unit_cost' => '20.0000'],
            ]);
            app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);
            Auth::login($users['rem-admin']);
            app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
        }

        if (! StockTransfer::query()->where('idempotency_key', 'remediation-stock-transfer-001')->exists()) {
            Auth::login($users['rem-warehouse']);
            $transfer = app(CreateStockTransferDraftAction::class)->execute($warehouse->id, $sales->id, [
                ['product_id' => $normal->id, 'quantity_requested' => '5'],
                ['product_id' => $open->id, 'quantity_requested' => '5'],
            ], 'replenishment', 'Remediation source-sale stock transfer.', 'remediation-stock-transfer-001');
            $transfer = app(SubmitStockTransferAction::class)->execute($transfer->id);
            Auth::login($users['rem-admin']);
            $transfer = app(ApproveStockTransferAction::class)->execute($transfer->id);
            $transfer = app(DispatchStockTransferAction::class)->execute($transfer->id);
            app(ReceiveStockTransferAction::class)->execute(
                $transfer->id,
                $transfer->lines->mapWithKeys(fn ($line): array => [$line->id => (string) $line->quantity_requested])->all(),
                null,
                null,
            );
        }

        Auth::login($users['rem-admin']);
    }

    /** @param array<string, User> $users */
    private function sourceSale(Store $sales, array $users): void
    {
        if (Sale::query()->where('idempotency_key', 'remediation-source-sale-001')->exists()) {
            return;
        }

        Auth::login($users['rem-cashier']);
        $cash = PaymentMethod::query()->where('code', 'REM-CASH')->firstOrFail();
        $normal = Product::query()->where('item_code', 'REM-NORMAL-001')->firstOrFail();
        $customer = Customer::query()->where('idempotency_key', 'remediation-customer-001')->firstOrFail();
        app(RetailSaleAction::class)->create(
            $users['rem-cashier'],
            $sales,
            [['product_id' => $normal->id, 'quantity' => '1']],
            'remediation-source-sale-001',
            false,
            [['method' => $cash, 'amount' => '25.00', 'tendered' => '25.00']],
            [],
            $customer,
        );
        Auth::login($users['rem-admin']);
    }

    /** @param array<string, User> $users */
    private function partyPrerequisites(Store $party, array $users): void
    {
        if (PartyBooking::query()->where('idempotency_key', 'remediation-party-booking-001')->exists()) {
            return;
        }

        $manager = $users['rem-admin'];
        Auth::login($manager);
        $asset = app(CreateAssetAction::class)->execute($manager, [
            'code' => 'REM-PARTY-ASSET-001',
            'name_ar' => 'Remediation party asset',
            'name_en' => 'Remediation party asset',
            'branch_id' => $party->branch_id,
            'store_id' => $party->id,
            'condition' => 'good',
        ]);
        $customer = Customer::query()->where('idempotency_key', 'remediation-party-customer-001')->firstOrFail();
        $start = now()->addDays(14)->setTime(14, 0);
        $booking = app(CreatePartyBookingAction::class)->execute($manager, $party, [
            'customer_id' => $customer->id,
            'party_date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHours(3)->format('H:i'),
            'timezone' => 'Africa/Cairo',
            'location' => 'Remediation Party Room',
            'primary_contact' => '01000000992',
            'idempotency_key' => 'remediation-party-booking-001',
            'lines' => [[
                'line_type' => 'rental_asset',
                'asset_id' => $asset->id,
                'description' => 'Remediation Party Asset',
                'quantity' => '1',
                'unit_price' => '0.0000',
            ]],
        ]);
        Auth::login($users['rem-admin']);
        $booking = app(ConfirmPartyBookingAction::class)->execute($users['rem-admin'], $booking, 'Remediation fixture confirmed booking.');
        Auth::login($manager);
        $order = app(CreatePartyOperatingOrderAction::class)->execute(
            $manager,
            $booking,
            $booking->invoice,
            'remediation-party-operating-order-001',
        );
        Auth::login($users['rem-admin']);
        app(ReleasePartyOperatingOrderAction::class)->execute($users['rem-admin'], $order);
        Auth::login($users['rem-admin']);
    }

    private function configuration(): void
    {
        foreach ([
            ['REM-CASH', 'Remediation cash', 'cash', true],
            ['REM-MANUAL-ELECTRONIC', 'Remediation manual electronic', 'manual_electronic', true],
            ['REM-GIFT-CARD', 'Remediation gift card', 'gift_card', false],
        ] as [$code, $name, $type, $offlineEligible]) {
            $existing = PaymentMethod::query()->where('code', $code)->first();
            app(SaveLocalSettingsAction::class)->savePaymentMethod([
                'name_ar' => $name,
                'name_en' => $name,
                'code' => $code,
                'type' => $type,
                'requires_evidence' => false,
                'offline_eligible' => $offlineEligible,
                'status' => 'active',
                'policy_notes' => 'Remediation-only Local/Dev payment fixture.',
            ], $existing?->id);
        }

        foreach ([
            'retail_sale' => 'REM-SAL-',
            'inventory_adjustment' => 'REM-ADJ-',
            'stock_transfer' => 'REM-TRF-',
            'purchase_order' => 'REM-PO-',
            'purchase_invoice' => 'REM-PINV-',
            'supplier_return' => 'REM-SR-',
            'inventory_count' => 'REM-CNT-',
            'party_booking' => 'REM-PB-',
            'party_invoice' => 'REM-PI-',
            'party_operating_order' => 'REM-POO-',
            'party_payment_receipt' => 'REM-PPR-',
            'party_final_invoice' => 'REM-PFI-',
            'party_final_receipt' => 'REM-PFR-',
            'quotation' => 'REM-QT-',
        ] as $type => $prefix) {
            $existing = DocumentSequence::query()->where('document_type', $type)->first();
            app(SaveLocalSettingsAction::class)->saveDocumentSequence([
                'document_type' => $type,
                'prefix' => $prefix,
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
                'policy_notes' => 'Remediation-only Local/Dev workflow fixture.',
            ], $existing?->id);
        }

        if (! PosFinancialSettingVersion::query()->where('key', PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION)->exists()) {
            app(SavePosFinancialSettingAction::class)->execute(
                PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
                '5.0000',
                'Remediation-only cash sale prerequisite.',
            );
        }
    }

    /** @param array<string, User> $users */
    private function catalogAndCustomers(Store $sales, Store $party, array $users): void
    {
        $administrator = $users['rem-admin'];
        $catalog = [
            ['REM-TOYS', 'ألعاب معالجة', 'Remediation Toys'],
            ['REM-PARTY-CONSUMABLES', 'مستهلكات حفلات معالجة', 'Remediation Party Consumables'],
        ];
        foreach ($catalog as [$code, $nameAr, $nameEn]) {
            if (! Category::query()->where('code', $code)->exists()) {
                app(SaveCategoryAction::class)->execute(['code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'parent_id' => null, 'status' => 'active', 'sort_order' => 0]);
            }
        }
        $supplier = Supplier::query()->where('code', 'REM-SUPPLIER-001')->first()
            ?? app(SaveSupplierAction::class)->execute(['code' => 'REM-SUPPLIER-001', 'name_ar' => 'مورد المعالجة', 'name_en' => 'Remediation Supplier', 'status' => 'active']);
        unset($supplier);

        // Supplier-return reasons have no domain save Action. This is a
        // master-data prerequisite only; all purchasing documents continue
        // to be created through their existing Actions.
        SupplierReturnReason::query()->firstOrCreate(['code' => 'REM-SUPPLIER-RETURN-REASON'], [
            'label_ar' => 'سبب إرجاع المورد للمعالجة',
            'label_en' => 'Remediation supplier return reason',
            'is_active' => true,
        ]);

        foreach ([
            ['REM-NORMAL-001', 'REM-TOYS', 'لعبة معالجة عادية', 'Remediation Normal Product'],
            ['REM-OPEN-PRICE-001', 'REM-TOYS', 'لعبة معالجة بسعر مفتوح', 'Remediation Open Price Product'],
            ['REM-PARTY-CONSUMABLE-001', 'REM-PARTY-CONSUMABLES', 'مستهلك حفلة معالجة', 'Remediation Party Consumable'],
        ] as [$code, $categoryCode, $nameAr, $nameEn]) {
            if (! Product::query()->where('item_code', $code)->exists()) {
                app(SaveProductAction::class)->execute(['item_code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'product_type' => 'standard', 'unit_of_measure' => 'piece', 'category_id' => Category::query()->where('code', $categoryCode)->value('id'), 'status' => 'active', 'fractional_quantity' => false]);
            }
        }

        foreach ([
            'customer.phone_normalization' => 'digits_only',
            'customer.consent.purpose' => '["service"]',
            'customer.consent.wording' => '{"version":"REM-V1","text":"Remediation fixture consent."}',
            'customer.consent.retention' => '{"days":365}',
            'loyalty.retail_rule' => '{"earn_points_per_currency":"1","redeem_currency_per_point":"0.01"}',
            'loyalty.expiry_policy' => '{"days":30}',
            'loyalty.rounding_policy' => '{"earn":"floor","redeem":"floor"}',
            'loyalty.ledger_integrity' => '{"enabled":true}',
        ] as $key => $value) {
            if (! CustomerPolicySettingVersion::query()->where('key', $key)->exists()) {
                app(SaveCustomerPolicySettingAction::class)->execute($key, $value, 'Remediation fixture prerequisite.');
            }
        }
        app(CreateCustomerAction::class)->execute($administrator, $sales, ['idempotency_key' => 'remediation-customer-001', 'phone' => '01000000991', 'name_ar' => 'عميل معالجة', 'name_en' => 'Remediation Customer', 'email' => 'rem-customer@example.test', 'consents' => [['purpose' => 'service', 'status' => 'granted', 'source' => 'fixture']]]);
        app(CreateCustomerAction::class)->execute($users['rem-admin'], $party, [
            'idempotency_key' => 'remediation-party-customer-001',
            'phone' => '01000000992',
            'name_ar' => 'Party customer',
            'name_en' => 'Remediation Party Customer',
            'email' => 'rem-party-customer@example.test',
            'consents' => [['purpose' => 'service', 'status' => 'granted', 'source' => 'fixture']],
        ]);
    }
}
