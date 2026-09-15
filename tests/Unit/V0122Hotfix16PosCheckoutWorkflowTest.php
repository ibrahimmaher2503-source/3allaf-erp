<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class V0122Hotfix16PosCheckoutWorkflowTest extends TestCase
{
    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents, $path);

        return $contents;
    }

    public function test_open_orders_are_durable_bounded_and_exactly_scoped(): void
    {
        $migration = $this->source('database/migrations/2026_09_06_000110_create_pos_open_orders.php');
        $manager = $this->source('app/Modules/Retail/Support/PosOpenOrderManager.php');
        $model = $this->source('app/Modules/Retail/Models/PosOpenOrder.php');

        foreach (['pos_open_orders', 'pos_open_order_lines', 'pos_open_order_payments'] as $table) {
            self::assertStringContainsString("Schema::create('{$table}'", $migration);
        }
        foreach (['company_id', 'branch_id', 'store_id', 'cash_drawer_id', 'shift_id', 'cashier_id', 'checkout_token'] as $scope) {
            self::assertStringContainsString("'{$scope}'", $migration);
            self::assertStringContainsString("->where('{$scope}'", $manager);
        }
        self::assertStringContainsString('public const MAX_OPEN_ORDERS = 50;', $manager);
        self::assertStringContainsString('->limit(self::MAX_OPEN_ORDERS)', $manager);
        self::assertStringContainsString('lockForUpdate()', $manager);
        self::assertStringContainsString("where('checkout_token', \$creationToken)", $manager);
        self::assertStringContainsString("'draft_payload' => \$line", $manager);
        self::assertStringContainsString('paymentDrafts()->delete()', $manager);
        self::assertStringContainsString('->open()', $manager);
        self::assertStringContainsString("return \$query->where('status', 'open');", $model);
    }

    public function test_checkout_delegates_atomically_to_existing_sale_contract(): void
    {
        $completion = $this->source('app/Modules/Retail/Actions/CompletePosOpenOrderAction.php');
        $sale = $this->source('app/Modules/Retail/Actions/RetailSaleAction.php');
        $payment = $this->source('app/Modules/Retail/Actions/CapturePaymentAction.php');
        $routes = $this->source('routes/pos-hotfix16.php');

        self::assertStringContainsString('DB::transaction(', $completion);
        self::assertStringContainsString('lockForUpdate()', $completion);
        self::assertStringContainsString('$this->sales->create(', $completion);
        self::assertStringContainsString("(\$suspend ? 'SUSPEND:' : 'CHECKOUT:')", $completion);
        self::assertStringContainsString("'status' => \$suspend ? 'suspended' : 'completed'", $completion);
        self::assertStringContainsString('completed_sale_id', $completion);

        foreach (['assertPricesRemainCurrent', 'assertOpenPriceApprovalsRemainCurrent', 'assertDiscountApprovalsRemainCurrent', 'PostInventoryMovement', 'captureTenders', 'finalize'] as $contract) {
            self::assertStringContainsString($contract, $sale);
        }
        self::assertStringContainsString("whereIn('customer_type', ['credit', 'both'])", $payment);
        self::assertStringContainsString("throw new InvalidArgumentException(__('An electronic payment cannot exceed the outstanding amount.'))", $payment);
        self::assertStringContainsString("->where('idempotency_key'", $sale);

        self::assertStringContainsString("'payments' => ['sometimes', 'array', 'max:12']", $routes);
        self::assertStringContainsString("'payment_mode' => ['required', 'in:cash,card,split,other,credit']", $routes);
        self::assertStringContainsString("\$validated['payment_mode'] === 'credit'", $routes);
        self::assertStringContainsString('if ($cashCount > 1', $routes);
        self::assertStringContainsString('PaymentReferenceGuard::normalize', $routes);
        self::assertStringContainsString('$orders->savePaymentDraft(', $routes);
        self::assertStringContainsString('$sales->execute($user, $context, $order, $tenders, false', $routes);
    }

    public function test_customer_and_payment_ux_is_bounded_responsive_and_safe(): void
    {
        $routes = $this->source('routes/pos-hotfix16.php');
        $customers = $this->source('routes/pos-hotfix16-customers.php');
        $page = $this->source('resources/views/pages/pos/index.blade.php');
        $checkout = $this->source('resources/views/livewire/pos/checkout-panel.blade.php');
        $guard = $this->source('app/Modules/Retail/Support/PaymentReferenceGuard.php');

        foreach (['name_ar', 'name_en', 'phone_normalized', 'secondary_phone_normalized', 'public_id', 'email'] as $identifier) {
            self::assertStringContainsString($identifier, $routes);
        }
        self::assertStringContainsString('->limit(12)', $routes);
        self::assertStringContainsString('visibleFrom($user', $customers);
        self::assertStringContainsString('$orders->setCustomer(', $customers);
        self::assertStringContainsString("route('pos.customer.create')", $page);
        self::assertStringContainsString('data-pos-workstation', $page);
        self::assertStringContainsString('lg:grid-cols-', $page);
        self::assertStringContainsString('xl:grid-cols-', $page);

        foreach (['Cash payment', 'Card / electronic payment', 'Mixed split payment', 'Cash received', 'Exact amount', 'Change due to customer', 'Add card portion', 'Complete sale'] as $label) {
            self::assertStringContainsString($label, $checkout);
        }
        self::assertStringContainsString("chooseMode('credit')", $checkout);
        self::assertStringContainsString('No payment now; balance on customer account', $checkout);
        self::assertStringContainsString('data-noncash-amount', $checkout);
        self::assertStringContainsString('amountRemaining()', $checkout);
        self::assertStringContainsString('changeDue()', $checkout);
        self::assertStringContainsString("config('pos.cash_quick_denominations'", $checkout);
        self::assertStringNotContainsString('name="pan"', strtolower($checkout));
        self::assertStringNotContainsString('name="cvv"', strtolower($checkout));
        self::assertStringNotContainsString('name="track', strtolower($checkout));
        self::assertStringContainsString('passesLuhn', $guard);
    }

    public function test_success_receipt_routes_and_locale_parity_are_complete(): void
    {
        $routes = $this->source('routes/pos-hotfix16.php');
        $retail = $this->source('routes/retail.php');
        $page = $this->source('resources/views/pages/pos/index.blade.php');
        $profile = $this->source('app/Modules/Retail/Support/PosReceiptProfile.php');
        $thermal = $this->source('resources/views/pages/sales/thermal.blade.php');
        $a4 = $this->source('resources/views/pages/sales/print.blade.php');
        $ar = json_decode($this->source('lang/ar.json'), true, 512, JSON_THROW_ON_ERROR);
        $en = json_decode($this->source('lang/en.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach (["->name('pos')", "->name('pos.checkout')", "->name('pos.suspend')", "->name('pos.receipt.preview')"] as $name) {
            self::assertStringContainsString($name, $routes);
        }
        self::assertStringContainsString("require __DIR__.'/pos-hotfix16.php';", $retail);
        self::assertStringContainsString('pos/session-legacy', $retail);
        foreach (['Sale completed successfully', 'Invoice / order number', 'Print receipt', 'New order', 'View current order'] as $label) {
            self::assertStringContainsString($label, $page);
        }

        foreach (['visibleTo($actor)', "document_type', 'sales_invoice'", "whereColumn('print_templates.paper_size'", "whereIn('language'", 'browser_fallback'] as $contract) {
            self::assertStringContainsString($contract, $profile);
        }
        foreach ([$thermal, $a4] as $receipt) {
            self::assertStringContainsString("route('pos.orders.store')", $receipt);
            self::assertStringContainsString("route('sales.show', \$sale)", $receipt);
            self::assertStringContainsString('window.print()', $receipt);
            self::assertStringNotContainsString('$payment->method_code', $receipt);
        }

        foreach (['Sale completed successfully', 'Print receipt', 'View current order', 'Cash checkout', 'Card / electronic checkout', 'Mixed split payment', 'Open customer orders', 'The new-order request is invalid.'] as $key) {
            self::assertArrayHasKey($key, $ar);
            self::assertArrayHasKey($key, $en);
            self::assertNotSame($key, $ar[$key]);
            self::assertSame($key, $en[$key]);
        }
        self::assertStringContainsString("public const RELEASE = '0.1.22-hotfix41';", $this->source('app/Support/ApplicationVersion.php'));
    }
}
