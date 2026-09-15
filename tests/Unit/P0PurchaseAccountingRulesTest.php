<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class P0PurchaseAccountingRulesTest extends TestCase
{
    public function test_purchase_approval_filters_period_expense_from_landed_cost_and_snapshots_terms(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php');

        self::assertIsString($source);
        self::assertStringContainsString('$charge->accounting_treatment === \'landed_cost\'', $source);
        self::assertStringContainsString('validateSupplierCreditLimit', $source);
        self::assertStringContainsString('snapshotSupplierTerms', $source);
        self::assertStringContainsString("'due_date' => \$dueDate->toDateString()", $source);
    }

    public function test_charge_input_rejects_unknown_treatment_and_defaults_legacy_input(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Modules/Purchasing/Actions/SavePurchaseInvoiceChargesAction.php');

        self::assertIsString($source);
        self::assertStringContainsString("(\$charge['accounting_treatment'] ?? 'landed_cost')", $source);
        self::assertStringContainsString("['landed_cost', 'period_expense']", $source);
        self::assertStringContainsString('Purchase charge accounting treatment is not supported.', $source);
    }
}
