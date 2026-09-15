<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class V0122Hotfix13CashDrawerIntegrityTest extends TestCase
{
    public function test_valid_drawer_opening_keeps_transaction_scope_and_duplicate_guards(): void
    {
        $action = $this->source('app/Modules/Retail/Actions/OpenShiftAction.php');

        self::assertStringContainsString('DB::transaction(function ()', $action);
        self::assertStringContainsString('lockForUpdate()->find((int) $drawer->getKey())', $action);
        self::assertStringContainsString('visibleTo($cashier)->whereKey($drawer->getKey())->exists()', $action);
        self::assertStringContainsString("where('cash_drawer_id', \$drawer->getKey())->whereIn('status', \$activeStates)->exists()", $action);
        self::assertStringContainsString("where('cashier_id', \$cashier->id)->whereIn('status', \$activeStates)->exists()", $action);
        self::assertStringContainsString("'status' => ShiftState::Open->value", $action);
        self::assertStringContainsString("'opening_cash' => \$float", $action);
        self::assertStringContainsString("DB::table('active_pos_shift_assignments')->insert", $action);
        self::assertStringContainsString("event: 'open_shift'", $action);
    }

    public function test_every_application_write_derives_company_from_store_and_rejects_mismatch(): void
    {
        $model = $this->source('app/Modules/Platform/Models/CashDrawer.php');
        $save = $this->source('app/Modules/Platform/Actions/SaveCashDrawerAction.php');
        $uat = $this->source('app/Modules/Platform/Services/UatDataset.php');

        self::assertStringContainsString('static::saving(function (CashDrawer $drawer)', $model);
        self::assertStringContainsString("(int) \$store->getAttribute('branch_id') !== \$branchId", $model);
        self::assertStringContainsString("(int) \$store->getAttribute('company_id') !== (int) \$branch->getAttribute('company_id')", $model);
        self::assertStringContainsString("\$drawer->setAttribute('company_id', (int) \$store->getAttribute('company_id'))", $model);
        self::assertStringContainsString("'company_id' => \$store?->company_id ?? \$branch->company_id", $save);
        self::assertStringNotContainsString("'company_id' => \$data", $save);
        self::assertStringContainsString("['company_id'=>(int)\$outlet->company_id,'branch_id'=>(int)\$outlet->branch_id", $uat);
    }

    public function test_legacy_migration_repairs_only_unambiguous_store_ownership_and_fails_closed(): void
    {
        $migration = $this->source('database/migrations/2026_09_04_000107_enforce_cash_drawer_context_integrity.php');

        self::assertStringContainsString("DB::table('cash_drawers')->orderBy('id')", $migration);
        self::assertStringContainsString('$updates[$drawerId] = $storeCompanyId;', $migration);
        self::assertStringContainsString("'drawer_store_branch_mismatch'", $migration);
        self::assertStringContainsString("'store_branch_company_mismatch'", $migration);
        self::assertStringContainsString('Cash drawer integrity audit failed; no drawers were changed.', $migration);
        self::assertLessThan(strpos($migration, 'DB::transaction(function () use ($updates)'), strpos($migration, "if (\$issues !== [])"));
        self::assertStringContainsString("CHECK (company_id > 0)", $migration);
        self::assertStringContainsString("->foreign('company_id')->references('id')->on('companies')->restrictOnDelete()", $migration);
        self::assertStringContainsString("->unsignedBigInteger('company_id')->nullable(false)->change()", $migration);
    }

    public function test_invalid_or_cross_scope_drawers_are_not_selectable_and_return_safe_validation(): void
    {
        $model = $this->source('app/Modules/Platform/Models/CashDrawer.php');
        $routes = $this->source('routes/retail.php');
        $action = $this->source('app/Modules/Retail/Actions/OpenShiftAction.php');
        $message = 'The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.';

        self::assertStringContainsString("whereIn('branch_id', \$user->branchScopes()->where('status', 'active')", $model);
        self::assertStringContainsString("orWhereIn('store_id', \$user->storeScopes()->where('status', 'active')", $model);
        self::assertStringContainsString("->whereColumn('branches.company_id', 'cash_drawers.company_id')", $model);
        self::assertStringContainsString("->whereColumn('stores.company_id', 'cash_drawers.company_id')", $model);
        self::assertStringContainsString("->whereColumn('stores.branch_id', 'cash_drawers.branch_id')", $model);
        self::assertStringContainsString('visibleTo($user)->operationallyConsistent()', $routes);
        self::assertStringContainsString('visibleTo($cashier)->whereKey($drawer->getKey())->exists()', $action);
        self::assertStringContainsString($message, $routes);
        self::assertStringContainsString($message, $action);
        self::assertStringNotContainsString("Company::query()->findOrFail((int) \$drawer->getAttribute('company_id'))", $action);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        self::assertIsString($source);

        return $source;
    }
}
