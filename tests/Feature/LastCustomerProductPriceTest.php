<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Retail\Queries\LastCustomerProductPrice;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

final class LastCustomerProductPriceTest extends TestCase
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

    public function test_latest_approved_sale_wins_chronologically(): void
    {
        $now = now();
        $user = DB::table('users')->insertGetId(['name'=>'Price Test','email'=>'last-price@test.invalid','password'=>'x','created_at'=>$now,'updated_at'=>$now]);
        $company = DB::table('companies')->insertGetId(['code'=>'LP','name_ar'=>'اختبار','name_en'=>'Test','currency_code'=>'EGP','created_at'=>$now,'updated_at'=>$now]);
        $branch = DB::table('branches')->insertGetId(['company_id'=>$company,'code'=>'LP-B','name_ar'=>'فرع','name_en'=>'Branch','created_at'=>$now,'updated_at'=>$now]);
        $store = DB::table('stores')->insertGetId(['company_id'=>$company,'branch_id'=>$branch,'code'=>'LP-S','type'=>'selling','name_ar'=>'مخزن','name_en'=>'Store','created_at'=>$now,'updated_at'=>$now]);
        $category = DB::table('categories')->insertGetId(['code'=>'LP-C','name_ar'=>'أعلاف','name_en'=>'Feed','created_at'=>$now,'updated_at'=>$now]);
        $product = DB::table('products')->insertGetId(['item_code'=>'LP-P','name_ar'=>'علف','name_en'=>'Feed','category_id'=>$category,'created_at'=>$now,'updated_at'=>$now]);
        $customer = DB::table('customers')->insertGetId(['public_id'=>(string) str()->uuid(),'name_ar'=>'عميل','name_en'=>'Customer','created_by'=>$user,'created_branch_id'=>$branch,'created_store_id'=>$store,'idempotency_key'=>(string) str()->uuid(),'created_at'=>$now,'updated_at'=>$now]);
        $unit = DB::table('units')->where('code', 'BAG')->value('id');
        $productUnit = DB::table('product_units')->insertGetId(['product_id'=>$product,'unit_id'=>$unit,'conversion_factor'=>50,'is_sale_unit'=>true,'created_at'=>$now,'updated_at'=>$now]);

        foreach ([['2026-09-01 10:00:00','840'], ['2026-09-10 10:00:00','845']] as $index => [$date, $price]) {
            $sale = DB::table('sales')->insertGetId(['branch_id'=>$branch,'store_id'=>$store,'cashier_id'=>$user,'customer_id'=>$customer,'document_number'=>'LP-'.$index,'status'=>'approved','idempotency_key'=>(string) str()->uuid(),'total'=>$price,'payable_total'=>$price,'currency_code'=>'EGP','approved_at'=>$date,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('sale_lines')->insert(['sale_id'=>$sale,'product_id'=>$product,'product_unit_id'=>$productUnit,'entered_quantity'=>1,'conversion_factor_snapshot'=>50,'entered_unit_price'=>$price,'line_number'=>1,'item_code'=>'LP-P','name_ar'=>'علف','name_en'=>'Feed','quantity'=>50,'unit_price'=>bcdiv($price,'50',4),'gross_amount'=>$price,'net_amount'=>$price,'created_at'=>$now,'updated_at'=>$now]);
        }

        $latest = app(LastCustomerProductPrice::class)->for($customer, $product);
        self::assertSame('845.0000', $latest['price']);
        self::assertSame('BAG', $latest['unit_code']);
        self::assertSame('2026-09-10 10:00:00', $latest['date']);
    }
}
