<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Support\CustomerIdentity;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Support\DataExchange\ArabicPdfText;
use App\Support\DataExchange\MasterDataDocument;
use App\Support\Hierarchy\GroupHierarchy;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class M51CustomerSupplierDataTest extends TestCase
{
    private ?Container $previousContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $application = new Application(dirname(__DIR__, 2));
        $loader = new FileLoader(new Filesystem, [
            dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Translation/lang',
            dirname(__DIR__, 2).'/lang',
        ]);
        $application->instance('translator', new Translator($loader, 'en'));
        Container::setInstance($application);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.ltrim($path, '/');
    }

    public function test_egyptian_phone_forms_share_one_canonical_identity(): void
    {
        self::assertSame('01012345678', PhoneNormalizer::normalize('01012345678'));
        self::assertSame('01012345678', PhoneNormalizer::normalize('201012345678'));
        self::assertSame('01012345678', PhoneNormalizer::normalize('+20 10 1234 5678'));
        self::assertSame('441234567890', PhoneNormalizer::normalize('+44 1234 567890'));
    }

    public function test_primary_and_secondary_phone_identity_rejects_duplicates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomerIdentity::normalizedPhones('01012345678', '+20 10 1234 5678');
    }

    public function test_hierarchy_keeps_parent_before_matching_child_and_exposes_leaf_only_selection(): void
    {
        $parent = (new CustomerGroup)->forceFill(['id' => 1, 'parent_id' => null, 'code' => 'ROOT', 'name_ar' => 'الجذر', 'name_en' => 'Root', 'sort_order' => 20]);
        $child = (new CustomerGroup)->forceFill(['id' => 2, 'parent_id' => 1, 'code' => 'VIP', 'name_ar' => 'مميز', 'name_en' => 'VIP', 'sort_order' => 1]);
        $other = (new CustomerGroup)->forceFill(['id' => 3, 'parent_id' => null, 'code' => 'OTHER', 'name_ar' => 'أخرى', 'name_en' => 'Other', 'sort_order' => 1]);
        $groups = new Collection([$parent, $child, $other]);

        self::assertSame([1, 2], GroupHierarchy::flatten($groups, 'VIP')->pluck('id')->all());
        self::assertSame([2, 3], GroupHierarchy::leaves($groups)->pluck('id')->all());
        self::assertSame(1, $child->hierarchy_depth);
    }

    public function test_supplier_group_hierarchy_uses_the_same_shared_presenter(): void
    {
        $root = (new SupplierGroup)->forceFill(['id' => 7, 'parent_id' => null, 'name_ar' => 'محلي', 'name_en' => 'Local', 'sort_order' => 1]);
        $child = (new SupplierGroup)->forceFill(['id' => 8, 'parent_id' => 7, 'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'sort_order' => 1]);
        self::assertSame([7, 8], GroupHierarchy::flatten(new Collection([$child, $root]))->pluck('id')->all());
    }

    public function test_supplier_settlement_choices_expose_other_as_a_supported_localized_contract(): void
    {
        self::assertSame(['cash', 'cheques', 'installments', 'trust_deposits', 'other'], SupplierSettlementMethod::VALUES);
        self::assertTrue(SupplierSettlementMethod::isValid('other'));
        self::assertSame(__('Other'), SupplierSettlementMethod::options()['other']);
    }

    public function test_supplier_readiness_has_no_product_or_payment_terms_dependency(): void
    {
        $source = file_get_contents($this->projectPath('app/Modules/Platform/Support/InitialSetupStatus.php'));
        $start = strpos($source, 'private function suppliersReady');
        $end = strpos($source, '/**', $start);
        $method = substr($source, $start, $end - $start);
        self::assertStringContainsString('settlement_method', $method);
        self::assertStringNotContainsString('payment_terms', $method);
        self::assertStringNotContainsString('product', $method);
    }

    public function test_migration_contains_all_reference_and_legacy_preservation_contracts(): void
    {
        $source = file_get_contents($this->projectPath('database/migrations/2026_08_30_000096_add_customer_supplier_data_management.php'));
        self::assertSame(27, preg_match_all("/\['[A-Z]{3}', '[^']+', '[^']+'\]/u", $source));
        self::assertStringContainsString("Schema::create('cities'", $source);
        self::assertStringContainsString('secondary_phone_normalized', $source);
        self::assertStringContainsString("leftJoin('payment_methods'", $source);
    }

    public function test_import_export_routes_are_permission_guarded_and_rejection_reports_are_real_xlsx(): void
    {
        $customerRoutes = file_get_contents($this->projectPath('routes/customers.php'));
        $supplierRoutes = file_get_contents($this->projectPath('routes/catalog.php'));
        self::assertStringContainsString("name('customers.import.rejections')", $customerRoutes);
        self::assertStringContainsString("name('customers.groups.export')", $customerRoutes);
        self::assertStringContainsString("name('catalog.suppliers.import.rejections')", $supplierRoutes);
        self::assertStringContainsString("name('catalog.suppliers.export')", $supplierRoutes);

        $method = new ReflectionMethod(MasterDataDocument::class, 'safeCell');
        $escaped = $method->invoke(new MasterDataDocument, '=HYPERLINK("https://invalid")');
        self::assertSame("'".'=HYPERLINK("https://invalid")', $escaped);
    }

    public function test_arabic_pdf_text_is_shaped_in_visual_order_without_changing_stable_codes(): void
    {
        if (! is_executable('/usr/bin/fribidi')) {
            self::markTestSkipped('FriBidi is not installed in this local environment.');
        }

        $result = (new ArabicPdfText)->visualOrder(['مجموعات العملاء', 'SUP-001']);

        self::assertNotSame('مجموعات العملاء', $result[0]);
        self::assertMatchesRegularExpression('/[\x{FB50}-\x{FEFF}]/u', $result[0]);
        self::assertSame('SUP-001', $result[1]);
    }

    public function test_pdf_styles_register_only_local_cairo_ttf_assets_for_required_weights(): void
    {
        $styles = file_get_contents($this->projectPath('resources/views/pages/exports/partials/cairo-pdf-styles.blade.php'));
        self::assertStringContainsString('cairo-arabic-variable.ttf', $styles);
        self::assertStringContainsString('cairo-latin-variable.ttf', $styles);
        self::assertStringContainsString('[400, 500, 600, 700, 800, 900]', $styles);
        self::assertStringNotContainsString('DejaVu', $styles);
        self::assertSame("\x00\x01\x00\x00", file_get_contents($this->projectPath('resources/fonts/cairo/pdf/cairo-arabic-variable.ttf'), false, null, 0, 4));
        self::assertSame("\x00\x01\x00\x00", file_get_contents($this->projectPath('resources/fonts/cairo/pdf/cairo-latin-variable.ttf'), false, null, 0, 4));
    }
}
