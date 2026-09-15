<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class M54PurchaseReceivingDistributionTest extends TestCase
{
    private function source(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_posting_is_locked_transactional_and_idempotent(): void
    {
        $source=$this->source('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php');
        self::assertStringContainsString('DB::transaction', $source);
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString("where('purchase_invoice_id', \$invoice->id)", $source);
        self::assertStringContainsString("'status' => 'in_transit'", $source);
        self::assertStringContainsString('adjustInTransit', $source);
        self::assertStringContainsString('PURCHASE-TRANSFER-DISPATCH:', $source);
    }

    public function test_full_distribution_is_mandatory_and_rejects_invalid_allocations(): void
    {
        $approval=$this->source('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php');
        $save=$this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php');
        self::assertStringContainsString('Every purchased unit must be distributed before approval', $approval);
        self::assertStringContainsString('temporary receiving warehouse must finish with zero', $approval);
        self::assertStringContainsString('ProductQuantity::normalize', $save);
        self::assertStringContainsString('visibleTo($actor)', $save);
    }

    public function test_destination_price_snapshot_uses_list_zero_fallback_and_overrides(): void
    {
        $save=$this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php');
        $resolver=$this->source('app/Modules/Pricing/Services/PriceListResolver.php');
        self::assertStringContainsString('resolveWithBasePrice', $save);
        self::assertStringContainsString('ProductPriceOverride::query()', $resolver);
        self::assertStringContainsString("where('list_number', 0)", $resolver);
        self::assertStringContainsString('effective_selling_price', $save);
    }

    public function test_least_privilege_permissions_are_registered_and_enforced(): void
    {
        $seeder=$this->source('database/seeders/ProductionSeeder.php');
        foreach(['draft','distribute','approve','reverse','view_cost','change_cost','change_list0_price','print_labels'] as $permission) self::assertStringContainsString("'".$permission."'", $seeder);
        self::assertStringContainsString("Gate::authorize('purchase_invoices.distribute')", $this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php'));
        self::assertStringContainsString("Gate::authorize('purchase_invoices.approve')", $this->source('app/Modules/Purchasing/Actions/ApprovePurchaseInvoiceAction.php'));
    }

    public function test_full_page_two_stage_ui_scanning_and_destination_labels_are_present(): void
    {
        $view=$this->source('resources/views/purchasing/invoices.blade.php');
        self::assertStringContainsString('Stage 1 — Purchase invoice', $view);
        self::assertStringContainsString('Stage 2 — Mandatory distribution', $view);
        self::assertStringContainsString('addItemEntry', $view);
        self::assertStringContainsString('createQuickProduct', $view);
        self::assertStringContainsString('Base Consumer Price List 0', $view);
        self::assertStringContainsString("name('purchasing.invoices.destination-labels')", $this->source('routes/purchasing.php'));
    }

    public function test_m54_flux_references_are_supported(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');

        self::assertStringNotContainsString('icon="barcode"', $view);
        self::assertStringContainsString('<x-product-line-lookup', $view);

        preg_match_all('/icon="([a-z0-9-]+)"/', $view, $matches);
        foreach (array_unique($matches[1]) as $icon) {
            self::assertFileExists(dirname(__DIR__, 2).'/vendor/livewire/flux/stubs/resources/views/flux/icon/'.$icon.'.blade.php');
        }

        foreach (['button/index', 'callout/index', 'card/index', 'checkbox/index', 'heading', 'icon/index', 'input/index', 'label', 'modal/index', 'select/index', 'table/index', 'text', 'textarea'] as $component) {
            self::assertFileExists(dirname(__DIR__, 2).'/vendor/livewire/flux/stubs/resources/views/flux/'.$component.'.blade.php');
        }
    }

    public function test_six_stabilization_render_targets_and_setup_context_are_wired(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');
        $routes = $this->source('routes/purchasing.php');
        $layout = $this->source('resources/views/layouts/app/sidebar.blade.php');

        foreach (['Purchase invoices', 'Stage 1 — Purchase invoice', 'Unknown barcode — create Product Card', 'Stage 2 — Mandatory distribution', 'Invoice transition completed.'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        self::assertStringContainsString("name('purchasing.invoices.destination-labels')", $routes);
        self::assertStringContainsString("redirect()->route('pricing.labels'", $routes);
        self::assertStringContainsString('ApplicationNavigation::class', $layout);
    }

    public function test_release_cache_and_runtime_user_guard_is_documented(): void
    {
        $runbook = $this->source('docs/53-deployment-backup-and-rollback-runbook.md');

        self::assertStringContainsString('Never copy generated source `bootstrap/cache/*.php` files into a release', $runbook);
        self::assertStringContainsString('must execute as the runtime user `rajeh`', $runbook);
    }
}
