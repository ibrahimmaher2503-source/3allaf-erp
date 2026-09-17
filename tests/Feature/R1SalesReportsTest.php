<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Actions\CreateExportJobAction;
use App\Modules\Reporting\Queries\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class R1SalesReportsTest extends TestCase
{
    private User $actor;

    private Store $store;

    private Store $otherStore;

    private Customer $customerA;

    private Customer $customerB;

    private int $groupA;

    private int $feedA;

    private int $feedB;

    private int $feedABag;

    private int $feedAKg;

    private int $feedBBag;

    private int $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        DB::beginTransaction();

        $this->actor = User::query()->where('username', 'admin')->firstOrFail();
        $this->store = Store::query()->where('type', 'selling')->firstOrFail();
        $this->otherStore = Store::query()->whereKeyNot($this->store->id)->firstOrFail();
        $this->cashMethod = (int) DB::table('payment_methods')->where('code', 'CASH')->value('id');
        $this->feedA = (int) DB::table('products')->where('item_code', 'FEED-001')->value('id');
        $this->feedB = (int) DB::table('products')->where('item_code', 'FEED-002')->value('id');
        $this->feedABag = $this->unit($this->feedA, 'BAG');
        $this->feedAKg = $this->unit($this->feedA, 'KG');
        $this->feedBBag = $this->unit($this->feedB, 'BAG');
        $this->groupA = DB::table('customer_groups')->insertGetId([
            'company_id' => $this->store->company_id, 'code' => 'R1-A', 'name_ar' => 'عملاء R1', 'name_en' => 'R1 Customers', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->customerA = $this->customer('A', $this->groupA);
        $this->customerB = $this->customer('B', null);
        $this->seedReconciliationData();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        restore_exception_handler();
        restore_error_handler();
        parent::tearDown();
    }

    public function test_sales_summary_reconciles_cash_credit_discount_return_and_exclusions(): void
    {
        $report = app(SalesReport::class)->summary($this->actor, $this->filters());
        $metrics = $report['metrics'];

        self::assertSame('3570.00', $metrics['gross_before_discount']);
        self::assertSame('20.00', $metrics['discounts']);
        self::assertSame('3550.00', $metrics['gross_sales']);
        self::assertSame('625.00', $metrics['returns']);
        self::assertSame('2925.00', $metrics['net_sales']);
        self::assertSame('2600.00', $metrics['collected_at_sale']);
        self::assertSame('950.00', $metrics['credit_created']);
        self::assertSame(3, $metrics['invoice_count']);
        self::assertSame('975.00', $metrics['average_invoice']);
        self::assertCount(3, $report['invoices']->items());
    }

    public function test_previous_equivalent_period_uses_same_cairo_day_length(): void
    {
        $report = app(SalesReport::class)->summary($this->actor, $this->filters());
        self::assertSame('100.00', $report['previous']['net_sales']);
        self::assertSame(1, $report['previous']['invoice_count']);
        self::assertSame('100.00', $report['previous']['average_invoice']);
        self::assertSame(2825.0, $report['comparison']['net_sales']['percent']);
    }

    public function test_product_and_unit_grain_reconciles_without_join_multiplication(): void
    {
        $report = app(SalesReport::class)->byProduct($this->actor, $this->filters(), true);
        $rows = $report['products']->keyBy(fn (array $row): string => $row['item_code'].':'.$row['unit_code']);

        self::assertCount(3, $rows);
        self::assertSame('2', $rows['FEED-001:BAG']['sold_quantity']);
        self::assertSame('0.5', $rows['FEED-001:BAG']['returned_quantity']);
        self::assertSame('1.5', $rows['FEED-001:BAG']['net_quantity']);
        self::assertSame('1875.00', $rows['FEED-001:BAG']['net_sales']);
        self::assertSame('10', $rows['FEED-001:KG']['sold_quantity']);
        self::assertSame('250.00', $rows['FEED-001:KG']['net_sales']);
        self::assertSame('800.00', $rows['FEED-002:BAG']['net_sales']);
        self::assertSame('2925.00', $report['metrics']['net_sales']);
        self::assertSame('2925.00', number_format($rows->sum(fn (array $row): float => (float) $row['net_sales']), 2, '.', ''));
    }

    public function test_product_drilldown_uses_historical_price_and_matches_selected_row(): void
    {
        $report = app(SalesReport::class)->byProduct($this->actor, [...$this->filters(), 'detail_product_id' => $this->feedA, 'detail_product_unit_id' => $this->feedABag]);
        self::assertNotNull($report['detail']);
        self::assertCount(1, $report['detail']->items());
        $line = $report['detail']->items()[0];
        self::assertSame('1250.0000', (string) $line->entered_unit_price);
        self::assertSame('0.5000000000', (string) $line->returned_quantity);
        self::assertSame('625.00', (string) $line->returned_value);
    }

    public function test_store_and_customer_group_filters_do_not_leak_other_scope(): void
    {
        $group = app(SalesReport::class)->summary($this->actor, [...$this->filters(), 'customer_group_id' => $this->groupA]);
        self::assertSame(2, $group['metrics']['invoice_count']);
        self::assertSame('2125.00', $group['metrics']['net_sales']);

        $other = app(SalesReport::class)->summary($this->actor, ['date_from' => '2026-06-20', 'date_to' => '2026-06-20', 'store_id' => $this->otherStore->id]);
        self::assertSame(1, $other['metrics']['invoice_count']);
        self::assertSame('999.00', $other['metrics']['net_sales']);
    }

    public function test_customer_analysis_is_fixed_to_customer_and_current_balance_is_not_period_revenue(): void
    {
        $analysis = app(SalesReport::class)->customer($this->actor, [$this->customerA->id], $this->filters());
        self::assertSame(2, $analysis['metrics']['invoice_count']);
        self::assertSame('2125.00', $analysis['metrics']['net_sales']);
        self::assertSame('2600.00', $analysis['metrics']['collected_at_sale']);
        self::assertSame('150.00', $analysis['metrics']['credit_created']);
        self::assertCount(2, $analysis['invoices']->items());
        self::assertSame(['FEED-001'], $analysis['products']->pluck('item_code')->unique()->values()->all());
        self::assertSame('999.0000', app(CustomerBalance::class)->for($this->customerA, 'EGP'));

        $receiptDay = app(SalesReport::class)->customer($this->actor, [$this->customerA->id], ['date_from' => '2026-06-21', 'date_to' => '2026-06-21', 'store_id' => $this->store->id]);
        self::assertSame(0, $receiptDay['metrics']['invoice_count']);
        self::assertSame('0.00', $receiptDay['metrics']['net_sales']);
        self::assertSame('999.0000', app(CustomerBalance::class)->for($this->customerA, 'EGP'));
    }

    public function test_financial_report_service_rejects_user_without_report_permissions(): void
    {
        $user = User::query()->create(['name' => 'R1 No Access', 'username' => 'r1-no-access', 'email' => 'r1-no-access@example.test', 'password' => 'password', 'status' => 'active']);
        $this->expectException(AuthorizationException::class);
        app(SalesReport::class)->summary($user, $this->filters());
    }

    public function test_sales_report_exports_generate_csv_xlsx_and_pdf_from_the_same_snapshot(): void
    {
        Storage::fake('local');

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $job = app(CreateExportJobAction::class)->execute($this->actor, [...$this->filters(), 'dataset' => 'sales_by_product'], $format);
            $job->refresh();

            self::assertSame('ready', $job->status);
            self::assertNotNull($job->storage_path);
            Storage::disk('local')->assertExists($job->storage_path);
            self::assertGreaterThan(0, Storage::disk('local')->size($job->storage_path));
        }
    }

    public function test_integrity_queries_find_no_duplicate_or_cross_scope_rows(): void
    {
        $saleIds = DB::table('sales')->where('document_number', 'like', 'R1-%')->select('id');
        $duplicateLines = DB::query()->fromSub(
            DB::table('sale_lines')->whereIn('sale_id', clone $saleIds)->select('sale_id', 'line_number')->groupBy('sale_id', 'line_number')->havingRaw('COUNT(*) > 1'),
            'duplicate_lines',
        )->count();
        $duplicatePayments = DB::query()->fromSub(
            DB::table('sale_payments')->whereIn('sale_id', clone $saleIds)->select('sale_id', 'idempotency_key')->groupBy('sale_id', 'idempotency_key')->havingRaw('COUNT(*) > 1'),
            'duplicate_payments',
        )->count();
        $duplicateReturns = DB::query()->fromSub(
            DB::table('retail_return_lines as line')->join('retail_returns as return', 'return.id', '=', 'line.retail_return_id')->where('return.return_number', 'like', 'R1-%')->select('line.retail_return_id', 'line.sale_line_id', 'line.line_number')->groupBy('line.retail_return_id', 'line.sale_line_id', 'line.line_number')->havingRaw('COUNT(*) > 1'),
            'duplicate_returns',
        )->count();
        $crossCompany = DB::table('sales as sale')->join('stores as store', 'store.id', '=', 'sale.store_id')->join('branches as branch', 'branch.id', '=', 'sale.branch_id')->where('sale.document_number', 'like', 'R1-%')->whereColumn('store.company_id', '!=', 'branch.company_id')->count();
        $crossStore = DB::table('retail_returns as return')->join('sales as sale', 'sale.id', '=', 'return.source_sale_id')->where('return.return_number', 'like', 'R1-%')->whereColumn('return.store_id', '!=', 'sale.store_id')->count();

        self::assertSame(0, $duplicateLines);
        self::assertSame(0, $duplicatePayments);
        self::assertSame(0, $duplicateReturns);
        self::assertSame(0, $crossCompany);
        self::assertSame(0, $crossStore);
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return ['date_from' => '2026-06-20', 'date_to' => '2026-06-20', 'store_id' => $this->store->id];
    }

    private function seedReconciliationData(): void
    {
        $day = CarbonImmutable::create(2026, 6, 20, 12, 0, 0, 'Africa/Cairo')->utc();
        $previous = $day->subDay();
        $sale1 = $this->sale('R1-0001', $this->customerA, $this->store, '2500.00', '0.00', '2500.00', 'paid', $day);
        $line1 = $this->line($sale1, $this->feedA, $this->feedABag, '2', '50', '1250', '2500', '0');
        $this->payment($sale1, '2500');
        $sale2 = $this->sale('R1-0002', $this->customerA, $this->store, '270.00', '20.00', '250.00', 'partial', $day->addHour());
        $this->line($sale2, $this->feedA, $this->feedAKg, '10', '1', '27', '270', '20');
        $this->payment($sale2, '60');
        $this->payment($sale2, '40');
        $sale3 = $this->sale('R1-0003', $this->customerB, $this->store, '800.00', '0.00', '800.00', 'unpaid', $day->addHours(2));
        $this->line($sale3, $this->feedB, $this->feedBBag, '1', '40', '800', '800', '0');

        $this->sale('R1-DRAFT', $this->customerA, $this->store, '999.00', '0.00', '999.00', 'unpaid', $day, 'draft');
        $this->sale('R1-CANCELLED', $this->customerA, $this->store, '999.00', '0.00', '999.00', 'unpaid', $day, 'cancelled');
        $foreign = $this->sale('R1-OTHER-STORE', $this->customerA, $this->otherStore, '999.00', '0.00', '999.00', 'unpaid', $day);
        $this->line($foreign, $this->feedA, $this->feedABag, '1', '50', '999', '999', '0');
        $old = $this->sale('R1-PREVIOUS', $this->customerB, $this->store, '100.00', '0.00', '100.00', 'paid', $previous);
        $this->line($old, $this->feedB, $this->feedBBag, '1', '40', '100', '100', '0');
        $this->payment($old, '100');

        $returnId = DB::table('retail_returns')->insertGetId([
            'branch_id' => $this->store->branch_id, 'store_id' => $this->store->id, 'cashier_id' => $this->actor->id, 'approved_by' => $this->actor->id, 'customer_id' => $this->customerA->id, 'source_sale_id' => $sale1, 'return_number' => 'R1-RET-1', 'status' => 'completed', 'settlement_type' => 'cash', 'reason' => 'R1 partial return', 'eligible_value' => '625', 'subtotal_refund' => '625', 'discount_refund' => '0', 'settlement_value' => '625', 'ar_reduction_value' => '0', 'actual_refund_value' => '625', 'idempotency_key' => 'r1-return-1', 'completed_at' => $day->addHours(3), 'created_at' => $day, 'updated_at' => $day,
        ]);
        DB::table('retail_return_lines')->insert([
            'retail_return_id' => $returnId, 'sale_line_id' => $line1, 'product_id' => $this->feedA, 'return_store_id' => $this->store->id, 'line_number' => 1, 'quantity' => '25', 'unit_value' => '25', 'gross_value' => '625', 'discount_value' => '0', 'eligible_value' => '625', 'tax_value' => '0', 'condition' => 'resalable', 'disposition' => 'restock', 'created_at' => $day, 'updated_at' => $day,
        ]);

        $receiptId = DB::table('customer_receipts')->insertGetId([
            'public_id' => (string) Str::uuid(), 'customer_id' => $this->customerA->id, 'store_id' => $this->store->id, 'payment_method_id' => $this->cashMethod, 'receipt_date' => '2026-06-21', 'currency_code' => 'EGP', 'amount' => '150', 'status' => 'approved', 'created_by' => $this->actor->id, 'approved_by' => $this->actor->id, 'approved_at' => $day->addDay(), 'idempotency_key' => 'r1-later-receipt', 'payload_hash' => hash('sha256', 'r1-later-receipt'), 'created_at' => $day, 'updated_at' => $day,
        ]);
        DB::table('customer_receipt_allocations')->insert(['customer_receipt_id' => $receiptId, 'sale_id' => $sale2, 'amount' => '150', 'created_at' => $day->addDay()]);
    }

    private function sale(string $document, Customer $customer, Store $store, string $subtotal, string $discount, string $payable, string $paymentStatus, CarbonImmutable $approvedAt, string $status = 'approved'): int
    {
        $paid = $paymentStatus === 'paid' ? $payable : ($paymentStatus === 'partial' ? '100' : '0');

        return DB::table('sales')->insertGetId([
            'branch_id' => $store->branch_id, 'store_id' => $store->id, 'cashier_id' => $this->actor->id, 'customer_id' => $customer->id, 'document_number' => $document, 'status' => $status, 'idempotency_key' => strtolower($document), 'subtotal' => $subtotal, 'discount_total' => $discount, 'tax_total' => '0', 'total' => $payable, 'paid_total' => $paid, 'outstanding_amount' => bcsub($payable, $paid, 4), 'payment_status' => $paymentStatus, 'payable_total' => $payable, 'currency_code' => 'EGP', 'approved_at' => $status === 'approved' ? $approvedAt : null, 'created_at' => $approvedAt, 'updated_at' => $approvedAt,
        ]);
    }

    private function line(int $saleId, int $productId, int $unitId, string $enteredQuantity, string $factor, string $enteredPrice, string $gross, string $discount): int
    {
        $product = DB::table('products')->find($productId);

        return DB::table('sale_lines')->insertGetId([
            'sale_id' => $saleId, 'product_id' => $productId, 'product_unit_id' => $unitId, 'entered_quantity' => $enteredQuantity, 'conversion_factor_snapshot' => $factor, 'entered_unit_price' => $enteredPrice, 'line_number' => 1, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => bcmul($enteredQuantity, $factor, 6), 'unit_price' => bcdiv($enteredPrice, $factor, 4), 'gross_amount' => $gross, 'discount_amount' => $discount, 'allocated_invoice_discount' => '0', 'net_amount' => bcsub($gross, $discount, 2), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payment(int $saleId, string $amount): void
    {
        DB::table('sale_payments')->insert([
            'sale_id' => $saleId, 'payment_method_id' => $this->cashMethod, 'method_code' => 'CASH', 'method_type' => 'cash', 'amount' => $amount, 'tendered_amount' => $amount, 'change_amount' => '0', 'idempotency_key' => 'r1-payment-'.Str::uuid(), 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function customer(string $suffix, ?int $group): Customer
    {
        return Customer::query()->create([
            'phone_normalized' => '010000001'.($suffix === 'A' ? '01' : '02'), 'phone_display' => '010000001'.($suffix === 'A' ? '01' : '02'), 'name_ar' => 'عميل '.$suffix, 'name_en' => 'Customer '.$suffix, 'status' => 'active', 'created_by' => $this->actor->id, 'created_branch_id' => $this->store->branch_id, 'created_store_id' => $this->store->id, 'customer_group_id' => $group, 'customer_type' => 'both', 'idempotency_key' => 'r1-customer-'.strtolower($suffix),
        ]);
    }

    private function unit(int $productId, string $code): int
    {
        return (int) DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('product_units.product_id', $productId)->where('units.code', $code)->value('product_units.id');
    }
}
