<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class DemoFeedStoreSeeder extends Seeder
{
    private const PRODUCT_NAMES = [
        'علف تسمين مواشي 18%', 'علف تسمين مواشي 16%', 'علف حلاب 18%', 'علف حلاب 20%', 'علف بادئ عجول',
        'علف نامي عجول', 'علف دواجن بادئ 23%', 'علف دواجن نامي 21%', 'علف دواجن ناهي 19%', 'علف بياض',
        'علف أرانب تسمين', 'علف أرانب أمهات', 'ذرة صفراء', 'ذرة مجروشة', 'ردة ناعمة', 'ردة خشنة',
        'كسب فول صويا 44%', 'كسب فول صويا 46%', 'جلوتين', 'مولاس', 'حجر جيري أعلاف', 'ملح أعلاف',
        'مخلوط أملاح معدنية', 'مخلوط فيتامينات', 'خميرة أعلاف', 'ثنائي فوسفات الكالسيوم',
        'بيكربونات صوديوم أعلاف', 'مركز بروتين مواشي', 'مركز بروتين دواجن', 'إضافة محسن هضم',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('DemoFeedStoreSeeder is local/testing only.');
        }

        if (DB::table('products')->where('item_code', 'FEED-001')->exists()) {
            return;
        }

        DB::transaction(fn () => $this->seed());
    }

    private function seed(): void
    {
        $now = now();
        $company = Company::query()->where('status', 'active')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('status', 'active')->firstOrFail();
        $salesStore = Store::query()->where('branch_id', $branch->id)->where('type', 'selling')->firstOrFail();
        $warehouse = Store::query()->where('branch_id', $branch->id)->where('type', 'warehouse')->firstOrFail();
        $userId = (int) DB::table('users')->orderBy('id')->value('id');
        $cashMethod = PaymentMethod::query()->where('type', 'cash')->firstOrFail();
        $drawer = CashDrawer::query()->where('store_id', $salesStore->id)->firstOrFail();
        Auth::loginUsingId($userId);

        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $salesStore->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $userId, 'opened_by' => $userId, 'status' => 'open', 'opening_cash' => '5000.00',
            'currency_code' => 'EGP', 'idempotency_key' => 'feed-demo-shift', 'opened_at' => $now,
        ]);

        $unitIds = $this->units($now);
        $categoryIds = $this->categories($userId, $now);
        $supplierIds = $this->suppliers($cashMethod->id, $userId, $now);
        [$products, $productUnits, $batches] = $this->products($categoryIds, $supplierIds, $unitIds, $userId, $now);
        $customerIds = $this->customers($userId, $branch->id, $salesStore->id, $now);
        $cashAccountId = $this->cashControl($company->id, $cashMethod->id, $userId, $now);

        $this->openingStock($products, $batches, $salesStore->id);
        $purchaseIds = $this->purchases($products, $productUnits, $batches, $supplierIds, $warehouse->id, $userId, $now);
        $saleIds = $this->sales($products, $productUnits, $batches, $customerIds, $branch->id, $salesStore->id, $drawer->id, $shift->id, $cashMethod->id, $userId, $now);
        $this->receiptsAndPayments($saleIds, $purchaseIds, $cashMethod->id, $cashAccountId, $userId, $now);
        $this->expenses($company->id, $cashMethod->id, $cashAccountId, $userId, $now);
        Auth::logout();
    }

    /** @return array<string,int> */
    private function units($now): array
    {
        $rows = [
            'KG' => ['كجم', 'Kilogram', 'weight', 3], 'BAG' => ['شيكارة', 'Bag', 'count', 3],
            'TON' => ['طن', 'Ton', 'weight', 3], 'PIECE' => ['قطعة', 'Piece', 'count', 0],
            'LITER' => ['لتر', 'Liter', 'volume', 3],
        ];
        foreach ($rows as $code => [$ar, $en, $dimension, $places]) {
            DB::table('units')->updateOrInsert(['code' => $code], ['name_ar' => $ar, 'name_en' => $en, 'dimension' => $dimension, 'decimal_places' => $places, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('units')->whereIn('code', array_keys($rows))->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    private function categories(int $userId, $now): array
    {
        $names = ['أعلاف مواشي', 'أعلاف دواجن', 'أعلاف أرانب', 'خامات أعلاف', 'ذرة وحبوب', 'نخالة وردة', 'مركزات', 'إضافات أعلاف', 'أملاح وفيتامينات'];
        foreach ($names as $i => $name) {
            DB::table('categories')->insert(['code' => sprintf('FEED-CAT-%02d', $i + 1), 'name_ar' => $name, 'name_en' => 'Feed category '.($i + 1), 'status' => 'active', 'sort_order' => $i + 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('categories')->where('code', 'like', 'FEED-CAT-%')->orderBy('code')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    private function suppliers(int $paymentMethodId, int $userId, $now): array
    {
        $names = ['الوادي للأعلاف', 'النيل لتجارة الأعلاف والحبوب', 'الأمل للأعلاف', 'الفجر للحبوب', 'مخازن الدلتا', 'الصفوة للأعلاف', 'الإخلاص للحبوب', 'البركة للأعلاف', 'أبو النور للخامات', 'الرواد للأعلاف'];
        $areas = ['الجيزة', 'البدرشين', 'العياط', 'أبو النمرس', 'الصف', 'الحوامدية', 'القليوبية', 'الشرقية', 'المنوفية', 'بني سويف'];
        foreach ($names as $i => $name) {
            $policy = $i === 0 ? 'both' : ['cash', 'credit', 'both'][$i % 3];
            DB::table('suppliers')->insert(['code' => sprintf('FEED-SUP-%02d', $i + 1), 'name_ar' => $name, 'name_en' => 'Synthetic Feed Supplier '.($i + 1), 'phone' => '010'.sprintf('%08d', 10000000 + $i), 'payment_policy' => $policy, 'credit_days' => $policy === 'cash' ? null : 30, 'credit_limit' => $policy === 'cash' ? null : (string) (25000 + $i * 5000), 'commercial_registration' => 'SYN-CR-'.sprintf('%05d', $i + 1), 'address' => $areas[$i], 'notes' => 'بيانات تجريبية غير مرتبطة بجهة حقيقية', 'status' => 'active', 'preferred_payment_method_id' => $paymentMethodId, 'settlement_method' => 'cash', 'lock_version' => 0, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('suppliers')->where('code', 'like', 'FEED-SUP-%')->orderBy('code')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array{list<Product>,array<int,array<string,ProductUnit>>,array<int,InventoryBatch>} */
    private function products(array $categoryIds, array $supplierIds, array $unitIds, int $userId, $now): array
    {
        $products = [];
        $units = [];
        $batches = [];
        foreach (self::PRODUCT_NAMES as $i => $name) {
            $kind = $i < 12 ? 'feed' : ($i < 22 ? 'raw_material' : 'additive');
            $animal = $i < 6 ? 'cattle' : ($i < 10 ? 'poultry' : ($i < 12 ? 'rabbit' : 'other'));
            $bagFactor = $kind === 'additive' ? 25 : ($i % 4 === 0 ? 40 : 50);
            $tracked = $i < 2;
            $product = Product::query()->create(['item_code' => sprintf('FEED-%03d', $i + 1), 'name_ar' => $name, 'name_en' => 'Synthetic '.$name, 'model_number' => sprintf('FD-%03d', $i + 1), 'product_type' => 'standard', 'feed_kind' => $kind, 'animal_type' => $animal, 'protein_percentage' => preg_match('/(\d+)%/', $name, $m) ? $m[1] : null, 'track_batches' => $tracked, 'track_expiry' => $tracked, 'unit_of_measure' => 'KG', 'category_id' => $categoryIds[$i % count($categoryIds)], 'status' => 'active', 'barcode_mode' => 'none', 'lock_version' => 0, 'average_cost' => (string) (12 + $i), 'sale_price' => (string) ((14 + $i) * $bagFactor), 'reorder_threshold' => '100.000', 'fractional_quantity' => true]);
            $products[] = $product;
            foreach ([['KG', '1', true, true, true], ['BAG', (string) $bagFactor, false, true, true], ['TON', '1000', false, true, true]] as [$code,$factor,$base,$purchase,$sale]) {
                $units[$product->id][$code] = ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $unitIds[$code], 'conversion_factor' => $factor, 'is_base_unit' => $base, 'is_purchase_unit' => $purchase, 'is_sale_unit' => $sale]);
            }
            foreach (array_slice($supplierIds, $i % 7, 2 + ($i % 3)) as $j => $supplierId) {
                DB::table('product_suppliers')->insert(['product_id' => $product->id, 'supplier_id' => $supplierId, 'supplier_item_code' => sprintf('S%02d-P%03d', $supplierId, $i + 1), 'is_preferred' => $j === 0, 'last_purchase_price' => (string) (12000 + $i * 170), 'last_purchase_date' => now()->subDays($i % 30)->toDateString(), 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($tracked) {
                foreach ([['LOT-260801-A', '2026-08-01', '2027-02-01'], ['LOT-260820-B', '2026-08-20', '2027-02-20'], ['LOT-260905-C', '2026-09-05', '2027-03-05']] as [$number,$production,$expiry]) {
                    $batch = InventoryBatch::query()->create(['product_id' => $product->id, 'batch_number' => $number, 'production_date' => $production, 'expiry_date' => $expiry, 'status' => 'active']);
                    $batches[$product->id] ??= $batch;
                }
            }
        }

        return [$products, $units, $batches];
    }

    /** @return list<int> */
    private function customers(int $userId, int $branchId, int $storeId, $now): array
    {
        $first = ['محمد', 'أحمد', 'سيد', 'محمود', 'عبدالرحمن', 'مصطفى', 'حسن', 'إبراهيم', 'علي', 'رمضان'];
        $last = ['علي حسن', 'فتحي محمود', 'عبدالعال', 'رمضان', 'إبراهيم', 'السيد', 'شوقي', 'عطية', 'حمدي', 'سالم'];
        for ($i = 0; $i < 40; $i++) {
            $type = $i === 0 ? 'both' : ['cash', 'credit', 'both'][$i % 3];
            DB::table('customers')->insert(['public_id' => (string) Str::uuid(), 'phone_normalized' => '011'.sprintf('%08d', 20000000 + $i), 'phone_display' => '011'.sprintf('%08d', 20000000 + $i), 'name_ar' => $i >= 32 ? 'مزرعة تجريبية '.($i - 31) : $first[$i % 10].' '.$last[(int) floor($i / 4) % 10], 'name_en' => 'Synthetic Customer '.($i + 1), 'status' => 'active', 'created_by' => $userId, 'updated_by' => $userId, 'created_branch_id' => $branchId, 'created_store_id' => $storeId, 'customer_type' => $type, 'credit_limit' => $type === 'cash' ? null : (string) ([5000, 10000, 15000, 25000, 50000][$i % 5]), 'notes' => 'عميل تجريبي', 'idempotency_key' => 'feed-customer-'.($i + 1), 'lock_version' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('customers')->where('idempotency_key', 'like', 'feed-customer-%')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function cashControl(int $companyId, int $paymentMethodId, int $userId, $now): int
    {
        $accountId = DB::table('cash_accounts')->insertGetId(['company_id' => $companyId, 'code' => 'SHOP-TREASURY', 'name_ar' => 'خزينة المحل', 'name_en' => 'Shop Treasury', 'type' => 'cash', 'currency_code' => 'EGP', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['transport', 'نقل'], ['loading', 'تحميل وتنزيل'], ['labor', 'عمال'], ['electricity', 'كهرباء'], ['rent', 'إيجار'], ['maintenance', 'صيانة'], ['other', 'مصروفات أخرى']] as [$code,$name]) {
            DB::table('expense_categories')->insert(['company_id' => $companyId, 'code' => $code, 'name_ar' => $name, 'name_en' => ucfirst($code), 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        return (int) $accountId;
    }

    private function openingStock(array $products, array $batches, int $storeId): void
    {
        foreach ($products as $i => $product) {
            $quantity = $i < 4 ? ['2500', '1500', '5000', '5000'][$i] : ($product->feed_kind === 'additive' ? (string) (100 + ($i % 5) * 100) : '4000');
            app(PostInventoryMovement::class)->execute($product->id, $storeId, $quantity, 'opening_balance', (string) $product->average_cost, 'feed-opening-'.$product->id, null, null, null, false, null, true, $batches[$product->id]->id ?? null);
        }
    }

    /** @return list<int> */
    private function purchases(array $products, array $productUnits, array $batches, array $supplierIds, int $storeId, int $userId, $now): array
    {
        $ids = [];
        for ($i = 0; $i < 36; $i++) {
            $product = $products[$i % count($products)];
            $unit = $productUnits[$product->id]['TON'];
            $quantity = $i === 0 ? '2.000000' : ($i % 3 === 0 ? '1.000000' : '0.500000');
            $base = bcmul($quantity, '1000', 6);
            $unitPrice = $i === 0 ? '16000.0000' : (string) (15000 + ($i % 8) * 250);
            $goods = bcmul($quantity, $unitPrice, 4);
            $charge = $i === 0 ? '500.0000' : (string) (100 + ($i % 5) * 50).'.0000';
            $total = bcadd($goods, $charge, 4);
            $invoiceId = DB::table('purchase_invoices')->insertGetId(['invoice_number' => sprintf('FEED-PI-%04d', $i + 1), 'supplier_id' => $supplierIds[$i % count($supplierIds)], 'store_id' => $storeId, 'invoice_date' => now()->subDays(90 - ($i * 2))->toDateString(), 'currency_code' => 'EGP', 'supplier_reference' => sprintf('SYN-%04d', $i + 1), 'status' => 'draft', 'subtotal' => $goods, 'tax_amount' => '0', 'discount_amount' => '0', 'total_amount' => $total, 'lock_version' => 0, 'created_by' => $userId, 'updated_by' => $userId, 'idempotency_key' => 'feed-purchase-'.$i, 'created_at' => $now, 'updated_at' => $now]);
            $lineId = DB::table('purchase_invoice_lines')->insertGetId(['purchase_invoice_id' => $invoiceId, 'product_id' => $product->id, 'product_unit_id' => $unit->id, 'entered_quantity' => $quantity, 'conversion_factor_snapshot' => '1000', 'entered_unit_price' => $unitPrice, 'quantity' => $base, 'quantity_received' => $base, 'unit_cost' => bcdiv($unitPrice, '1000', 4), 'base_consumer_price' => $product->sale_price, 'discount_value' => '0', 'discount_amount' => '0', 'tax_amount' => '0', 'tax_rate' => '0', 'subtotal' => $goods, 'line_total' => $goods, 'allocated_charge_amount' => $charge, 'inventory_unit_cost' => bcdiv($total, $base, 6), 'created_at' => $now, 'updated_at' => $now]);
            DB::table('purchase_invoice_charges')->insert(['purchase_invoice_id' => $invoiceId, 'charge_type' => $i % 2 ? 'transport' : 'loading', 'amount' => $charge, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('purchase_invoices')->where('id', $invoiceId)->update(['status' => 'approved', 'approved_at' => now()->subDays(90 - ($i * 2)), 'approved_by' => $userId]);
            app(PostInventoryMovement::class)->execute($product->id, $storeId, $base, 'purchase_receipt', bcdiv($total, $base, 6), 'feed-purchase-movement-'.$i, 'purchase_invoices', $invoiceId, $lineId, false, null, false, $batches[$product->id]->id ?? null);
            DB::table('product_suppliers')->where('product_id', $product->id)->where('supplier_id', $supplierIds[$i % count($supplierIds)])->update(['last_purchase_price' => $unitPrice, 'last_purchase_date' => now()->subDays(90 - ($i * 2))->toDateString()]);
            $ids[] = $invoiceId;
        }

        return $ids;
    }

    /** @return list<int> */
    private function sales(array $products, array $productUnits, array $batches, array $customerIds, int $branchId, int $storeId, int $drawerId, int $shiftId, int $paymentMethodId, int $userId, $now): array
    {
        $ids = [];
        for ($i = 0; $i < 150; $i++) {
            $product = $products[$i % 20];
            [$code,$entered] = match ($i % 5) {
                0 => ['BAG', '3'], 1 => ['KG', '7.5'], 2 => ['KG', '500'], 3 => ['TON', '0.5'], default => ['BAG', '2']
            };
            $unit = $productUnits[$product->id][$code];
            $base = bcmul($entered, (string) $unit->conversion_factor, 6);
            $enteredPrice = $code === 'KG' ? (string) (14 + ($i % 11)).'.0000' : ($code === 'TON' ? (string) (15000 + ($i % 9) * 100).'.0000' : (string) (840 + ($i % 5) * 5).'.0000');
            $basePrice = bcdiv($enteredPrice, (string) $unit->conversion_factor, 4);
            $total = bcmul($base, $basePrice, 2);
            if ($i === 0) {
                $entered = '4';
                $unit = $productUnits[$product->id]['BAG'];
                $base = bcmul($entered, (string) $unit->conversion_factor, 6);
                $enteredPrice = '850.0000';
                $basePrice = bcdiv($enteredPrice, (string) $unit->conversion_factor, 4);
                $total = '3400.00';
            }
            $customerId = $customerIds[$i % count($customerIds)];
            $paid = $i === 0 ? '2000.00' : ($i % 4 === 0 ? '0.00' : ($i % 4 === 1 ? bcdiv($total, '2', 2) : $total));
            $outstanding = bcsub($total, $paid, 2);
            $saleId = DB::table('sales')->insertGetId(['branch_id' => $branchId, 'store_id' => $storeId, 'cash_drawer_id' => $drawerId, 'shift_id' => $shiftId, 'cashier_id' => $userId, 'customer_id' => $customerId, 'document_number' => sprintf('FEED-S-%05d', $i + 1), 'status' => 'draft', 'idempotency_key' => 'feed-sale-'.$i, 'request_fingerprint' => hash('sha256', 'feed-sale-'.$i), 'subtotal' => $total, 'discount_total' => '0', 'tax_total' => '0', 'tax_applicable' => false, 'total' => $total, 'paid_total' => $paid, 'outstanding_amount' => $outstanding, 'payment_status' => bccomp($outstanding, '0', 2) === 0 ? 'paid' : (bccomp($paid, '0', 2) === 0 ? 'unpaid' : 'partial'), 'change_total' => '0', 'cash_rounding_amount' => '0', 'payable_total' => $total, 'currency_code' => 'EGP', 'lock_version' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $lineId = DB::table('sale_lines')->insertGetId(['sale_id' => $saleId, 'product_id' => $product->id, 'product_unit_id' => $unit->id, 'entered_quantity' => $entered, 'conversion_factor_snapshot' => $unit->conversion_factor, 'entered_unit_price' => $enteredPrice, 'line_number' => 1, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => $base, 'unit_price' => $basePrice, 'reference_price' => $basePrice, 'is_open_price' => false, 'gross_amount' => $total, 'discount_amount' => '0', 'allocated_invoice_discount' => '0', 'net_amount' => $total, 'consumed_cost' => bcmul($base, (string) $product->average_cost, 4), 'created_at' => $now, 'updated_at' => $now]);
            $movement = app(PostInventoryMovement::class)->execute($product->id, $storeId, '-'.$base, 'sale', null, 'feed-sale-movement-'.$i, 'sales', $saleId, $lineId, false, null, false, $batches[$product->id]->id ?? null);
            DB::table('sale_lines')->where('id', $lineId)->update(['stock_movement_id' => $movement->id]);
            DB::table('sales')->where('id', $saleId)->update(['status' => 'approved', 'approved_at' => now()->subDays(75 - intdiv($i, 2))]);
            if (bccomp($paid, '0', 2) > 0) {
                DB::table('sale_payments')->insert(['sale_id' => $saleId, 'payment_method_id' => $paymentMethodId, 'method_code' => 'CASH', 'method_type' => 'cash', 'amount' => $paid, 'tendered_amount' => $paid, 'change_amount' => '0', 'idempotency_key' => 'feed-sale-payment-'.$i, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            }
            $ids[] = $saleId;
        }

        return $ids;
    }

    private function receiptsAndPayments(array $saleIds, array $purchaseIds, int $paymentMethodId, int $cashAccountId, int $userId, $now): void
    {
        $sale = DB::table('sales')->where('id', $saleIds[0])->first();
        $receiptId = DB::table('customer_receipts')->insertGetId(['public_id' => (string) Str::uuid(), 'customer_id' => $sale->customer_id, 'store_id' => $sale->store_id, 'payment_method_id' => $paymentMethodId, 'cash_account_id' => $cashAccountId, 'receipt_date' => now()->subDays(5)->toDateString(), 'currency_code' => $sale->currency_code, 'amount' => '500', 'reference' => 'SYN-RECEIPT-1', 'status' => 'approved', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => $now, 'idempotency_key' => 'feed-receipt-1', 'payload_hash' => hash('sha256', 'feed-receipt-1'), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customer_receipt_allocations')->insert(['customer_receipt_id' => $receiptId, 'sale_id' => $sale->id, 'amount' => '500', 'created_at' => $now]);
        $purchase = DB::table('purchase_invoices')->where('id', $purchaseIds[0])->first();
        $cashRows = [[$receiptId, 'customer_receipt', '500', 'receipt']];
        foreach ([['20000', 'initial'], ['5000', 'later']] as [$amount, $suffix]) {
            $paymentId = DB::table('supplier_payments')->insertGetId(['supplier_id' => $purchase->supplier_id, 'payment_method_id' => $paymentMethodId, 'cash_account_id' => $cashAccountId, 'payment_date' => now()->subDays($suffix === 'initial' ? 10 : 4)->toDateString(), 'currency_code' => 'EGP', 'amount' => $amount, 'reference' => 'SYN-PAY-'.$suffix, 'status' => 'approved', 'idempotency_key' => 'feed-supplier-payment-'.$suffix, 'payload_hash' => hash('sha256', 'feed-supplier-payment-'.$suffix), 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('supplier_payment_allocations')->insert(['supplier_payment_id' => $paymentId, 'purchase_invoice_id' => $purchase->id, 'amount' => $amount, 'created_at' => $now, 'updated_at' => $now]);
            $cashRows[] = [$paymentId, 'supplier_payment', bcsub('0', $amount, 4), 'supplier-'.$suffix];
        }
        $cashRows[] = [$sale->id, 'cash_sale_transfer', '850', 'sale'];
        foreach ($cashRows as [$sourceId, $type, $amount, $suffix]) {
            DB::table('cash_transactions')->insert(['cash_account_id' => $cashAccountId, 'transaction_date' => now()->toDateString(), 'amount' => $amount, 'transaction_type' => $type, 'source_type' => $type, 'source_id' => $sourceId, 'idempotency_key' => 'feed-'.$type.'-'.$suffix, 'payload_hash' => hash('sha256', 'feed-'.$type.'-'.$suffix), 'description' => 'حركة تجريبية', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function expenses(int $companyId, int $paymentMethodId, int $cashAccountId, int $userId, $now): void
    {
        $categories = DB::table('expense_categories')->where('company_id', $companyId)->pluck('id')->all();
        for ($i = 0; $i < 40; $i++) {
            $amount = (string) (100 + ($i % 8) * 75).'.0000';
            $id = DB::table('expenses')->insertGetId(['company_id' => $companyId, 'expense_category_id' => $categories[$i % count($categories)], 'cash_account_id' => $cashAccountId, 'payment_method_id' => $paymentMethodId, 'expense_date' => now()->subDays($i * 2)->toDateString(), 'amount' => $amount, 'description' => 'مصروف تجريبي '.($i + 1), 'reference' => 'EXP-'.($i + 1), 'status' => 'approved', 'idempotency_key' => 'feed-expense-'.$i, 'payload_hash' => hash('sha256', 'feed-expense-'.$i), 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('cash_transactions')->insert(['cash_account_id' => $cashAccountId, 'transaction_date' => now()->subDays($i * 2)->toDateString(), 'amount' => bcsub('0', $amount, 4), 'transaction_type' => 'expense', 'source_type' => 'expenses', 'source_id' => $id, 'idempotency_key' => 'feed-expense-cash-'.$i, 'payload_hash' => hash('sha256', 'feed-expense-cash-'.$i), 'description' => 'سداد مصروف تجريبي', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
