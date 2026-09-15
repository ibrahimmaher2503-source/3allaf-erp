<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Factories\CustomerFactory;
use Database\Factories\CustomerReceiptFactory;
use Database\Factories\ExpenseFactory;
use Database\Factories\InventoryBatchFactory;
use Database\Factories\ProductFactory;
use Database\Factories\PurchaseInvoiceFactory;
use Database\Factories\PurchaseInvoiceLineFactory;
use Database\Factories\SaleFactory;
use Database\Factories\SaleLineFactory;
use Database\Factories\SupplierFactory;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

final class FeedStoreFactoriesSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        restore_exception_handler();
        restore_error_handler();
        parent::tearDown();
    }

    public function test_feed_store_factory_states_create_compatible_models(): void
    {
        self::assertSame('feed', ProductFactory::new()->feed()->create()->feed_kind);
        self::assertSame('raw_material', ProductFactory::new()->rawMaterial()->create()->feed_kind);
        self::assertSame('additive', ProductFactory::new()->additive()->create()->feed_kind);
        self::assertTrue(ProductFactory::new()->feed()->withBatchTracking()->create()->track_batches);
        self::assertSame('credit', SupplierFactory::new()->credit()->create()->payment_policy);
        self::assertSame('cash', CustomerFactory::new()->cash()->create()->customer_type);
        self::assertSame('credit', CustomerFactory::new()->credit()->create()->customer_type);
        self::assertNotNull(InventoryBatchFactory::new()->create()->product_id);
        self::assertSame('draft', PurchaseInvoiceFactory::new()->create()->status);
        self::assertSame('1000.000000', PurchaseInvoiceLineFactory::new()->create()->quantity);
        self::assertSame('draft', SaleFactory::new()->create()->status);
        self::assertSame('paid', SaleFactory::new()->paid()->create()->payment_status);
        self::assertSame('partial', SaleFactory::new()->partiallyPaid()->create()->payment_status);
        self::assertSame('unpaid', SaleFactory::new()->credit()->create()->payment_status);
        self::assertSame('2.000000', SaleLineFactory::new()->create()->quantity);
        self::assertSame('500.0000', CustomerReceiptFactory::new()->create()->amount);
        self::assertSame('5000.0000', SupplierPaymentFactory::new()->create()->amount);
        self::assertSame('300.0000', ExpenseFactory::new()->create()->amount);
    }
}
