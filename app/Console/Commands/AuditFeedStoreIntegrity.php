<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Support\CashAccountBalance;
use App\Modules\Retail\Queries\LastCustomerProductPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditFeedStoreIntegrity extends Command
{
    protected $signature = 'feed-store:audit';
    protected $description = 'Validate seeded feed-store units, inventory, AR, AP, cash, and relational integrity.';

    public function handle(): int
    {
        $firstSale = DB::table('sales')->where('document_number', 'FEED-S-00001')->first();
        $firstPurchase = DB::table('purchase_invoices')->where('invoice_number', 'FEED-PI-0001')->first();
        $firstLine = $firstSale ? DB::table('sale_lines')->where('sale_id', $firstSale->id)->first() : null;
        $cashAccount = CashAccount::query()->where('code', 'SHOP-TREASURY')->first();
        $latestPrice = $firstSale && $firstLine ? app(LastCustomerProductPrice::class)->for((int) $firstSale->customer_id, (int) $firstLine->product_id) : null;
        $checks = [
            'products' => DB::table('products')->where('item_code', 'like', 'FEED-%')->count() === 30,
            'product_units' => DB::table('product_units')->whereIn('product_id', DB::table('products')->where('item_code', 'like', 'FEED-%')->select('id'))->count() === 90,
            'suppliers' => DB::table('suppliers')->where('code', 'like', 'FEED-SUP-%')->count() === 10,
            'customers' => DB::table('customers')->where('idempotency_key', 'like', 'feed-customer-%')->count() === 40,
            'purchases' => DB::table('purchase_invoices')->where('invoice_number', 'like', 'FEED-PI-%')->count() === 36,
            'sales' => DB::table('sales')->where('document_number', 'like', 'FEED-S-%')->count() === 150,
            'stock_movements' => DB::table('stock_movements')->where('idempotency_key', 'like', 'feed-%')->count() === 216,
            'receipts' => DB::table('customer_receipts')->where('idempotency_key', 'like', 'feed-receipt-%')->count() === 1,
            'supplier_payments' => DB::table('supplier_payments')->where('idempotency_key', 'like', 'feed-supplier-payment-%')->count() === 2,
            'expenses' => DB::table('expenses')->where('idempotency_key', 'like', 'feed-expense-%')->count() === 40,
            'batches' => DB::table('inventory_batches')->count() === 6,
            'ton_kg' => DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('units.code', 'TON')->where('conversion_factor', '1000')->count() === 30,
            'bag_50' => DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('units.code', 'BAG')->where('conversion_factor', '50')->exists(),
            'bag_25' => DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('units.code', 'BAG')->where('conversion_factor', '25')->exists(),
            'last_customer_price' => $latestPrice !== null && $latestPrice['sale_id'] > 0 && $latestPrice['unit_code'] === 'BAG',
            'stock_matches_movements' => $this->stockMismatchCount() === 0,
            'negative_stock' => DB::table('stock_balances')->where('on_hand', '<', 0)->doesntExist(),
            'customer_ar_900' => $firstSale !== null && bccomp($this->saleOutstanding((int) $firstSale->id), '900.0000', 4) === 0,
            'supplier_ap_7500' => $firstPurchase !== null && bccomp($this->purchaseOutstanding((int) $firstPurchase->id), '7500.0000', 4) === 0,
            'cash_is_sum' => $cashAccount !== null && bccomp(app(CashAccountBalance::class)->for($cashAccount), bcadd((string) DB::table('cash_transactions')->where('cash_account_id', $cashAccount->id)->sum('amount'), '0', 4), 4) === 0,
            'orphans' => $this->orphanCount() === 0,
            'float_columns' => DB::table('information_schema.columns')->where('table_schema', DB::getDatabaseName())->whereIn('data_type', ['float', 'double', 'real'])->count() === 0,
        ];

        foreach ($checks as $name => $passed) {
            $this->line(($passed ? 'PASS ' : 'FAIL ').$name);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function stockMismatchCount(): int
    {
        return count(DB::select("SELECT sb.id FROM stock_balances sb LEFT JOIN (SELECT product_id, store_id, SUM(quantity) quantity FROM stock_movements GROUP BY product_id, store_id) sm ON sm.product_id=sb.product_id AND sm.store_id=sb.store_id WHERE ABS(sb.on_hand-COALESCE(sm.quantity,0)) > 0.000001"));
    }

    private function orphanCount(): int
    {
        $queries = [
            'SELECT pu.id FROM product_units pu LEFT JOIN products p ON p.id=pu.product_id LEFT JOIN units u ON u.id=pu.unit_id WHERE p.id IS NULL OR u.id IS NULL',
            'SELECT a.id FROM customer_receipt_allocations a LEFT JOIN customer_receipts r ON r.id=a.customer_receipt_id LEFT JOIN sales s ON s.id=a.sale_id WHERE r.id IS NULL OR s.id IS NULL',
            'SELECT a.id FROM supplier_payment_allocations a LEFT JOIN supplier_payments p ON p.id=a.supplier_payment_id LEFT JOIN purchase_invoices i ON i.id=a.purchase_invoice_id WHERE p.id IS NULL OR i.id IS NULL',
            'SELECT sm.id FROM stock_movements sm LEFT JOIN products p ON p.id=sm.product_id LEFT JOIN stores s ON s.id=sm.store_id WHERE p.id IS NULL OR s.id IS NULL',
        ];
        return array_sum(array_map(fn (string $sql): int => count(DB::select($sql)), $queries));
    }

    private function saleOutstanding(int $saleId): string
    {
        $total = (string) DB::table('sales')->where('id', $saleId)->value('payable_total');
        $paid = (string) DB::table('sale_payments')->where('sale_id', $saleId)->sum('amount');
        $receipts = (string) DB::table('customer_receipt_allocations')->where('sale_id', $saleId)->sum('amount');
        return bcsub(bcsub($total, $paid, 4), $receipts, 4);
    }

    private function purchaseOutstanding(int $invoiceId): string
    {
        $total = (string) DB::table('purchase_invoices')->where('id', $invoiceId)->value('total_amount');
        $paid = (string) DB::table('supplier_payment_allocations')->where('purchase_invoice_id', $invoiceId)->sum('amount');
        return bcsub($total, $paid, 4);
    }
}
