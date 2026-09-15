<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class V021PurchasingLocalizationLayoutHotfixTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_every_purchase_invoice_literal_has_an_arabic_translation(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');
        preg_match_all("/__\\('([^']+)'/", $view, $matches);
        $translations = json_decode($this->source('lang/ar.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach (array_unique($matches[1]) as $key) {
            self::assertArrayHasKey($key, $translations, $key);
            self::assertNotSame($key, $translations[$key], $key);
        }
    }

    public function test_list_and_entry_layout_contracts_are_responsive_and_locale_safe(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');
        self::assertStringContainsString('lg:grid-cols-[minmax(11rem,1.25fr)', $view);
        self::assertStringNotContainsString('responsive-resource-table', $view);
        self::assertStringContainsString('<x-product-line-lookup', $view);
        self::assertStringNotContainsString("__('Product pending')", $view);
        self::assertStringContainsString("formatQuantity(", $view);
        self::assertStringContainsString('data-invoice-metadata', $view);
        self::assertStringContainsString('dir="ltr"', $view);
    }

    public function test_status_and_currency_presenters_never_leak_raw_values(): void
    {
        $badge = $this->source('resources/views/components/status/badge.blade.php');
        $money = $this->source('resources/views/components/money.blade.php');
        self::assertStringContainsString("'awaiting_distribution' => __('Awaiting Distribution')", $badge);
        self::assertStringContainsString("'reversed' => __('Reversed')", $badge);
        self::assertStringContainsString("str_starts_with(app()->getLocale(), 'ar') ? 'ج.م' : 'EGP'", $money);
    }

    public function test_production_uat_requires_explicit_environment_confirmation_and_reuses_sequence(): void
    {
        $service = $this->source('app/Modules/Platform/Services/UatDataset.php');
        self::assertStringContainsString("getenv('RAJEH_UAT_ALLOW_PRODUCTION')!=='CONFIRMED'", $service);
        self::assertStringContainsString("where('document_type', 'purchase_invoice')->where('status', 'active')->exists()", $service);
        self::assertStringNotContainsString("insertGetId(['document_type'=>'purchase_invoice'", $service);
        self::assertStringNotContainsString("mark(\$batch,'document_sequences'", $service);
        self::assertStringNotContainsString("DB::table('document_sequences')->insert", $service);
    }
}
