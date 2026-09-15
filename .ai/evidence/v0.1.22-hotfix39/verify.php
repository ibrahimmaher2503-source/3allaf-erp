<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Support\DocumentTypeCatalog;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); };

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix39', 'Version mismatch.');

    foreach (['ar', 'en', 'ar-EG'] as $locale) {
        $map = json_decode((string) file_get_contents($root."/lang/{$locale}.json"), true, 512, JSON_THROW_ON_ERROR);
        foreach (['Supplier product code', 'Opening inventory header', 'Save as Draft', 'Resume Draft', 'Main category', 'Loyalty behavior'] as $key) {
            $assert(filled($map[$key] ?? null), "{$locale} is missing {$key}.");
        }
        echo "HOTFIX39_LOCALE=PASS locale={$locale}\n";
    }

    $migration = (string) file_get_contents($root.'/database/migrations/2026_09_14_000113_extend_opening_inventory_and_supplier_contacts_for_hotfix39.php');
    foreach (["foreignId('branch_id')", "foreignId('store_id')", "date('document_date')", "string('mobile'", "text('notes'", 'nullOnDelete()', 'insertOrIgnore', "'reset_rule' => 'never'"] as $needle) {
        $assert(str_contains($migration, $needle), "Migration omitted {$needle}.");
    }
    $assert(! str_contains($migration, 'dropColumn') && ! str_contains($migration, 'dropIfExists'), 'Rollback must retain nullable additions.');
    $assert(DocumentTypeCatalog::prefix('BR1', 'opening_inventory') === 'BR1-OI-', 'Opening numbering prefix mismatch.');

    $opening = (string) file_get_contents($root.'/app/Modules/Inventory/Actions/SaveOpeningInventoryDraftAction.php');
    foreach (['lockForUpdate()', "executeForBranch('opening_inventory'", 'unitCost(', 'document_date'] as $needle) $assert(str_contains($opening, $needle), "Opening action omitted {$needle}.");
    $lookup = (string) file_get_contents($root.'/app/Modules/Inventory/Queries/SearchAssignableProducts.php');
    foreach (['name_ar', 'name_en', 'item_code', 'model_number', 'supplier_item_code', 'barcode'] as $needle) $assert(str_contains($lookup, $needle), "Lookup omitted {$needle}.");

    $productUi = (string) file_get_contents($root.'/resources/views/catalog/products.blade.php');
    $assert(! str_contains($productUi, "route('catalog.data-exchange.export', ['type' => 'products'"), 'Product export controls remain visible.');
    foreach (['Excel import', "__('Draft')", "__('Resume')", 'supplier_item_code'] as $needle) $assert(str_contains($productUi, $needle), "Product UI omitted {$needle}.");
    $supplierUi = (string) file_get_contents($root.'/resources/views/catalog/suppliers.blade.php');
    $assert(! str_contains($supplierUi, "route('catalog.suppliers.export'"), 'Supplier export controls remain visible.');
    $assert(! str_contains($supplierUi, 'wire:model="supplierForm.payment_terms"'), 'Payment terms remain visible.');
    foreach (['supplierContactForm.mobile', 'supplierContactForm.notes', "__('Owner')", "__('Accountant')", "__('Sales representative')"] as $needle) $assert(str_contains($supplierUi, $needle), "Supplier UI omitted {$needle}.");

    echo "HOTFIX39_FOCUSED_VERIFICATION=PASS database_mutation=none\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX39_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
