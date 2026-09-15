<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Platform\Support\InitialSetupRouteMap;
use App\Modules\Platform\Support\SetupContinuation;
use Database\Seeders\ProductionSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class V016SetupAdminFoundationTest extends TestCase
{
    public function test_authoritative_group_map_contains_exactly_twenty_one_unique_steps(): void
    {
        $groups = InitialSetupRouteMap::groups();
        self::assertCount(5, $groups['basics']);
        self::assertCount(5, $groups['settings']);
        self::assertCount(10, $groups['operations']);
        self::assertCount(20, array_unique(array_merge(...array_values($groups))));
        self::assertNotContains('product-import', array_merge(...array_values($groups)));
        self::assertSame(['prices', 'opening-configuration'], array_slice($groups['operations'], -2));
    }

    public function test_continuation_bypasses_skipped_and_deferred_until_no_actionable_step_remains(): void
    {
        $steps = new Collection([
            ['key' => 'optional', 'can_access' => true, 'complete' => false, 'required' => false, 'decision' => 'skipped'],
            ['key' => 'required-later', 'can_access' => true, 'complete' => false, 'required' => true, 'decision' => 'deferred'],
            ['key' => 'actionable', 'can_access' => true, 'complete' => false, 'required' => true, 'decision' => null],
        ]);
        self::assertSame('actionable', (new SetupContinuation)->next($steps)['key']);
        $steps->pop();
        self::assertSame('required-later', (new SetupContinuation)->next($steps)['key']);
    }

    public function test_direct_route_context_resolves_without_setup_query_parameters(): void
    {
        $request = Request::create('/admin/stores');
        $route = new Route(['GET'], 'admin/stores', static fn () => null);
        $route->name('admin.stores');
        $request->setRouteResolver(static fn () => $route);
        self::assertSame('warehouses', (new InitialSetupRouteMap)->resolve($request));
    }

    public function test_cashier_template_is_least_privilege_and_uses_only_existing_operational_permissions(): void
    {
        $permissions = ProductionSeeder::productionSafeRolePermissions()['cashier'];
        self::assertContains('pos_sales.create', $permissions);
        self::assertContains('shifts_cash_movements.submit', $permissions);
        self::assertContains('pos_sales.payment_create', $permissions);
        self::assertNotContains('company_settings.edit', $permissions);
        self::assertNotContains('users_roles_permissions.edit', $permissions);
        self::assertNotContains('purchase_orders.create', $permissions);
        self::assertNotContains('pricing_labels.edit', $permissions);
    }
}
