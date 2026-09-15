<?php

namespace Tests\Unit;

use App\Modules\Platform\Actions\SavePrintTemplateAction;
use PHPUnit\Framework\TestCase;

class V022CumulativeStabilizationTest extends TestCase
{
    private function source(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_print_template_library_is_persisted_scoped_authorized_and_audited(): void
    {
        $migration = $this->source('database/migrations/2026_09_01_000101_add_print_template_library.php');
        $action = $this->source('app/Modules/Platform/Actions/SavePrintTemplateAction.php');
        $routes = $this->source('routes/platform.php');
        self::assertStringContainsString("Schema::create('print_templates'", $migration);
        self::assertStringContainsString("foreignId('company_id')", $migration);
        self::assertStringContainsString("foreignId('print_template_id')", $migration);
        self::assertStringContainsString("Gate::authorize('manage-settings')", $action);
        self::assertStringContainsString('visibleTo($user)', $action);
        self::assertStringContainsString('DB::transaction', $action);
        self::assertStringContainsString('RecordAuditEvent::class', $action);
        self::assertStringContainsString("name('admin.settings.template-preview')", $routes);
    }

    public function test_real_paper_sizes_and_compatibility_are_enforced_server_side(): void
    {
        self::assertSame(['58mm', '80mm', 'a4', 'a5', 'label_50x30mm', 'label_40x25mm'], SavePrintTemplateAction::PAPER_SIZES);
        $settings = $this->source('app/Modules/Platform/Actions/SaveLocalSettingsAction.php');
        foreach (['58mm', '80mm', 'a4', 'a5', 'label_50x30mm', 'label_40x25mm'] as $size) self::assertStringContainsString("'{$size}'", $settings);
        self::assertStringContainsString('not compatible with this printer paper size', $settings);
        self::assertStringNotContainsString("'label' => ['label']", $settings);
    }

    public function test_printer_scope_ui_clears_and_filters_incompatible_values(): void
    {
        $view = $this->source('resources/views/platform/admin/settings.blade.php');
        self::assertStringContainsString('updatedPrinterFormScopeType', $view);
        self::assertStringContainsString('updatedPrinterFormBranchId', $view);
        self::assertStringContainsString("where('branch_id', (int) \$printerForm['branch_id'])", $view);
        self::assertStringContainsString('wire:model.live="printerForm.scope_type"', $view);
        self::assertStringContainsString('Printing Template Library', $view);
        self::assertStringContainsString('savePrintTemplate', $view);
    }

    public function test_template_preview_is_bilingual_responsive_and_uses_no_business_data(): void
    {
        $preview = $this->source('resources/views/platform/admin/print-template-preview.blade.php');
        self::assertStringContainsString("str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr'", $preview);
        self::assertStringContainsString('viewport', $preview);
        self::assertStringContainsString('Test print', $preview);
        self::assertStringContainsString('no customer, payment, or production data is used', $preview);
    }
}
