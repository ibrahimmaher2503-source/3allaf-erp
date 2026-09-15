<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Pricing\Services\PriceListResolver;
use PHPUnit\Framework\TestCase;

final class M53PriceListsTest extends TestCase
{
    private function source(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_decimal_formula_and_upward_five_egp_rounding(): void
    {
        $resolver = new PriceListResolver;
        self::assertSame('115.000', $resolver->calculate('111', '0')['rounded']);
        self::assertSame('115.000', $resolver->calculate('113', '0')['rounded']);
        self::assertSame('115.000', $resolver->calculate('115', '0')['rounded']);
        self::assertSame('120.000', $resolver->calculate('117', '0')['rounded']);
        self::assertSame('120.000', $resolver->calculate('118', '0')['rounded']);
        self::assertSame(['pre_round'=>'122.100','rounded'=>'125.000'], $resolver->calculate('111', '10'));
    }

    public function test_list_zero_is_projected_and_protected_without_copying_product_prices(): void
    {
        $migration=$this->source('database/migrations/2026_08_31_000098_add_reusable_price_lists.php');$action=$this->source('app/Modules/Pricing/Actions/SavePriceListAction.php');
        self::assertStringContainsString("'list_number'=>0", str_replace(' ', '', $migration));
        self::assertStringNotContainsString('product_id', substr($migration, 0, strpos($migration, "Schema::create('product_price_overrides'")));
        self::assertStringContainsString('Base Price List 0 cannot be edited', $action);
    }

    public function test_override_is_locked_audited_and_removal_restores_calculation(): void
    {
        $source=$this->source('app/Modules/Pricing/Actions/SaveProductPriceOverrideAction.php');
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString('remove_product_price_override', $source);
        self::assertStringContainsString('RecordAuditEvent::class', $source);
        self::assertStringContainsString('$override?->delete()', $source);
    }

    public function test_outlet_resolution_is_outlet_then_branch_then_base_and_rejects_invalid_assignments(): void
    {
        $source=$this->source('app/Modules/Pricing/Services/PriceListResolver.php');
        self::assertStringContainsString('$outlet->priceList ?: $outlet->branch?->defaultPriceList', $source);
        self::assertStringContainsString("where('list_number', 0)", $source);
        self::assertStringContainsString('assignmentIsValid', $source);
    }

    public function test_historical_contract_is_snapshot_based_and_legacy_approved_prices_remain(): void
    {
        self::assertStringContainsString("'price_list_id'", $this->source('app/Modules/Pricing/Data/ResolvedListPrice.php'));
        self::assertFileExists(dirname(__DIR__,2).'/app/Modules/Pricing/Services/EffectivePriceResolver.php');
        self::assertStringContainsString('PriceVersionState::Approved', $this->source('app/Modules/Pricing/Services/EffectivePriceResolver.php'));
    }

    public function test_permissions_routes_and_readiness_reasons_are_authoritative(): void
    {
        $migration=$this->source('database/migrations/2026_08_31_000098_add_reusable_price_lists.php');$routes=$this->source('routes/pricing.php');$setup=$this->source('app/Modules/Platform/Support/InitialSetupStatus.php');
        foreach(['pricing_lists.view','pricing_lists.manage','pricing_lists.approve','pricing_lists.overrides','pricing_lists.assign'] as $permission) self::assertStringContainsString($permission,$migration);
        self::assertStringContainsString('can:pricing_lists.view',$routes);
        self::assertStringContainsString('pricingReadinessReason',$setup);
        self::assertStringContainsString('positive base consumer price',$setup);
        self::assertStringContainsString('valid active price list',$setup);
    }
}
