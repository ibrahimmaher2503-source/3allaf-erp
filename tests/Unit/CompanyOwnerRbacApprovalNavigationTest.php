<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Platform\Support\CompanyOwnerPermissionProfile;
use PHPUnit\Framework\TestCase;

final class CompanyOwnerRbacApprovalNavigationTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    protected function setUp(): void
    {
        parent::setUp();

        require_once self::ROOT.'/app/Modules/Platform/Support/CompanyOwnerPermissionProfile.php';
    }

    public function test_company_owner_profile_includes_company_permissions_and_excludes_privileged_capabilities(): void
    {
        self::assertTrue(CompanyOwnerPermissionProfile::allows('company_settings.edit', 'company_settings'));
        self::assertTrue(CompanyOwnerPermissionProfile::allows('pricing_lists.approve', 'pricing_lists'));
        self::assertTrue(CompanyOwnerPermissionProfile::allows('stock_counts.reconcile', 'stock_counts'));
        self::assertTrue(CompanyOwnerPermissionProfile::allows('manage-authorization', 'authorization'));

        self::assertFalse(CompanyOwnerPermissionProfile::allows('audit_logs.view', 'audit_logs'));
        self::assertFalse(CompanyOwnerPermissionProfile::allows('view-platform-status', 'platform'));
        self::assertFalse(CompanyOwnerPermissionProfile::allows('infrastructure.backup', 'infrastructure'));
        self::assertFalse(CompanyOwnerPermissionProfile::allows('orders.cross-company', 'orders'));
        self::assertFalse(CompanyOwnerPermissionProfile::allows('users.impersonate', 'users'));

        $seeder = $this->source('database/seeders/ProductionSeeder.php');
        self::assertStringContainsString('app(CompanyOwnerPermissionProfile::class)->synchronize();', $seeder);
        self::assertStringNotContainsString("'karim'", strtolower($seeder));
    }

    public function test_administration_activity_and_approval_navigation_have_exact_guards_and_placement(): void
    {
        $navigation = require self::ROOT.'/config/navigation.php';
        $groups = [];
        $approvalItems = [];

        foreach ($navigation as $group) {
            $groups[$group['key']] = $group;
            foreach ($group['items'] as $item) {
                if ($item['route'] === 'admin.approvals') {
                    $approvalItems[] = $item;
                }
            }
        }

        self::assertSame(
            ['dashboard', 'alerts.index', 'admin.approvals'],
            array_column(array_slice($groups['home']['items'], 0, 3), 'route'),
        );
        self::assertCount(1, $approvalItems);
        self::assertSame('view-approval-inbox', $approvalItems[0]['permission']);

        $administration = array_column($groups['administration']['items'], null, 'route');
        self::assertSame('access-administration-center', $administration['admin.overview']['permission']);
        self::assertSame('access-activity-log', $administration['admin.audit']['permission']);
        self::assertArrayNotHasKey('admin.approvals', $administration);

        $routes = $this->source('routes/platform.php');
        self::assertStringContainsString("middleware('can:access-administration-center')->name('admin.overview')", $routes);
        self::assertStringContainsString("middleware('can:access-activity-log')->name('admin.audit')", $routes);
        self::assertStringContainsString("middleware('can:access-activity-log')->name('admin.audit.export')", $routes);
        self::assertStringContainsString("middleware('can:view-approval-inbox')->name('admin.approvals')", $routes);

        $provider = $this->source('app/Providers/AppServiceProvider.php');
        self::assertStringContainsString("in_array(\$ability, ['access-administration-center', 'access-activity-log'], true)", $provider);
        self::assertStringContainsString("return \$user->is_super_admin;", $provider);

        $inbox = $this->source('resources/views/platform/system/approval-inbox.blade.php');
        self::assertSame(2, substr_count($inbox, "Gate::authorize('view-approval-inbox')"));
    }

    public function test_company_user_management_is_scope_bounded_and_global_roles_are_protected(): void
    {
        $scope = $this->source('app/Modules/Platform/Support/UserManagementScope.php');
        self::assertStringContainsString("->where('is_super_admin', false)", $scope);
        self::assertStringContainsString("ROOT_MANAGED_ROLE_CODES", $scope);
        self::assertStringContainsString("whereNotIn('branch_id', \$branchIds)", $scope);
        self::assertStringContainsString("whereNotIn('store_id', \$storeIds)", $scope);
        self::assertStringContainsString('CompanyOwnerPermissionProfile::allows', $scope);
        self::assertStringContainsString('assertAssignmentsAreManageable', $scope);

        $saveUser = $this->source('app/Modules/Platform/Actions/SaveUserAuthorizationAction.php');
        self::assertStringContainsString('bool $creating = false', $saveUser);
        self::assertStringContainsString('$scope->assertTargetIsManageable($actor, $user);', $saveUser);
        self::assertStringContainsString('$scope->assertAssignmentsAreManageable($actor, $roleIds, $branchIds, $storeIds);', $saveUser);

        $view = $this->source('resources/views/platform/admin/authorization-baseline.blade.php');
        self::assertStringContainsString('$scope->visibleUsers($actor)', $view);
        self::assertStringContainsString('$scope->assignableRoles($actor)', $view);
        self::assertStringContainsString("@can('manage-global-role-catalog')", $view);

        $routes = $this->source('routes/platform.php');
        self::assertSame(2, substr_count($routes, "middleware('can:manage-global-role-catalog')"));
    }

    public function test_karim_provisioner_is_interactive_idempotent_and_does_not_expose_credentials(): void
    {
        $command = $this->source('app/Console/Commands/ProvisionKarimCompanyOwner.php');

        self::assertStringContainsString("private const USERNAME = 'karim';", $command);
        self::assertStringContainsString("protected \$signature = 'company-owner:provision-karim';", $command);
        self::assertStringContainsString("posix_geteuid() !== 0", $command);
        self::assertSame(5, substr_count($command, '$this->secret('));
        self::assertStringContainsString("where('username', self::USERNAME)->lockForUpdate()->first()", $command);
        self::assertStringContainsString("Hash::make((string) \$validated['password'])", $command);
        self::assertStringContainsString("'is_super_admin' => false", $command);
        self::assertStringContainsString('$profile->synchronize()', $command);
        self::assertStringContainsString("DB::table('sessions')->where('user_id', \$user->id)->delete()", $command);
        self::assertStringContainsString('No credentials were displayed.', $command);
        self::assertStringNotContainsString('--password', $command);
        self::assertStringNotContainsString('password=', strtolower($command));
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(self::ROOT.'/'.$path);
    }
}
