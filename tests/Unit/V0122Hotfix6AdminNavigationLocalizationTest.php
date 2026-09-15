<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Platform\Support\InitialSetupRouteMap;
use App\Modules\Platform\Support\InitialSetupStepRegistry;
use PHPUnit\Framework\TestCase;

final class V0122Hotfix6AdminNavigationLocalizationTest extends TestCase
{
    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_canonical_setup_groups_contain_every_real_step_once(): void
    {
        $groups = InitialSetupRouteMap::groups();
        $keys = array_merge(...array_values($groups));

        self::assertSame(['basics', 'settings', 'operations'], array_keys($groups));
        self::assertCount(5, $groups['basics']);
        self::assertCount(5, $groups['settings']);
        self::assertCount(10, $groups['operations']);
        self::assertCount(20, $keys);
        self::assertCount(20, array_unique($keys));
        self::assertContains('company', $groups['basics']);
    }

    public function test_sidebar_derives_nested_authorized_links_from_the_lightweight_registry(): void
    {
        $navigation = $this->source('app/Modules/Platform/Support/ApplicationNavigation.php');
        $view = $this->source('resources/views/components/app-navigation.blade.php');

        self::assertCount(21, InitialSetupStepRegistry::steps());
        self::assertCount(20, InitialSetupStepRegistry::activeSteps());
        self::assertStringNotContainsString('InitialSetupStatus::class)->snapshot()', $navigation);
        self::assertStringContainsString('$user->is_super_admin ? null : app(RequestPermissionLookup::class)->for($user)', $navigation);
        self::assertStringContainsString('InitialSetupStepRegistry::groups()', $navigation);
        self::assertStringContainsString("'setup_step' => \$stepKey", $navigation);
        self::assertStringContainsString("request()->query('setup_step') === \$stepKey", $navigation);
        self::assertStringContainsString('data-setup-subcategory', $view);
        self::assertStringContainsString('data-setup-step', $view);
        self::assertStringNotContainsString('status_label', $view);
    }

    public function test_audit_list_uses_centralized_safe_presentations(): void
    {
        $presenter = $this->source('app/Modules/Platform/Support/AuditLogPresentation.php');
        $view = $this->source('resources/views/platform/system/audit-log.blade.php');

        foreach (['create_store', 'map_branch_selling_store', 'create_payment_method', 'update_local_settings'] as $event) {
            self::assertStringContainsString("'$event' =>", $presenter);
        }
        foreach (['Store', 'BranchSellingStore', 'PaymentMethod', 'Company'] as $source) {
            self::assertStringContainsString("'$source' =>", $presenter);
        }
        self::assertStringContainsString("?? 'Other audit event'", $presenter);
        self::assertStringContainsString("?? 'Other source record'", $presenter);
        self::assertStringNotContainsString('AUDIT_EXPORT_MAX_ROWS', $view);
        self::assertStringNotContainsString('{{ $log->event }}', $view);
        self::assertStringNotContainsString('class_basename((string) $log->source_type)', $view);
        self::assertStringContainsString("__('Source record ID')", $view);
    }

    public function test_warehouse_terminology_and_store_action_are_localized(): void
    {
        $arabic = $this->source('lang/ar.json');
        $english = $this->source('lang/en.json');

        self::assertDoesNotMatchRegularExpression('/مستودع|المستودعات/u', $arabic);
        self::assertStringContainsString('إضافة مخزن / منفذ بيع', $arabic);
        self::assertStringContainsString('Add Warehouse / Point of Sale', $english);
        self::assertStringContainsString("0.1.22-hotfix41", $this->source('app/Support/ApplicationVersion.php'));
    }
}
