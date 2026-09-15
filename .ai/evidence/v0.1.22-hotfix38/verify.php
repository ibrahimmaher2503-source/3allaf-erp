<?php

declare(strict_types=1);

use App\Modules\Platform\Actions\SavePrintTemplateAction;
use App\Modules\Platform\Support\DocumentTypeCatalog;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix38', 'Version mismatch.');
    foreach (['ar', 'en', 'ar-EG'] as $locale) {
        $map = json_decode((string) file_get_contents($root."/lang/{$locale}.json"), true, 512, JSON_THROW_ON_ERROR);
        foreach (['Add Payment Method', 'Add Tax', 'Product-specific tax', 'Add Numbering Rule', 'Printer and template help', 'Relevant printers'] as $key) {
            $assert(filled($map[$key] ?? null), "{$locale} is missing {$key}.");
        }
        echo "HOTFIX38_LOCALE=PASS locale={$locale}\n";
    }

    $migration = (string) file_get_contents($root.'/database/migrations/2026_09_14_000112_add_tax_setting_id_to_products.php');
    foreach (["foreignId('tax_setting_id')", "constrained('tax_settings')", 'nullable()', 'nullOnDelete()'] as $needle) {
        $assert(str_contains($migration, $needle), "Migration omitted {$needle}.");
    }
    foreach (['sales', 'sale_lines', 'purchase_invoices', 'purchase_invoice_lines'] as $historicalTable) {
        $assert(! str_contains($migration, "Schema::table('{$historicalTable}'"), "Migration changes historical {$historicalTable} snapshots.");
    }

    $settings = (string) file_get_contents($root.'/resources/views/platform/admin/settings.blade.php');
    foreach (['paymentModalOpen', 'taxModalOpen', 'sequenceModalOpen', 'Add Payment Method', 'Add Tax', 'Add Numbering Rule', 'DocumentTypeCatalog::prefix', 'printerModalOpen', 'templateModalOpen', 'relevant_printers_count'] as $needle) {
        $assert(str_contains($settings, $needle), "Settings UI omitted {$needle}.");
    }
    $assert(! str_contains($settings, 'wire:model="taxSettingForm.effective_from"') && ! str_contains($settings, 'wire:model="taxSettingForm.effective_to"'), 'Effective dates remain visible.');

    $product = (string) file_get_contents($root.'/app/Modules/Catalog/Actions/SaveProductAction.php');
    foreach (['tax_setting_id', "where('status', 'active')", 'Company::query()->count() !== 1'] as $needle) {
        $assert(str_contains($product, $needle), "Product tax validation omitted {$needle}.");
    }
    $assert(count(SavePrintTemplateAction::DOCUMENT_TYPES) === 15, 'Print type registry does not contain 15 types.');
    $assert(count(DocumentTypeCatalog::NUMBERING_TYPES) >= 15, 'Numbering type registry omits existing document types.');
    $assert(DocumentTypeCatalog::prefix('BR1', 'retail_sale') === 'BR1-SI-', 'Deterministic numbering prefix mismatch.');

    $allocation = (string) file_get_contents($root.'/app/Modules/Platform/Actions/AllocateDocumentNumber.php');
    $sequenceMigration = (string) file_get_contents($root.'/database/migrations/2026_08_19_000002_add_cf13_cf14_contracts.php');
    $assert(str_contains($allocation, 'lockForUpdate()'), 'Sequence allocation does not use a row lock.');
    foreach (glob($root.'/app/Modules/*/Actions/*.php') ?: [] as $actionFile) {
        if (str_ends_with($actionFile, '/AllocatePurchaseInvoiceNumberAction.php')
            || str_ends_with($actionFile, '/AllocatePurchaseOrderNumberAction.php')
            || str_ends_with($actionFile, '/AllocatePurchaseReturnNumberAction.php')) {
            continue;
        }
        $assert(! str_contains((string) file_get_contents($actionFile), 'AllocateDocumentNumber::class)->execute('), 'A branch-owned document still bypasses branch-aware allocation: '.basename($actionFile));
    }
    $assert(str_contains($sequenceMigration, "unique(['document_type', 'scope_key']"), 'Sequence database uniqueness is missing.');

    echo "HOTFIX38_FOCUSED_VERIFICATION=PASS database_mutation=none\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX38_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
