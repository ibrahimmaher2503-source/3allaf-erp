<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\CustomerProductPrice;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\OpenPricePolicy;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Services\PosCalculationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class P09FeedPricingTest extends TestCase
{
    private Product $product;

    private Store $store;

    private Customer $retail;

    private Customer $trader;

    private Customer $special;

    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        DB::beginTransaction();
        $this->product = Product::query()->where('item_code', 'FEED-001')->firstOrFail();
        $this->store = Store::query()->where('type', 'selling')->firstOrFail();
        $customers = Customer::query()->where('idempotency_key', 'like', 'feed-customer-%')->orderBy('id')->take(3)->get();
        [$this->retail, $this->trader, $this->special] = $customers;
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        restore_exception_handler();
        restore_error_handler();
        parent::tearDown();
    }

    public function test_authoritative_precedence_and_unit_prices(): void
    {
        $resolver = app(EffectivePriceResolver::class);
        $bag = $this->unit('BAG');
        $kg = $this->unit('KG');

        self::assertSame('1250.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag)->amount);
        self::assertSame('1250.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag, customerId: $this->retail->id)->amount);
        self::assertSame('1210.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag, customerId: $this->trader->id)->amount);
        $special = $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag, customerId: $this->special->id);
        self::assertSame('1190.0000', (string) $special->amount);
        self::assertSame('customer_special', $special->price_source);
        self::assertSame('27.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $kg)->amount);
        self::assertNotSame('25.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $kg)->amount);
        self::assertSame('50.000000', (string) $this->product->productUnits()->findOrFail($bag)->conversion_factor);
    }

    public function test_special_price_is_scoped_and_expiry_falls_back(): void
    {
        $resolver = app(EffectivePriceResolver::class);
        $bag = $this->unit('BAG');
        $productB = Product::query()->where('item_code', 'FEED-002')->firstOrFail();
        $bagB = (int) $productB->productUnits()->whereHas('unit', fn ($q) => $q->where('code', 'BAG'))->value('id');

        self::assertSame('790.0000', (string) $resolver->resolve($productB->id, $this->store->id, productUnitId: $bagB, customerId: $this->special->id)->amount);
        CustomerProductPrice::query()->where('customer_id', $this->special->id)->update(['status' => 'expired', 'active_key' => null, 'effective_to' => today()->subDay()]);
        self::assertSame('1210.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag, customerId: $this->special->id)->amount);
    }

    public function test_cross_company_customer_and_list_cannot_change_store_price(): void
    {
        $company = DB::table('companies')->insertGetId(['code' => 'P09-X', 'name_ar' => 'شركة أخرى', 'name_en' => 'Other', 'currency_code' => 'EGP', 'created_at' => now(), 'updated_at' => now()]);
        $list = PriceList::query()->create(['company_id' => $company, 'list_number' => 0, 'code' => 'X-RETAIL', 'name_ar' => 'أخرى', 'name_en' => 'Other', 'percentage_increase' => '0', 'status' => 'active']);
        $this->trader->mutateMaster(['price_list_id' => $list->id]);
        $price = app(EffectivePriceResolver::class)->resolve($this->product->id, $this->store->id, productUnitId: $this->unit('BAG'), customerId: $this->trader->id);
        self::assertSame('1250.0000', (string) $price->amount);
        self::assertSame('outlet_price_list', $price->price_source);
    }

    public function test_minimum_policy_catches_direct_and_discount_bypass(): void
    {
        $policy = app(OpenPricePolicy::class);
        self::assertTrue($policy->validate('1250', '1170', '1170', null, true, 'normal')['allowed']);
        self::assertFalse($policy->validate('1250', '1150', '1170', null, true, 'special')['allowed']);
        self::assertTrue($policy->validate('1250', '1150', '1170', null, true, 'عرض خاص', allowBelowMinimum: true)['allowed']);
        self::assertFalse($policy->validate('1250', '1150', '1170', null, true, '', allowBelowMinimum: true)['allowed']);

        $totals = app(PosCalculationService::class)->calculate([['quantity' => '1', 'unit_price' => '1210', 'discount_amount' => '50']]);
        self::assertSame('1160.00', $totals['lines'][0]['net_amount']);
        self::assertLessThan(0, bccomp($totals['lines'][0]['net_amount'], '1170', 4));
    }

    public function test_customer_switch_reprices_without_leaking_special_price_and_history_stays_fixed(): void
    {
        $resolver = app(EffectivePriceResolver::class);
        $bag = $this->unit('BAG');
        self::assertSame('1190.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag, customerId: $this->special->id)->amount);
        self::assertSame('1210.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag, customerId: $this->trader->id)->amount);
        self::assertSame('1250.0000', (string) $resolver->resolve($this->product->id, $this->store->id, productUnitId: $bag)->amount);

        $historic = DB::table('sale_lines')->where('product_id', $this->product->id)->first();
        $before = $historic->entered_unit_price;
        DB::table('product_price_overrides')->where('product_id', $this->product->id)->where('product_unit_id', $bag)->where('price_list_id', $this->trader->price_list_id)->update(['amount' => '1200']);
        self::assertSame((string) $before, (string) DB::table('sale_lines')->where('id', $historic->id)->value('entered_unit_price'));
    }

    public function test_server_rejects_unauthorized_below_minimum_and_audits_authorized_override(): void
    {
        $bag = $this->unit('BAG');
        $unauthorized = $this->cashier('plain');
        try {
            app(RetailSaleAction::class)->create($unauthorized, $this->store, [['product_id' => $this->product->id, 'product_unit_id' => $bag, 'quantity' => '1', 'open_price_amount' => '1150', 'open_price_reason' => 'عرض خاص']], (string) str()->uuid(), true, customer: $this->trader);
            self::fail('Below-minimum sale should have been rejected.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('outside', strtolower($exception->getMessage()));
        }

        $authorized = $this->cashier('manager', true);
        $sale = app(RetailSaleAction::class)->create($authorized, $this->store, [['product_id' => $this->product->id, 'product_unit_id' => $bag, 'quantity' => '1', 'open_price_amount' => '1150', 'open_price_reason' => 'عرض خاص للعميل']], (string) str()->uuid(), true, customer: $this->trader);
        $line = $sale->lines()->firstOrFail();
        self::assertSame('1150.0000', (string) $line->entered_unit_price);
        self::assertSame('1170.0000', (string) $line->minimum_price_snapshot);
        self::assertTrue(DB::table('audit_logs')->where('event', 'sale_below_minimum_authorized')->where('source_id', (string) $line->id)->exists());
    }

    public function test_discount_cannot_bypass_minimum_without_stronger_permission(): void
    {
        DB::table('pos_financial_setting_versions')->insert(['key' => 'pos.discount_approval_limit_percent', 'value' => '100', 'value_type' => 'decimal', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $cashier = $this->cashier('discount', false, true);
        $line = ['product_id' => $this->product->id, 'product_unit_id' => $this->unit('BAG'), 'quantity' => '1', 'discount_amount' => '50', 'discount_type' => 'line', 'discount_reason' => 'خصم تفاوضي'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('below the minimum');
        app(RetailSaleAction::class)->create($cashier, $this->store, [$line], (string) str()->uuid(), true, customer: $this->trader);
    }

    public function test_open_order_is_repriced_on_resume_and_original_snapshot_is_explicit(): void
    {
        $cashier = $this->cashier('resume');
        $bag = $this->unit('BAG');
        $sale = app(RetailSaleAction::class)->create($cashier, $this->store, [['product_id' => $this->product->id, 'product_unit_id' => $bag, 'quantity' => '1']], (string) str()->uuid(), true, customer: $this->trader);
        self::assertSame('1210.0000', (string) $sale->lines()->firstOrFail()->entered_unit_price);
        DB::table('product_price_overrides')->where('product_id', $this->product->id)->where('product_unit_id', $bag)->where('price_list_id', $this->trader->price_list_id)->update(['amount' => '1200']);
        $preview = app(RetailSaleAction::class)->suspendedResumePreview($cashier, $sale);
        self::assertSame('1200.0000', (string) $preview['lines'][0]['entered_unit_price']);
        self::assertSame('1210.0000', (string) $sale->lines()->firstOrFail()->entered_unit_price);
    }

    private function unit(string $code): int
    {
        return (int) $this->product->productUnits()->whereHas('unit', fn ($query) => $query->where('code', $code))->value('id');
    }

    private function cashier(string $suffix, bool $belowMinimum = false, bool $discount = false): User
    {
        $now = now();
        $user = User::query()->create(['name' => 'P09 '.$suffix, 'username' => 'p09-'.$suffix, 'email' => 'p09-'.$suffix.'@example.test', 'password' => 'password', 'status' => 'active']);
        $role = DB::table('roles')->insertGetId(['code' => 'p09-'.$suffix, 'name_ar' => 'اختبار', 'name_en' => 'Test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $codes = ['pos_sales.create', 'pos_sales.open_price'];
        if ($belowMinimum) {
            $codes[] = 'pos_sales.override_below_minimum';
        }
        if ($discount) {
            $codes[] = 'pos_sales.apply_discount';
        }
        foreach (DB::table('permissions')->whereIn('code', $codes)->pluck('id') as $permission) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
        DB::table('role_user')->insert(['role_id' => $role, 'user_id' => $user->id]);
        DB::table('user_store_scopes')->insert(['user_id' => $user->id, 'store_id' => $this->store->id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $drawer = DB::table('cash_drawers')->insertGetId(['company_id' => $this->store->company_id, 'branch_id' => $this->store->branch_id, 'store_id' => $this->store->id, 'assigned_user_id' => $user->id, 'code' => 'P09-'.strtoupper($suffix), 'name_ar' => 'درج اختبار', 'name_en' => 'Test Drawer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $shift = DB::table('pos_shifts')->insertGetId(['branch_id' => $this->store->branch_id, 'store_id' => $this->store->id, 'cash_drawer_id' => $drawer, 'cashier_id' => $user->id, 'opened_by' => $user->id, 'status' => 'open', 'opening_cash' => '0', 'currency_code' => 'EGP', 'idempotency_key' => 'p09-'.$suffix, 'opened_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('active_pos_shift_assignments')->insert(['shift_id' => $shift, 'cashier_id' => $user->id, 'cash_drawer_id' => $drawer, 'created_at' => $now, 'updated_at' => $now]);

        return $user;
    }
}
