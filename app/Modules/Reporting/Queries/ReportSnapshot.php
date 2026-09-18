<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyPayment;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\GiftCardLedger;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Models\ShiftClosingSubmission;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ReportSnapshot
{
    public const EXPORT_ROW_LIMIT = 5000;

    private const SALES_PAGE_SIZE = 50;

    /** @var array<string, string> */
    private const MODULE_PERMISSIONS = [
        'sales' => 'pos_sales.view',
        'inventory' => 'inventory_stock_card.view',
        'purchasing' => 'purchase_orders.view',
        'cash' => 'shifts_cash_movements.view',
        'customers' => 'customers.view',
        'parties' => 'party_bookings_invoices.view',
        'quotations' => 'quotations.view',
        'assets' => 'rental_assets.view',
    ];

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function execute(User $user, array $filters = [], bool $forExport = false): array
    {
        Gate::forUser($user)->authorize('dashboard_reports.view');

        [$from, $to] = $this->dateRange($filters);
        $module = $this->moduleFilter($filters['module'] ?? null);
        $modules = $this->authorizedModules($user, $module);

        $stores = Store::query()->visibleTo($user)->where('status', 'active');
        if (filled($filters['branch_id'] ?? null)) {
            $branchId = (int) $filters['branch_id'];
            abort_unless($user->is_super_admin || $user->canAccessBranch($branchId), 403);
            $stores->where('branch_id', $branchId);
        }
        if (filled($filters['store_id'] ?? null)) {
            $storeId = (int) $filters['store_id'];
            abort_unless($user->is_super_admin || $user->canAccessStore($storeId), 403);
            $stores->whereKey($storeId);
        }

        $storeIds = $stores->pluck('id');
        $userId = filled($filters['user_id'] ?? null) ? (int) $filters['user_id'] : null;
        if ($userId !== null) {
            $this->assertUserFilter($user, $userId, $storeIds);
        }
        $customerId = $this->scopedIdFilter($filters['customer_id'] ?? null, Customer::query()->visibleTo($user), __('customer'));
        $supplierId = $this->scopedIdFilter($filters['supplier_id'] ?? null, Supplier::query()->where('status', 'active'), __('supplier'));
        $productId = $this->scopedIdFilter($filters['product_id'] ?? null, Product::query()->sellable(), __('product'));
        $categoryId = $this->scopedIdFilter($filters['category_id'] ?? null, Category::query()->where('status', 'active'), __('category'));
        $brandId = $this->scopedIdFilter($filters['brand_id'] ?? null, Brand::query()->where('status', 'active'), __('brand'));
        $ageLabelId = $this->scopedIdFilter($filters['age_label_id'] ?? null, AgeLabel::query()->where('status', 'active'), __('age'));
        $characterId = $this->scopedIdFilter($filters['character_id'] ?? null, Character::query()->where('status', 'active'), __('character'));
        $colourId = $this->scopedIdFilter($filters['colour_id'] ?? null, Colour::query()->where('status', 'active'), __('colour'));
        $genderId = $this->scopedIdFilter($filters['gender_id'] ?? null, Gender::query()->where('status', 'active'), __('gender'));
        $productType = $this->enumFilter($filters['product_type'] ?? null, ['standard', 'service', 'digital', 'bundle', 'rental', 'party_consumable']);
        $productStatus = $this->enumFilter($filters['product_status'] ?? null, ['active', 'inactive', 'archived']);
        $paymentMethodId = $this->scopedIdFilter($filters['payment_method_id'] ?? null, PaymentMethod::query()->where('status', 'active'), __('payment method'));
        $documentStatus = $this->enumFilter($filters['document_status'] ?? null, ['approved', 'suspended', 'cancelled']);
        $partyStatus = $this->enumFilter($filters['party_status'] ?? null, ['draft', 'confirmed', 'closed', 'cancelled']);
        $search = trim((string) ($filters['search'] ?? ''));
        $search = $search === '' ? null : mb_substr($search, 0, 100);
        $sort = filled($filters['sort'] ?? null) ? (string) $filters['sort'] : null;
        $sortColumns = ['document' => 'document_number', 'store' => 'store_id', 'cashier' => 'cashier_id', 'gross' => 'subtotal', 'discount' => 'discount_total', 'tax' => 'tax_total', 'total' => 'total'];
        abort_unless($sort === null || isset($sortColumns[$sort]), 422, __('The selected report sort is not available.'));
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        abort_unless(in_array($direction, ['asc', 'desc'], true), 422, __('The selected report direction is not available.'));
        $page = max(1, min(1000000, (int) ($filters['page'] ?? 1)));

        $selectedSaleStatus = $documentStatus ?? 'approved';
        $salesDateColumn = $selectedSaleStatus === 'approved' ? 'approved_at' : 'created_at';
        $sales = Sale::query()->visibleTo($user)
            ->where('status', $selectedSaleStatus)
            ->whereBetween($salesDateColumn, [$from, $to])
            ->whereIn('store_id', $storeIds);
        if ($userId !== null) {
            $sales->where('cashier_id', $userId);
        }
        if ($customerId !== null) {
            $sales->where('customer_id', $customerId);
        }
        if ($productId !== null) {
            $sales->whereHas('lines', fn (Builder $lines): Builder => $lines->where('product_id', $productId));
        }
        if ($categoryId !== null) {
            $sales->whereHas('lines.product', fn (Builder $product): Builder => $product->where('category_id', $categoryId));
        }
        if ($paymentMethodId !== null) {
            $sales->whereHas('payments', fn (Builder $payments): Builder => $payments->where('payment_method_id', $paymentMethodId));
        }
        $sales->when($search !== null, fn (Builder $query): Builder => $query->where(function (Builder $scope) use ($search): void {
            $scope->where('document_number', 'like', '%'.$search.'%')->orWhere('notes', 'like', '%'.$search.'%');
        }));
        $salesRowsQuery = clone $sales;
        if ($sort === null) {
            $salesRowsQuery->orderByDesc($salesDateColumn)->orderByDesc('id');
        } else {
            $salesRowsQuery->orderBy($sortColumns[$sort], $direction)->orderBy('id', $direction);
        }

        $salesCount = 0;
        $salesGross = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $salesTotal = 0.0;
        $paymentTotal = 0.0;
        $refundTotal = 0.0;
        $paymentMethodSummary = collect();
        $salesRows = collect();
        $salesPagination = null;
        $salesDetailTotal = 0;

        if (in_array('sales', $modules, true)) {
            $salesCount = (clone $sales)->count();
            $salesGross = (float) (clone $sales)->sum('subtotal');
            $discountTotal = (float) (clone $sales)->sum('discount_total');
            $taxTotal = (float) (clone $sales)->sum('tax_total');
            $salesTotal = round($salesGross - $discountTotal + $taxTotal, 2);
            if (Gate::forUser($user)->allows('pos_sales.payment_view')) {
                $paymentTotal = (float) SalePayment::query()
                    ->whereIn('sale_id', (clone $sales)->select('id'))
                    ->sum('amount');
                $paymentMethodSummary = SalePayment::query()
                    ->whereIn('sale_id', (clone $sales)->select('id'))
                    ->select('method_code')
                    ->selectRaw('SUM(amount) as amount')
                    ->groupBy('method_code')
                    ->orderBy('method_code')
                    ->get()
                    ->map(static fn (SalePayment $payment): array => [
                        'method_code' => (string) $payment->method_code,
                        'amount' => round((float) $payment->amount, 2),
                    ])->values();
            }
            if (Gate::forUser($user)->allows('returns.view') || Gate::forUser($user)->allows('returns_exchanges_gift_instruments.view')) {
                $refunds = RetailReturn::query()->visibleTo($user)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$from, $to])
                    ->whereIn('store_id', $storeIds);
                if ($userId !== null) {
                    $refunds->where('cashier_id', $userId);
                }
                if ($customerId !== null) {
                    $refunds->where('customer_id', $customerId);
                }
                if ($productId !== null) {
                    $refunds->whereHas('lines', fn (Builder $lines): Builder => $lines->where('product_id', $productId));
                }
                if ($categoryId !== null) {
                    $refunds->whereHas('lines.product', fn (Builder $product): Builder => $product->where('category_id', $categoryId));
                }
                if ($paymentMethodId !== null) {
                    $refunds->whereHas('settlements', fn (Builder $settlements): Builder => $settlements->where('payment_method_id', $paymentMethodId));
                }
                $refundTotal = (float) $refunds->sum('settlement_value');
            }
            $salesDetailTotal = (clone $salesRowsQuery)->reorder()->count();
            $columns = ['id', 'document_number', 'store_id', 'cashier_id', 'subtotal', 'discount_total', 'tax_total', 'total', 'approved_at', 'created_at'];
            if ($forExport) {
                $salesRows = (clone $salesRowsQuery)->with(['store', 'cashier'])->limit(self::EXPORT_ROW_LIMIT)->get($columns);
            } else {
                $salesPagination = (clone $salesRowsQuery)->with(['store', 'cashier'])->paginate(self::SALES_PAGE_SIZE, $columns, 'page', $page);
                $salesRows = $salesPagination->getCollection();
            }
        }

        $stock = StockBalance::query()
            ->whereIn('store_id', $storeIds)
            ->whereHas('product', function (Builder $product): void {
                $product->where('status', 'active')->where(function (Builder $scope): void {
                    $scope->where(function (Builder $simple): void {
                        $simple->whereNull('parent_product_id')->where('has_variations', false);
                    })->orWhere(function (Builder $variant): void {
                        $variant->whereNotNull('parent_product_id')->whereHas('parent', function (Builder $family): void {
                            $family->where('status', 'active')->where('has_variations', true);
                        });
                    });
                });
            });
        if ($productId !== null) {
            $stock->where('product_id', $productId);
        }
        if ($categoryId !== null) {
            $stock->whereHas('product', fn (Builder $product): Builder => $product->where('category_id', $categoryId));
        }
        $stockOnHand = 0.0;
        $stockReserved = 0.0;
        $stockAvailable = 0.0;
        $stockInTransit = 0.0;
        $stockValue = 0.0;
        $stockRows = 0;
        if (in_array('inventory', $modules, true)) {
            $stockRows = (clone $stock)->count();
            $stockOnHand = (float) (clone $stock)->sum('on_hand');
            $stockReserved = (float) (clone $stock)->sum('reserved');
            $stockAvailable = round($stockOnHand - $stockReserved, 6);
            $stockInTransit = (float) (clone $stock)->sum('in_transit');
            if (Gate::forUser($user)->allows('inventory_stock_card.cost_view')) {
                $stockValue = (float) (clone $stock)->sum('total_value');
            }
        }

        $assets = RentalAsset::query()->visibleTo($user)->whereIn('store_id', $storeIds);
        if ($userId !== null) {
            $assets->where('created_by', $userId);
        }
        $assetRows = collect();
        $assetsAvailable = 0;
        $assetsReserved = 0;
        $assetIssues = 0;
        if (in_array('assets', $modules, true)) {
            $assetsAvailable = (clone $assets)->where('status', 'available')->count();
            $assetsReserved = (clone $assets)->where('status', 'reserved')->count();
            $assetIssues = (clone $assets)->whereIn('status', ['damaged', 'under_maintenance', 'lost'])->count();
            $assetRows = (clone $assets)->latest('id')->limit(50)->get(['id', 'code', 'name_en', 'status', 'condition', 'store_id']);
        }

        $quotes = Quotation::query()->visibleTo($user)->whereIn('store_id', $storeIds)->whereBetween('created_at', [$from, $to]);
        if ($userId !== null) {
            $quotes->where('created_by', $userId);
        }
        $quotationCount = in_array('quotations', $modules, true) ? (clone $quotes)->count() : 0;
        $quotationValue = in_array('quotations', $modules, true) ? (float) (clone $quotes)->sum('total') : 0.0;

        $purchaseOrderCount = 0;
        $purchaseOrderValue = 0.0;
        $purchaseInvoiceCount = 0;
        $purchaseInvoiceValue = 0.0;
        $purchaseReturnCount = 0;
        $purchaseReturnValue = 0.0;
        if (in_array('purchasing', $modules, true)) {
            $purchaseOrders = PurchaseOrder::query()->whereIn('store_id', $storeIds)->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])->whereIn('status', ['approved', 'partially_received', 'received', 'closed']);
            if ($supplierId !== null) {
                $purchaseOrders->where('supplier_id', $supplierId);
            }
            if ($userId !== null) {
                $purchaseOrders->where('created_by', $userId);
            }
            $purchaseOrderCount = (clone $purchaseOrders)->count();
            $purchaseOrderValue = (float) (clone $purchaseOrders)->sum('total_amount');
            $purchaseInvoices = PurchaseInvoice::query()->whereIn('store_id', $storeIds)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->where('status', 'approved');
            if ($supplierId !== null) {
                $purchaseInvoices->where('supplier_id', $supplierId);
            }
            if ($userId !== null) {
                $purchaseInvoices->where('created_by', $userId);
            }
            $purchaseInvoiceCount = (clone $purchaseInvoices)->count();
            $purchaseInvoiceValue = (float) (clone $purchaseInvoices)->sum('total_amount');
            $purchaseReturns = PurchaseReturn::query()->whereIn('store_id', $storeIds)->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])->where('status', 'approved');
            if ($supplierId !== null) {
                $purchaseReturns->where('supplier_id', $supplierId);
            }
            if ($userId !== null) {
                $purchaseReturns->where('created_by', $userId);
            }
            $purchaseReturnCount = (clone $purchaseReturns)->count();
            $purchaseReturnValue = (float) (clone $purchaseReturns)->sum('total_amount');
        }

        $openShiftCount = 0;
        $unclosedShiftCount = 0;
        $shiftVariance = 0.0;
        if (in_array('cash', $modules, true)) {
            $shifts = PosShift::query()->visibleTo($user)->whereIn('store_id', $storeIds)->whereBetween('opened_at', [$from, $to]);
            if ($userId !== null) {
                $shifts->where('cashier_id', $userId);
            }
            $openShiftCount = (clone $shifts)->where('status', 'open')->count();
            $unclosedShiftCount = (clone $shifts)->whereNotIn('status', ['closed', 'cancelled'])->count();
            if (Gate::forUser($user)->allows('shifts_cash_movements.approve')) {
                $shiftVariance = (float) ShiftClosingSubmission::query()->whereIn('shift_id', (clone $shifts)->select('id'))->sum('total_variance');
            }
        }

        $customerCount = 0;
        $newCustomerCount = 0;
        $loyaltyEarned = 0;
        $loyaltyRedeemed = 0;
        $productWalletBalance = 0.0;
        $partyWalletBalance = 0.0;
        $giftCardIssued = 0.0;
        $giftCardRedeemed = 0.0;
        $giftCardOutstanding = 0.0;
        if (in_array('customers', $modules, true)) {
            $customerQuery = Customer::query()->visibleTo($user)->where(function (Builder $scope) use ($storeIds): void {
                $scope->whereIn('created_store_id', $storeIds)->orWhereHas('scopes', fn (Builder $customerScope): Builder => $customerScope->whereIn('store_id', $storeIds));
            });
            if ($userId !== null) {
                $customerQuery->where('created_by', $userId);
            }
            $customerCount = (clone $customerQuery)->count();
            $newCustomerCount = (clone $customerQuery)->whereBetween('created_at', [$from, $to])->count();
            $loyalty = LoyaltyLedger::query()->visibleTo($user)->whereIn('store_id', $storeIds)->whereBetween('effective_at', [$from, $to]);
            if ($userId !== null) {
                $loyalty->where('created_by', $userId);
            }
            $loyaltyEarned = (int) (clone $loyalty)->where('event_type', 'earn')->sum('points');
            $loyaltyRedeemed = abs((int) (clone $loyalty)->where('event_type', 'redeem')->sum('points'));
            $productWallets = ProductWalletLedger::query()->whereIn('store_id', $storeIds);
            if ($userId !== null) {
                $productWallets->where('created_by', $userId);
            }
            $latestProductWalletIds = (clone $productWallets)->selectRaw('MAX(id)')->groupBy('customer_id');
            $productWalletBalance = (float) ProductWalletLedger::query()->whereIn('id', $latestProductWalletIds)->sum('balance_after');
            $partyWallets = PartyWalletLedger::query()->whereIn('store_id', $storeIds);
            if ($userId !== null) {
                $partyWallets->where('created_by', $userId);
            }
            $latestPartyWalletIds = (clone $partyWallets)->selectRaw('MAX(id)')->groupBy('customer_id');
            $partyWalletBalance = (float) PartyWalletLedger::query()->whereIn('id', $latestPartyWalletIds)->sum('balance_after');
            $cards = GiftCard::query()->visibleTo($user)->whereIn('store_id', $storeIds);
            if ($userId !== null) {
                $cards->where('issued_by', $userId);
            }
            $giftCardIssued = (float) (clone $cards)->whereBetween('created_at', [$from, $to])->sum('issued_value');
            $giftCardOutstanding = (float) (clone $cards)->whereIn('status', ['active', 'partially_redeemed'])->sum('balance');
            $giftCardRedeemed = abs((float) GiftCardLedger::query()->whereIn('gift_card_id', (clone $cards)->select('id'))->whereBetween('created_at', [$from, $to])->where('event_type', 'redeem')->sum('amount'));
        }

        $partyBookingCount = 0;
        $upcomingPartyCount = 0;
        $partyInvoiceCount = 0;
        $partyBalanceDue = 0.0;
        if (in_array('parties', $modules, true)) {
            $bookings = PartyBooking::query()->visibleTo($user)->whereIn('store_id', $storeIds)->whereBetween('party_date', [$from->toDateString(), $to->toDateString()]);
            if ($userId !== null) {
                $bookings->where('created_by', $userId);
            }
            if ($partyStatus !== null) {
                $bookings->where('status', $partyStatus);
            }
            $partyBookingCount = (clone $bookings)->count();
            $upcomingPartyCount = (clone $bookings)->whereIn('status', ['confirmed', 'draft'])->whereDate('party_date', '>=', now()->toDateString())->count();
            $invoices = PartyInvoice::query()->visibleTo($user)->whereIn('party_booking_id', (clone $bookings)->select('id'));
            $partyInvoiceCount = (clone $invoices)->count();
            $partyBalanceDue = (float) (clone $invoices)->sum('balance_due');
        }

        $normalizedFilters = [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'branch_id' => filled($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null,
            'store_id' => filled($filters['store_id'] ?? null) ? (int) $filters['store_id'] : null,
            'user_id' => $userId,
            'supplier_id' => $supplierId,
            'customer_id' => $customerId,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'brand_id' => $brandId, 'age_label_id' => $ageLabelId, 'character_id' => $characterId,
            'colour_id' => $colourId, 'gender_id' => $genderId, 'product_type' => $productType, 'product_status' => $productStatus,
            'payment_method_id' => $paymentMethodId,
            'document_status' => $documentStatus,
            'party_status' => $partyStatus,
            'module' => $module,
            'search' => $search, 'sort' => $sort, 'direction' => $direction,
        ];
        if (! $forExport) { $normalizedFilters['page'] = $page; }

        $kpis = [
            'gross_sales' => round($salesGross, 2),
            'net_sales' => round($salesGross - $discountTotal, 2),
            'invoice_count' => $salesCount,
            'average_invoice_value' => $salesCount === 0 ? 0.0 : round(($salesGross - $discountTotal) / $salesCount, 2),
            'discounts' => round($discountTotal, 2),
            'tax' => round($taxTotal, 2),
            'total_sales' => round($salesTotal, 2),
            'payments_collected' => round($paymentTotal, 2),
            'refunds' => round($refundTotal, 2),
            'net_after_refunds' => round($salesGross - $discountTotal - $refundTotal, 2),
            'stock_on_hand' => $stockOnHand,
            'stock_reserved' => $stockReserved,
            'stock_available' => $stockAvailable,
            'stock_in_transit' => $stockInTransit,
            'quotation_count' => $quotationCount,
            'quotation_value' => round($quotationValue, 2),
            'purchase_order_count' => $purchaseOrderCount,
            'purchase_order_value' => round($purchaseOrderValue, 2),
            'purchase_invoice_count' => $purchaseInvoiceCount,
            'purchase_invoice_value' => round($purchaseInvoiceValue, 2),
            'purchase_return_count' => $purchaseReturnCount,
            'purchase_return_value' => round($purchaseReturnValue, 2),
            'open_shifts' => $openShiftCount,
            'unclosed_shifts' => $unclosedShiftCount,
            'customer_count' => $customerCount,
            'new_customer_count' => $newCustomerCount,
            'party_booking_count' => $partyBookingCount,
            'upcoming_party_count' => $upcomingPartyCount,
            'party_invoice_count' => $partyInvoiceCount,
            'party_balance_due' => round($partyBalanceDue, 2),
            'assets_available' => $assetsAvailable,
            'assets_reserved' => $assetsReserved,
            'asset_issues' => $assetIssues,
        ];
        if (Gate::forUser($user)->allows('shifts_cash_movements.approve') && in_array('cash', $modules, true)) {
            $kpis['shift_variance'] = round($shiftVariance, 2);
        }
        if (Gate::forUser($user)->allows('loyalty.view') && in_array('customers', $modules, true)) {
            $kpis['loyalty_earned'] = $loyaltyEarned;
            $kpis['loyalty_redeemed'] = $loyaltyRedeemed;
        }
        if (Gate::forUser($user)->allows('product_wallet.view') && in_array('customers', $modules, true)) {
            $kpis['product_wallet_balance'] = round($productWalletBalance, 2);
        }
        if (Gate::forUser($user)->allows('party_wallet.view') && in_array('customers', $modules, true)) {
            $kpis['party_wallet_balance'] = round($partyWalletBalance, 2);
        }
        if ((Gate::forUser($user)->allows('gift_cards.view') || Gate::forUser($user)->allows('returns_exchanges_gift_instruments.view')) && in_array('customers', $modules, true)) {
            $kpis['gift_card_issued'] = round($giftCardIssued, 2);
            $kpis['gift_card_redeemed'] = round($giftCardRedeemed, 2);
            $kpis['gift_card_outstanding'] = round($giftCardOutstanding, 2);
        }
        if (Gate::forUser($user)->allows('inventory_stock_card.cost_view') && in_array('inventory', $modules, true)) {
            $kpis['stock_value'] = round($stockValue, 2);
        }
        if (! Gate::forUser($user)->allows('pos_sales.payment_view')) {
            unset($kpis['payments_collected']);
        }
        if (! Gate::forUser($user)->allows('returns.view') && ! Gate::forUser($user)->allows('returns_exchanges_gift_instruments.view')) {
            unset($kpis['refunds'], $kpis['net_after_refunds']);
        }
        if ($module !== null) {
            $moduleKpis = [
                'sales' => ['gross_sales', 'net_sales', 'invoice_count', 'average_invoice_value', 'discounts', 'tax', 'total_sales', 'payments_collected', 'refunds', 'net_after_refunds'],
                'inventory' => ['stock_on_hand', 'stock_reserved', 'stock_available', 'stock_in_transit', 'stock_value'],
                'purchasing' => ['purchase_order_count', 'purchase_order_value', 'purchase_invoice_count', 'purchase_invoice_value', 'purchase_return_count', 'purchase_return_value'],
                'cash' => ['open_shifts', 'unclosed_shifts', 'shift_variance'],
                'customers' => ['customer_count', 'new_customer_count', 'loyalty_earned', 'loyalty_redeemed', 'product_wallet_balance', 'party_wallet_balance', 'gift_card_issued', 'gift_card_redeemed', 'gift_card_outstanding'],
                'parties' => ['party_booking_count', 'upcoming_party_count', 'party_invoice_count', 'party_balance_due'],
                'quotations' => ['quotation_count', 'quotation_value'],
                'assets' => ['assets_available', 'assets_reserved', 'asset_issues'],
            ];
            $kpis = array_intersect_key($kpis, array_flip($moduleKpis[$module]));
        }

        $detailSections = $this->detailSections($user, $modules, $storeIds, $from, $to, $normalizedFilters, $salesRows, $salesPagination, $assetRows, $forExport);
        $visuals = $this->visuals($modules, $kpis, $paymentMethodSummary->all());

        return [
            'filters' => $normalizedFilters,
            'modules' => $modules,
            'fresh_at' => now()->toIso8601String(),
            'kpis' => $kpis,
            'sources' => [
                'sales_label' => __($documentStatus === null ? 'Approved sales' : ucfirst($documentStatus).' sales'),
                'sales_count' => $salesCount,
                'sales_gross' => round($salesGross, 2),
                'sales_net' => round($salesGross - $discountTotal, 2),
                'sales_tax' => round($taxTotal, 2),
                'sales_total' => round($salesTotal, 2),
                'approved_sales_count' => $salesCount,
                'approved_sales_gross' => round($salesGross, 2),
                'approved_sales_net' => round($salesGross - $discountTotal, 2),
                'approved_sales_tax' => round($taxTotal, 2),
                'approved_sales_total' => round($salesTotal, 2),
                'payment_rows_total' => round($paymentTotal, 2),
                'payment_method_summary' => $paymentMethodSummary->all(),
                'refund_rows_total' => round($refundTotal, 2),
                'stock_balance_rows' => $stockRows,
                'asset_rows' => $assetRows->count(),
                'purchase_order_count' => $purchaseOrderCount,
                'purchase_invoice_count' => $purchaseInvoiceCount,
                'purchase_return_count' => $purchaseReturnCount,
                'shift_open_rows' => $openShiftCount,
                'shift_unclosed_rows' => $unclosedShiftCount,
                'customer_rows' => $customerCount,
                'party_booking_rows' => $partyBookingCount,
                'party_invoice_rows' => $partyInvoiceCount,
            ],
            'sales' => $salesRows,
            'assets' => $assetRows,
            'export' => ['row_limit' => self::EXPORT_ROW_LIMIT, 'total_filtered_rows' => $salesDetailTotal, 'exported_row_count' => $salesRows->count(), 'truncated' => $forExport && $salesDetailTotal > self::EXPORT_ROW_LIMIT],
            'detail_sections' => $detailSections,
            'visuals' => $visuals,
        ];
    }

    /**
     * Return a small, presentation-neutral chart contract. Every value comes
     * from the already scoped KPI/detail snapshot, so charts cannot diverge
     * from the cards, tables, or permission-filtered export data.
     *
     * @param  array<int, string>  $modules
     * @param  array<string, int|float>  $kpis
     * @param  array<int, array{method_code:string,amount:float}>  $paymentMethodSummary
     * @return array<int, array{key:string,title:string,description:string,type:string,unit:string,labels:array<int,string>,series:array<int,array{key:string,label:string,name:string,data:array<int,int|float>}>}>
     */
    private function visuals(array $modules, array $kpis, array $paymentMethodSummary): array
    {
        $visuals = [];
        $add = static function (string $key, string $title, string $type, string $unit, array $labels, array $series) use (&$visuals): void {
            $visuals[] = [
                'key' => $key,
                'title' => $title,
                'description' => __('Uses the current authorized filters and reconciles to the report detail and source totals.'),
                'type' => $type,
                'unit' => $unit,
                'labels' => array_values(array_slice($labels, 0, 31)),
                'series' => array_values(array_slice(array_map(static fn (array $item, int $index): array => [
                    'key' => (string) ($item['key'] ?? 'series_'.($index + 1)),
                    'label' => (string) ($item['label'] ?? $item['name']),
                    'name' => (string) ($item['name'] ?? $item['label']),
                    'data' => array_values(array_map(static fn (mixed $value): int|float => is_int($value) ? $value : (float) $value, array_slice($item['data'], 0, 31))),
                ], $series, array_keys($series)), 0, 4)),
            ];
        };
        if (in_array('sales', $modules, true)) {
            $add('sales_financials', __('Sales financial breakdown'), 'bar', 'money',
                [__('Gross'), __('Net before tax'), __('Tax'), __('Final')],
                [['name' => __('Amount'), 'data' => [$kpis['gross_sales'] ?? 0, $kpis['net_sales'] ?? 0, $kpis['tax'] ?? 0, $kpis['total_sales'] ?? 0]]]);
            if ($paymentMethodSummary !== []) {
                $add('sales_payment_methods', __('Payment method collection'), 'donut', 'money',
                    collect($paymentMethodSummary)->pluck('method_code')->all(),
                    [['name' => __('Collected'), 'data' => collect($paymentMethodSummary)->pluck('amount')->all()]]);
            }
        }

        if (in_array('customers', $modules, true)) {
            $add('customer_activity', __('Customer activity'), 'bar', 'number',
                [__('All visible customers'), __('New in range')],
                [['name' => __('Customers'), 'data' => [$kpis['customer_count'] ?? 0, $kpis['new_customer_count'] ?? 0]]]);
            if (array_key_exists('loyalty_earned', $kpis) || array_key_exists('loyalty_redeemed', $kpis)) {
                $add('customer_loyalty', __('Loyalty movement'), 'bar', 'points',
                    [__('Earned'), __('Redeemed')],
                    [['name' => __('Points'), 'data' => [$kpis['loyalty_earned'] ?? 0, $kpis['loyalty_redeemed'] ?? 0]]]);
            }
            foreach ([
                'product_wallet_balance' => ['customer_product_wallet', __('Product Wallet balance')],
                'party_wallet_balance' => ['customer_party_wallet', __('Party Wallet balance')],
                'gift_card_outstanding' => ['customer_gift_cards', __('Gift Card outstanding')],
            ] as $metric => [$key, $title]) {
                if (array_key_exists($metric, $kpis)) {
                    $add($key, $title, 'bar', 'money', [$title], [['name' => __('Balance'), 'data' => [$kpis[$metric]]]]);
                }
            }
        }

        if (in_array('cash', $modules, true)) {
            $open = (int) ($kpis['open_shifts'] ?? 0);
            $unclosed = (int) ($kpis['unclosed_shifts'] ?? 0);
            $add('cash_shift_status', __('Shift status'), 'donut', 'number',
                [__('Open'), __('Other unclosed')],
                [['name' => __('Shifts'), 'data' => [$open, max(0, $unclosed - $open)]]]);
            if (array_key_exists('shift_variance', $kpis)) {
                $add('cash_variance', __('Approved shift variance'), 'bar', 'money',
                    [__('Variance')], [['name' => __('Amount'), 'data' => [$kpis['shift_variance']]]]);
            }
        }

        if (in_array('purchasing', $modules, true)) {
            $add('purchasing_documents', __('Approved purchasing documents'), 'bar', 'number',
                [__('Orders'), __('Invoices'), __('Returns')],
                [['name' => __('Documents'), 'data' => [$kpis['purchase_order_count'] ?? 0, $kpis['purchase_invoice_count'] ?? 0, $kpis['purchase_return_count'] ?? 0]]]);
            $add('purchasing_values', __('Purchasing value'), 'bar', 'money',
                [__('Orders'), __('Invoices'), __('Returns')],
                [['name' => __('Value'), 'data' => [$kpis['purchase_order_value'] ?? 0, $kpis['purchase_invoice_value'] ?? 0, $kpis['purchase_return_value'] ?? 0]]]);
        }

        if (in_array('inventory', $modules, true)) {
            $add('inventory_quantity', __('Inventory quantity composition'), 'bar', 'number',
                [__('On hand'), __('Reserved'), __('Available'), __('In transit')],
                [['name' => __('Quantity'), 'data' => [
                    $kpis['stock_on_hand'] ?? 0, $kpis['stock_reserved'] ?? 0,
                    $kpis['stock_available'] ?? 0, $kpis['stock_in_transit'] ?? 0,
                ]]]);
            if (array_key_exists('stock_value', $kpis)) {
                $add('inventory_value', __('Inventory valuation'), 'bar', 'money',
                    [__('Stock value')], [['name' => __('Value'), 'data' => [$kpis['stock_value']]]]);
            }
        }

        if (in_array('parties', $modules, true)) {
            $add('party_pipeline', __('Party pipeline'), 'bar', 'number',
                [__('Bookings'), __('Upcoming'), __('Invoices')],
                [['name' => __('Documents'), 'data' => [$kpis['party_booking_count'] ?? 0, $kpis['upcoming_party_count'] ?? 0, $kpis['party_invoice_count'] ?? 0]]]);
            $add('party_balance', __('Party outstanding balance'), 'bar', 'money',
                [__('Balance due')], [['name' => __('Amount'), 'data' => [$kpis['party_balance_due'] ?? 0]]]);
        }

        if (in_array('assets', $modules, true)) {
            $add('asset_status', __('Rental asset status'), 'donut', 'number',
                [__('Available'), __('Reserved'), __('Issues')],
                [['name' => __('Assets'), 'data' => [$kpis['assets_available'] ?? 0, $kpis['assets_reserved'] ?? 0, $kpis['asset_issues'] ?? 0]]]);
        }

        return $visuals;
    }

    /**
     * Build bounded, display/export-ready detail tables from the same scoped
     * source records as the KPI snapshot. Cost, variance, customer-sensitive,
     * and wallet fields are added only after their independent permission.
     *
     * @param  array<int, string>  $modules
     * @param  Collection<int, int>  $storeIds
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, Sale>  $salesRows
     * @param  LengthAwarePaginator<Sale>|null  $salesPagination
     * @param  Collection<int, RentalAsset>  $assetRows
     * @return array<int, array{key:string,title:string,columns:array<string,string>,rows:array<int,array<string,mixed>>}>
     */
    private function detailSections(User $user, array $modules, Collection $storeIds, CarbonImmutable $from, CarbonImmutable $to, array $filters, Collection $salesRows, ?LengthAwarePaginator $salesPagination, Collection $assetRows, bool $forExport): array
    {
        $sections = [];
        $section = static function (string $key, string $title, array $columns, Collection|array $rows, ?LengthAwarePaginator $pagination = null, array $sortable = []) use (&$sections, $forExport): void {
            $sections[] = ['key' => $key, 'title' => $title, 'columns' => $columns, 'rows' => ($forExport || $pagination !== null) ? collect($rows)->values()->all() : collect($rows)->take(50)->values()->all(), 'sortable' => $sortable, 'pagination' => $pagination === null ? null : ['total' => $pagination->total(), 'current_page' => $pagination->currentPage(), 'last_page' => $pagination->lastPage()]];
        };

        if (in_array('sales', $modules, true)) {
            $section('sales', __('Sale documents'), [
                'document' => __('Document'), 'store' => __('Store'), 'cashier' => __('Cashier'),
                'gross' => __('Gross'), 'discount' => __('Discount'), 'tax' => __('Tax'), 'total' => __('Total'),
            ], $salesRows->map(fn (Sale $sale): array => [
                'document' => $sale->document_number, 'store' => $sale->store?->code, 'cashier' => $sale->cashier?->name,
                'gross' => (float) $sale->subtotal, 'discount' => (float) $sale->discount_total,
                'tax' => (float) $sale->tax_total, 'total' => (float) $sale->total,
            ]), $salesPagination, ['document' => __('Document'), 'store' => __('Store'), 'cashier' => __('Cashier'), 'gross' => __('Gross'), 'discount' => __('Discount'), 'tax' => __('Tax'), 'total' => __('Total')]);

            $saleLines = SaleLine::query()
                ->whereIn('sale_id', $salesRows->pluck('id'))
                ->when($filters['product_id'], fn (Builder $query, int $id): Builder => $query->where('product_id', $id))
                ->when($filters['category_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('category_id', $id)))
                ->with('sale:id,document_number')
                ->orderByDesc('sale_id')
                ->orderBy('line_number')
                ->limit($forExport ? self::EXPORT_ROW_LIMIT : 50)
                ->get();
            $section('sales_product_lines', __('Sale product lines'), [
                'document' => __('Document'), 'sku' => __('SKU'), 'product' => __('Product'),
                'options_ar' => __('Arabic options'), 'options_en' => __('English options'),
                'quantity' => __('Quantity'), 'net' => __('Line total'),
            ], $saleLines->map(fn (SaleLine $line): array => [
                'document' => $line->sale?->document_number,
                'sku' => $line->item_code,
                'product' => str_starts_with(app()->getLocale(), 'ar') ? $line->name_ar : $line->name_en,
                'options_ar' => $this->snapshotOptions($line->variant_snapshot, 'ar'),
                'options_en' => $this->snapshotOptions($line->variant_snapshot, 'en'),
                'quantity' => \App\Support\ProductQuantity::format($line->quantity),
                'net' => (float) $line->net_amount,
            ]));
        }

        if (in_array('inventory', $modules, true)) {
            $balances = StockBalance::query()->with(['product', 'store'])->whereIn('store_id', $storeIds)
                ->whereHas('product', function (Builder $product): void {
                    $product->where('status', 'active')->where(function (Builder $scope): void {
                        $scope->where(function (Builder $simple): void {
                            $simple->whereNull('parent_product_id')->where('has_variations', false);
                        })->orWhere(function (Builder $variant): void {
                            $variant->whereNotNull('parent_product_id')->whereHas('parent', function (Builder $family): void {
                                $family->where('status', 'active')->where('has_variations', true);
                            });
                        });
                    });
                })
                ->when($filters['product_id'], fn (Builder $query, int $id): Builder => $query->where('product_id', $id))
                ->when($filters['category_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('category_id', $id)))
                ->when($filters['brand_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('brand_id', $id)))
                ->when($filters['supplier_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product.suppliers', fn (Builder $supplier): Builder => $supplier->whereKey($id)))
                ->when($filters['age_label_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('age_label_id', $id)))
                ->when($filters['character_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('character_id', $id)))
                ->when($filters['colour_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('colour_id', $id)))
                ->when($filters['gender_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('gender_id', $id)))
                ->when($filters['product_type'], fn (Builder $query, string $value): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('product_type', $value)))
                ->when($filters['product_status'], fn (Builder $query, string $value): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('status', $value)))
                ->orderBy('product_id')->limit(50)->get();
            $canViewCost = Gate::forUser($user)->allows('inventory_stock_card.cost_view');
            $balanceColumns = ['product' => __('Product'), 'store' => __('Store'), 'on_hand' => __('On hand'), 'reserved' => __('Reserved'), 'available' => __('Available'), 'in_transit' => __('In transit')];
            if ($canViewCost) {
                $balanceColumns += ['average_cost' => __('Average cost'), 'value' => __('Stock value')];
            }
            $section('inventory_balances', __('Stock balances'), $balanceColumns, $balances->map(function (StockBalance $balance) use ($canViewCost): array {
                $row = [
                    'product' => $balance->product?->item_code, 'store' => $balance->store?->code,
                    'on_hand' => \App\Support\ProductQuantity::format($balance->on_hand), 'reserved' => \App\Support\ProductQuantity::format($balance->reserved),
                    'available' => \App\Support\ProductQuantity::format(bcsub((string)$balance->on_hand,(string)$balance->reserved,6)), 'in_transit' => \App\Support\ProductQuantity::format($balance->in_transit),
                ];
                if ($canViewCost) {
                    $row += ['average_cost' => (float) $balance->average_cost, 'value' => (float) $balance->total_value];
                }

                return $row;
            }));

            $movements = StockMovement::query()->with(['product', 'store'])->whereIn('store_id', $storeIds)->whereBetween('posted_at', [$from, $to])
                ->whereHas('product', function (Builder $product): void {
                    $product->where('status', 'active')->where(function (Builder $scope): void {
                        $scope->where(function (Builder $simple): void {
                            $simple->whereNull('parent_product_id')->where('has_variations', false);
                        })->orWhere(function (Builder $variant): void {
                            $variant->whereNotNull('parent_product_id')->whereHas('parent', function (Builder $family): void {
                                $family->where('status', 'active')->where('has_variations', true);
                            });
                        });
                    });
                })
                ->when($filters['product_id'], fn (Builder $query, int $id): Builder => $query->where('product_id', $id))
                ->when($filters['category_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('category_id', $id)))
                ->when($filters['brand_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('brand_id', $id)))
                ->when($filters['supplier_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product.suppliers', fn (Builder $supplier): Builder => $supplier->whereKey($id)))
                ->when($filters['age_label_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('age_label_id', $id)))
                ->when($filters['character_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('character_id', $id)))
                ->when($filters['colour_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('colour_id', $id)))
                ->when($filters['gender_id'], fn (Builder $query, int $id): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('gender_id', $id)))
                ->when($filters['product_type'], fn (Builder $query, string $value): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('product_type', $value)))
                ->when($filters['product_status'], fn (Builder $query, string $value): Builder => $query->whereHas('product', fn (Builder $product): Builder => $product->where('status', $value)))
                ->latest('posted_at')->limit(50)->get();
            $movementColumns = ['posted_at' => __('Posted'), 'product' => __('Product'), 'store' => __('Store'), 'type' => __('Movement'), 'quantity' => __('Quantity'), 'source' => __('Source')];
            if ($canViewCost) {
                $movementColumns += ['unit_cost' => __('Unit cost'), 'total_cost' => __('Total cost')];
            }
            $section('inventory_movements', __('Stock movements'), $movementColumns, $movements->map(function (StockMovement $movement) use ($canViewCost): array {
                $row = [
                    'posted_at' => $movement->posted_at?->toIso8601String(), 'product' => $movement->product?->item_code,
                    'store' => $movement->store?->code, 'type' => \App\Modules\Inventory\Support\InventoryMovementLabel::label($movement->movement_type), 'quantity' => \App\Support\ProductQuantity::format($movement->quantity),
                    'source' => app(\App\Modules\Platform\Support\AuditLogPresentation::class)->source($movement->source_type).':'.$movement->source_id,
                ];
                if ($canViewCost) {
                    $row += ['unit_cost' => (float) $movement->unit_cost, 'total_cost' => (float) $movement->total_cost];
                }

                return $row;
            }));

            $workflowRows = collect()
                ->merge(StockTransfer::query()->where(fn (Builder $query): Builder => $query->whereIn('source_store_id', $storeIds)->orWhereIn('destination_store_id', $storeIds))->latest('id')->limit(20)->get()->map(fn (StockTransfer $row): array => ['type' => __('Transfer'), 'document' => $row->transfer_number, 'status' => $this->status($row->status), 'difference' => filled($row->difference_status) ? $this->status($row->difference_status) : null]))
                ->merge(StockCount::query()->whereIn('store_id', $storeIds)->latest('id')->limit(15)->get()->map(fn (StockCount $row): array => ['type' => __('Stock count'), 'document' => $row->count_number, 'status' => $this->status($row->status), 'difference' => null]))
                ->merge(InventoryAdjustment::query()->whereIn('store_id', $storeIds)->latest('id')->limit(15)->get()->map(fn (InventoryAdjustment $row): array => ['type' => __('Adjustment'), 'document' => $row->adjustment_number, 'status' => $this->status($row->status), 'difference' => $this->status($row->adjustment_type)]));
            $section('inventory_workflows', __('Transfers, counts, and adjustments'), ['type' => __('Type'), 'document' => __('Document'), 'status' => __('Status'), 'difference' => __('Difference / kind')], $workflowRows);
        }

        if (in_array('purchasing', $modules, true)) {
            $supplierId = $filters['supplier_id'];
            $orders = PurchaseOrder::query()->with(['supplier', 'store'])->whereIn('store_id', $storeIds)->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('status', ['approved', 'partially_received', 'received', 'closed'])->when($supplierId, fn (Builder $query, int $id): Builder => $query->where('supplier_id', $id))->latest('id')->limit(50)->get();
            $section('purchase_orders', __('Purchase orders'), ['document' => __('Document'), 'supplier' => __('Supplier'), 'store' => __('Store'), 'status' => __('Status'), 'total' => __('Total')], $orders->map(fn (PurchaseOrder $row): array => ['document' => $row->po_number, 'supplier' => $this->localizedName($row->supplier), 'store' => $row->store?->code, 'status' => $this->status($row->status), 'total' => (float) $row->total_amount]));

            $invoices = PurchaseInvoice::query()->with(['supplier', 'store'])->whereIn('store_id', $storeIds)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
                ->where('status', 'approved')->when($supplierId, fn (Builder $query, int $id): Builder => $query->where('supplier_id', $id))->latest('id')->limit(50)->get();
            $section('purchase_invoices', __('Approved purchase invoices'), ['document' => __('Document'), 'supplier' => __('Supplier'), 'store' => __('Store'), 'date' => __('Date'), 'total' => __('Total')], $invoices->map(fn (PurchaseInvoice $row): array => ['document' => $row->invoice_number, 'supplier' => $this->localizedName($row->supplier), 'store' => $row->store?->code, 'date' => $row->invoice_date?->toDateString(), 'total' => (float) $row->total_amount]));

            $returns = PurchaseReturn::query()->with(['supplier', 'store'])->whereIn('store_id', $storeIds)->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->where('status', 'approved')->when($supplierId, fn (Builder $query, int $id): Builder => $query->where('supplier_id', $id))->latest('id')->limit(50)->get();
            $section('purchase_returns', __('Approved supplier returns'), ['document' => __('Document'), 'supplier' => __('Supplier'), 'store' => __('Store'), 'date' => __('Date'), 'total' => __('Total')], $returns->map(fn (PurchaseReturn $row): array => ['document' => $row->return_number, 'supplier' => $this->localizedName($row->supplier), 'store' => $row->store?->code, 'date' => $row->return_date?->toDateString(), 'total' => (float) $row->total_amount]));

            $supplierPrices = ProductSupplier::query()->with(['product', 'supplier'])->whereHas('product', function (Builder $product): void {
                $product->where('status', 'active')->where(function (Builder $scope): void {
                    $scope->where(function (Builder $simple): void {
                        $simple->whereNull('parent_product_id')->where('has_variations', false);
                    })->orWhere(function (Builder $variant): void {
                        $variant->whereNotNull('parent_product_id')->whereHas('parent', function (Builder $family): void {
                            $family->where('status', 'active')->where('has_variations', true);
                        });
                    });
                });
            })->when($supplierId, fn (Builder $query, int $id): Builder => $query->where('supplier_id', $id))->latest('last_purchase_date')->limit(50)->get();
            $section('supplier_prices', __('Supplier and last purchase prices'), ['product' => __('Product'), 'supplier' => __('Supplier'), 'preferred' => __('Preferred'), 'last_price' => __('Last purchase price'), 'last_date' => __('Last purchase date')], $supplierPrices->map(fn (ProductSupplier $row): array => ['product' => $row->product?->item_code, 'supplier' => $this->localizedName($row->supplier), 'preferred' => $row->is_preferred ? __('Yes') : __('No'), 'last_price' => (float) $row->last_purchase_price, 'last_date' => $row->last_purchase_date?->toDateString()]));
        }

        if (in_array('cash', $modules, true)) {
            $shifts = PosShift::query()->visibleTo($user)->with(['cashDrawer', 'cashier', 'closingSubmissions'])->whereIn('store_id', $storeIds)
                ->whereBetween('opened_at', [$from, $to])->when($filters['user_id'], fn (Builder $query, int $id): Builder => $query->where('cashier_id', $id))->latest('opened_at')->limit(50)->get();
            $canViewVariance = Gate::forUser($user)->allows('shifts_cash_movements.approve');
            $shiftColumns = ['drawer' => __('Drawer'), 'cashier' => __('Cashier'), 'opened' => __('Opened'), 'status' => __('Status')];
            if ($canViewVariance) {
                $shiftColumns += ['expected' => __('Expected'), 'actual' => __('Actual'), 'variance' => __('Variance')];
            }
            $section('cash_shifts', __('Shift closing and variance'), $shiftColumns, $shifts->map(function (PosShift $shift) use ($canViewVariance): array {
                $row = ['drawer' => $shift->cashDrawer?->code, 'cashier' => $shift->cashier?->name, 'opened' => $shift->opened_at?->toIso8601String(), 'status' => $this->status($shift->status)];
                if ($canViewVariance) {
                    $close = $shift->closingSubmissions->sortByDesc('attempt')->first();
                    $row += ['expected' => $close === null ? null : (float) $close->expected_cash, 'actual' => $close === null ? null : (float) $close->actual_cash, 'variance' => $close === null ? null : (float) $close->total_variance];
                }

                return $row;
            }));
            $movements = CashMovement::query()->whereIn('store_id', $storeIds)->whereBetween('created_at', [$from, $to])->latest('id')->limit(50)->get();
            $section('cash_movements', __('Drawer movements'), ['created' => __('Created'), 'type' => __('Movement'), 'amount' => __('Amount'), 'reference' => __('Reference')], $movements->map(fn (CashMovement $row): array => ['created' => $row->created_at?->toIso8601String(), 'type' => $this->status($row->movement_type), 'amount' => (float) $row->amount, 'reference' => $row->reference]));
        }

        if (in_array('customers', $modules, true)) {
            $customers = Customer::query()->visibleTo($user)->where(function (Builder $query) use ($storeIds): void {
                $query->whereIn('created_store_id', $storeIds)->orWhereHas('scopes', fn (Builder $scope): Builder => $scope->whereIn('store_id', $storeIds));
            })->when($filters['customer_id'], fn (Builder $query, int $id): Builder => $query->whereKey($id))->latest('id')->limit(50)->get();
            $customerColumns = ['customer' => __('Customer'), 'status' => __('Status'), 'created' => __('Created')];
            if (Gate::forUser($user)->allows('customers.sensitive')) {
                $customerColumns['phone'] = __('Phone');
            }
            $section('customers', __('Customer history'), $customerColumns, $customers->map(function (Customer $customer) use ($user): array {
                $row = ['customer' => $this->localizedName($customer), 'status' => $this->status($customer->status), 'created' => $customer->created_at?->toDateString()];
                if (Gate::forUser($user)->allows('customers.sensitive')) {
                    $row['phone'] = $customer->phone_display;
                }

                return $row;
            }));
            if (Gate::forUser($user)->allows('loyalty.view')) {
                $loyalty = LoyaltyLedger::query()->visibleTo($user)->with('customer')->whereIn('store_id', $storeIds)->whereBetween('effective_at', [$from, $to])->when($filters['customer_id'], fn (Builder $query, int $id): Builder => $query->where('customer_id', $id))->latest('effective_at')->limit(50)->get();
                $section('loyalty', __('Loyalty earn and redeem'), ['effective' => __('Effective'), 'customer' => __('Customer'), 'event' => __('Event'), 'points' => __('Points'), 'balance' => __('Balance')], $loyalty->map(fn (LoyaltyLedger $row): array => ['effective' => $row->effective_at?->toIso8601String(), 'customer' => $this->localizedName($row->customer), 'event' => $this->status($row->event_type), 'points' => $row->points, 'balance' => $row->balance_after]));
            }
            foreach ([
                ['product_wallet.view', ProductWalletLedger::class, 'product_wallet', __('Product Wallet statement')],
                ['party_wallet.view', PartyWalletLedger::class, 'party_wallet', __('Party Wallet statement')],
            ] as [$permission, $ledgerClass, $key, $title]) {
                if (! Gate::forUser($user)->allows($permission)) {
                    continue;
                }
                $ledger = $ledgerClass::query()->with('customer')->whereIn('store_id', $storeIds)->whereBetween('created_at', [$from, $to])->when($filters['customer_id'], fn (Builder $query, int $id): Builder => $query->where('customer_id', $id))->latest('created_at')->limit(50)->get();
                $section($key, $title, ['effective' => __('Effective'), 'customer' => __('Customer'), 'event' => __('Event'), 'amount' => __('Amount'), 'balance' => __('Balance')], $ledger->map(fn ($row): array => ['effective' => $row->created_at?->toIso8601String(), 'customer' => $this->localizedName($row->customer), 'event' => $this->status($row->entry_type), 'amount' => (float) $row->amount, 'balance' => (float) $row->balance_after]));
            }
            if (Gate::forUser($user)->allows('gift_cards.view') || Gate::forUser($user)->allows('returns_exchanges_gift_instruments.view')) {
                $cards = GiftCard::query()->visibleTo($user)->with('holder')->whereIn('store_id', $storeIds)->when($filters['customer_id'], fn (Builder $query, int $id): Builder => $query->where('holder_customer_id', $id))->latest('id')->limit(50)->get();
                $section('gift_cards', __('Gift Card status and use'), ['identifier' => __('Identifier'), 'holder' => __('Holder'), 'status' => __('Status'), 'issued' => __('Issued'), 'balance' => __('Balance'), 'valid_until' => __('Valid until')], $cards->map(fn (GiftCard $row): array => ['identifier' => $row->identifier, 'holder' => $this->localizedName($row->holder), 'status' => $this->status($row->status), 'issued' => (float) $row->issued_value, 'balance' => (float) $row->balance, 'valid_until' => $row->valid_until?->toDateString()]));
            }
        }

        if (in_array('parties', $modules, true)) {
            $bookings = PartyBooking::query()->visibleTo($user)->with(['customer', 'store'])->whereIn('store_id', $storeIds)->whereBetween('party_date', [$from->toDateString(), $to->toDateString()])
                ->when($filters['customer_id'], fn (Builder $query, int $id): Builder => $query->where('customer_id', $id))->when($filters['party_status'], fn (Builder $query, string $status): Builder => $query->where('status', $status))->latest('party_date')->limit(50)->get();
            $section('party_bookings', __('Party bookings'), ['booking' => __('Booking'), 'date' => __('Party date'), 'customer' => __('Customer'), 'store' => __('Store'), 'status' => __('Status')], $bookings->map(fn (PartyBooking $row): array => ['booking' => $row->booking_number, 'date' => $row->party_date?->toDateString(), 'customer' => $this->localizedName($row->customer), 'store' => $row->store?->code, 'status' => $this->status($row->status)]));

            $invoices = PartyInvoice::query()->visibleTo($user)->with('booking')->whereIn('party_booking_id', $bookings->pluck('id'))->latest('id')->limit(50)->get();
            $section('party_invoices', __('Party invoices and balances'), ['invoice' => __('Invoice'), 'booking' => __('Booking'), 'state' => __('State'), 'total' => __('Total'), 'paid' => __('Paid'), 'balance' => __('Balance')], $invoices->map(fn (PartyInvoice $row): array => ['invoice' => $row->invoice_number, 'booking' => $row->booking?->booking_number, 'state' => $this->status($row->state), 'total' => (float) $row->total_amount, 'paid' => (float) $row->paid_amount, 'balance' => (float) $row->balance_due]));

            $payments = PartyPayment::query()->whereIn('store_id', $storeIds)->whereBetween('approved_at', [$from, $to])->where('status', 'approved')->latest('approved_at')->limit(50)->get();
            $section('party_payments', __('Party payments on account'), ['receipt' => __('Receipt'), 'method' => __('Method'), 'amount' => __('Amount'), 'approved' => __('Approved')], $payments->map(fn (PartyPayment $row): array => ['receipt' => $row->receipt_number, 'method' => $row->method_code, 'amount' => (float) $row->amount, 'approved' => $row->approved_at?->toIso8601String()]));

            $orders = PartyOperatingOrder::query()->visibleTo($user)->whereIn('store_id', $storeIds)->latest('id')->limit(50)->get();
            $section('party_operations', __('Party operating orders and consumables'), ['order' => __('Order'), 'status' => __('Status'), 'released' => __('Released'), 'completed' => __('Completed')], $orders->map(fn (PartyOperatingOrder $row): array => ['order' => $row->order_number, 'status' => $this->status($row->status), 'released' => $row->released_at?->toIso8601String(), 'completed' => $row->completed_at?->toIso8601String()]));
        }

        if (in_array('assets', $modules, true)) {
            $assetIds = RentalAsset::query()->visibleTo($user)->whereIn('store_id', $storeIds)->pluck('id');
            $assetColumns = ['code' => __('Code'), 'name' => __('Name'), 'status' => __('Status'), 'condition' => __('Condition')];
            $canViewCost = Gate::forUser($user)->allows('rental_assets.cost_view');
            if ($canViewCost) {
                $assetColumns['cost'] = __('Cost');
            }
            $section('rental_assets', __('Rental asset register'), $assetColumns, $assetRows->map(function (RentalAsset $asset) use ($canViewCost): array {
                $row = ['code' => $asset->code, 'name' => $this->localizedName($asset), 'status' => $this->status($asset->status), 'condition' => $this->status($asset->condition)];
                if ($canViewCost) {
                    $row['cost'] = (float) $asset->cost_value;
                }

                return $row;
            }));
            $reservations = AssetReservation::query()->whereIn('asset_id', $assetIds)->where('starts_at', '<=', $to)->where('ends_at', '>=', $from)->latest('starts_at')->limit(50)->get();
            $section('asset_reservations', __('Asset reservations and calendar'), ['asset' => __('Asset'), 'starts' => __('Starts'), 'ends' => __('Ends'), 'status' => __('Status'), 'source' => __('Source')], $reservations->map(fn (AssetReservation $row): array => ['asset' => $row->asset_id, 'starts' => $row->starts_at?->toIso8601String(), 'ends' => $row->ends_at?->toIso8601String(), 'status' => $this->status($row->status), 'source' => $row->source_reference]));
            $returns = AssetReturn::query()->whereIn('asset_id', $assetIds)->whereBetween('returned_at', [$from, $to])->latest('returned_at')->limit(50)->get();
            $section('asset_returns', __('Asset checkout and return'), ['asset' => __('Asset'), 'returned' => __('Returned'), 'condition' => __('Condition'), 'outcome' => __('Outcome')], $returns->map(fn (AssetReturn $row): array => ['asset' => $row->asset_id, 'returned' => $row->returned_at?->toIso8601String(), 'condition' => $this->status($row->condition_after), 'outcome' => $this->status($row->outcome)]));
            $events = AssetEvent::query()->whereIn('asset_id', $assetIds)->latest('id')->limit(50)->get();
            $eventColumns = ['asset' => __('Asset'), 'event' => __('Event'), 'assessment' => __('Assessment'), 'status' => __('Status')];
            if ($canViewCost) {
                $eventColumns['cost'] = __('Cost impact');
            }
            $section('asset_events', __('Damage, depreciation, and history'), $eventColumns, $events->map(function (AssetEvent $event) use ($canViewCost): array {
                $row = ['asset' => $event->asset_id, 'event' => $this->status($event->event_type), 'assessment' => $event->assessment, 'status' => $this->status($event->status)];
                if ($canViewCost) {
                    $row['cost'] = (float) $event->cost_value;
                }

                return $row;
            }));
        }

        return $sections;
    }

    private function status(mixed $status): string
    {
        $value = $status instanceof BackedEnum ? (string) $status->value : (string) $status;

        return __((string) str($value)->replace('_', ' ')->title());
    }

    private function localizedName(?object $record): ?string
    {
        if ($record === null) {
            return null;
        }

        return str_starts_with(app()->getLocale(), 'ar')
            ? ($record->name_ar ?: $record->name_en)
            : ($record->name_en ?: $record->name_ar);
    }

    /** @param array<int, array<string, mixed>>|null $snapshot */
    private function snapshotOptions(?array $snapshot, string $locale): string
    {
        if ($snapshot === null || $snapshot === []) {
            return '';
        }

        $labels = [];
        foreach ($snapshot as $choice) {
            $group = (string) ($choice['group_'.$locale] ?? '');
            $value = (string) ($choice['value_'.$locale] ?? '');
            if ($group !== '' || $value !== '') {
                $labels[] = trim($group).': '.trim($value);
            }
        }

        return implode(' · ', $labels);
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode([
            'filters' => $snapshot['filters'],
            'modules' => $snapshot['modules'],
            'kpis' => $snapshot['kpis'],
            'sources' => $snapshot['sources'],
            'sales' => collect($snapshot['sales'])->pluck('id')->values()->all(),
            'assets' => collect($snapshot['assets'])->pluck('id')->values()->all(),
            'detail_sections' => $snapshot['detail_sections'] ?? [],
            'export' => $snapshot['export'] ?? [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dateRange(array $filters): array
    {
        try {
            $to = CarbonImmutable::parse((string) ($filters['date_to'] ?? now()->toDateString()))->endOfDay();
            $from = CarbonImmutable::parse((string) ($filters['date_from'] ?? $to->subDays(30)->toDateString()))->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages(['date_from' => __('Report dates must be valid calendar dates.')]);
        }
        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['date_from' => __('The report start must be before its end.')]);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['date_from' => __('Report ranges are limited to 366 days.')]);
        }

        return [$from, $to];
    }

    private function moduleFilter(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $module = (string) $value;
        abort_unless(array_key_exists($module, self::MODULE_PERMISSIONS), 422, __('The selected report module is not available.'));

        return $module;
    }

    private function scopedIdFilter(mixed $value, Builder $query, string $label): ?int
    {
        if (! filled($value)) {
            return null;
        }
        $id = (int) $value;
        abort_unless($id > 0 && $query->whereKey($id)->exists(), 422, __('The selected :label is not available.', ['label' => $label]));

        return $id;
    }

    /** @param array<int, string> $allowed */
    private function enumFilter(mixed $value, array $allowed): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $value = (string) $value;
        abort_unless(in_array($value, $allowed, true), 422, __('The selected report status is not available.'));

        return $value;
    }

    /** @return array<int, string> */
    private function authorizedModules(User $user, ?string $requested): array
    {
        $modules = [];
        foreach (self::MODULE_PERMISSIONS as $module => $permission) {
            if (Gate::forUser($user)->allows($permission)) {
                $modules[] = $module;
            }
        }
        if ($requested !== null && ! in_array($requested, $modules, true)) {
            abort(403);
        }

        return $requested === null ? $modules : [$requested];
    }

    /** @param Collection<int, int> $storeIds */
    private function assertUserFilter(User $viewer, int $userId, Collection $storeIds): void
    {
        if ($viewer->is_super_admin) {
            abort_unless(User::query()->whereKey($userId)->where('status', 'active')->exists(), 403);

            return;
        }

        $visible = User::query()->whereKey($userId)->where('status', 'active')->where(function (Builder $query) use ($storeIds): void {
            $query->whereHas('storeScopes', fn (Builder $scope): Builder => $scope->where('status', 'active')->whereIn('store_id', $storeIds))
                ->orWhereHas('branchScopes', fn (Builder $scope): Builder => $scope->where('status', 'active')->whereIn('branch_id', Store::query()->whereIn('id', $storeIds)->select('branch_id')));
        })->exists();
        abort_unless($visible, 403);
    }
}
