<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Translation\FileLoader;

final class V0122Hotfix12PosShiftOpenRouteTest extends TestCase
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app->setLocale('zz');

        $app->forgetInstance('translation.loader');
        $app->forgetInstance('translator');
        $app->singleton('translation.loader', fn ($app) => new FileLoader(
            $app['files'], [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']],
        ));

        return $app;
    }

    public function test_shift_open_flow_uses_separate_safe_routes_and_preserves_authorization(): void
    {
        $routes = app('router')->getRoutes();
        $compatibilityRoute = $routes->getByName('pos.shift.open');
        $storeRoute = $routes->getByName('pos.shift.store');

        self::assertNotNull($compatibilityRoute);
        self::assertSame('pos/shift/open', $compatibilityRoute->uri());
        self::assertSame(['GET', 'HEAD'], $compatibilityRoute->methods());
        self::assertContains('auth', $compatibilityRoute->gatherMiddleware());
        self::assertContains('verified', $compatibilityRoute->gatherMiddleware());
        self::assertContains('can:pos_sales.view', $compatibilityRoute->gatherMiddleware());
        self::assertContains('can:shifts_cash_movements.view', $compatibilityRoute->gatherMiddleware());
        self::assertContains('can:shifts_cash_movements.create', $compatibilityRoute->gatherMiddleware());

        self::assertNotNull($storeRoute);
        self::assertSame('pos/shift/open', $storeRoute->uri());
        self::assertSame(['POST'], $storeRoute->methods());
        self::assertContains('auth', $storeRoute->gatherMiddleware());
        self::assertContains('verified', $storeRoute->gatherMiddleware());
        self::assertContains('can:shifts_cash_movements.create', $storeRoute->gatherMiddleware());

        $shiftView = (string) file_get_contents(resource_path('views/pages/pos/shift.blade.php'));
        $retailRoutes = (string) file_get_contents(base_path('routes/retail.php'));
        $openAction = (string) file_get_contents(app_path('Modules/Retail/Actions/OpenShiftAction.php'));

        self::assertStringContainsString("action=\"{{ route('pos.shift.store') }}\"", $shiftView);
        self::assertStringContainsString("return to_route('pos')->with('success', __('Shift opened.'));", $retailRoutes);
        self::assertStringContainsString('visibleTo($cashier)->whereKey($drawer->getKey())->exists()', $openAction);
        self::assertStringContainsString("active()->where('cashier_id', \$cashier->id)->exists()", $openAction);
        self::assertStringContainsString("active()->where('cash_drawer_id', \$drawer->getKey())->exists()", $openAction);

        $this->get(route('pos.shift.open'))
            ->assertRedirect(route('login'));

        $authorized = new User([
            'name' => 'Authorized cashier',
            'email' => 'authorized-cashier@example.test',
            'status' => 'active',
        ]);
        $authorized->forceFill([
            'id' => 910001,
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($authorized)
            ->get(route('pos.shift.open'))
            ->assertRedirect(route('pos.shift'));

        $unauthorized = new User([
            'name' => 'Inactive cashier',
            'email' => 'inactive-cashier@example.test',
            'status' => 'inactive',
        ]);
        $unauthorized->forceFill([
            'id' => 910002,
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($unauthorized)
            ->get(route('pos.shift.open'))
            ->assertForbidden();
    }
}
