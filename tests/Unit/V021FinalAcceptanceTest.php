<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class V021FinalAcceptanceTest extends TestCase
{
    private function source(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_scanner_and_bounded_search_contract(): void
    {
        $view=$this->source('resources/views/purchasing/invoices.blade.php');
        self::assertStringContainsString('<x-product-line-lookup',$view);
        self::assertStringContainsString('x-on:product-selected',$view);
        self::assertStringContainsString('invoice-line-focus',$view);
        self::assertStringContainsString("mb_strlen(\$term) < 3",$view);
        self::assertStringContainsString('wire:model.live.debounce.300ms="search"',$view);
        self::assertStringContainsString("->limit(20)->get()",$view);
        self::assertStringNotContainsString("limit(1000)",$view);
        foreach(['item_code','model_number','name_ar','name_en','barcodes'] as $field)self::assertStringContainsString($field,$view);
    }

    public function test_draft_continuation_and_state_actions_are_explicit(): void
    {
        $view=$this->source('resources/views/purchasing/invoices.blade.php');
        foreach(['Complete invoice','Review and distribute quantities','Approve and post invoice','Current stage','Next required action','Remaining quantity'] as $text)self::assertStringContainsString($text,$view);
        self::assertStringContainsString("with(['supplier', 'store', 'lines.distributions'",$view);
        self::assertStringContainsString("'lock_version' => \$invoice->lock_version",$view);
    }

    public function test_uat_commands_are_explicit_idempotent_and_production_guarded(): void
    {
        $service=$this->source('app/Modules/Platform/Services/UatDataset.php');
        $seed=$this->source('app/Console/Commands/SeedUatDataset.php');
        $purge=$this->source('app/Console/Commands/PurgeUatDataset.php');
        self::assertStringContainsString('rajeh:uat-seed',$seed);
        self::assertStringContainsString('rajeh:uat-purge',$purge);
        self::assertStringContainsString("where('company_id', \$company->id)->where('status', 'active')",$service);
        self::assertStringContainsString("if (\$existing)",$service);
        self::assertStringContainsString("environment('production')",$service);
        self::assertStringContainsString('Real purge requires --confirm.',$service);
        self::assertStringContainsString("in_array(\$record->table_name,self::TABLES,true)",$service);
        self::assertStringContainsString("where('company_id',\$companyId)",$service);
    }

    public function test_uat_records_are_visibly_marked_and_coverage_is_documented(): void
    {
        $service=$this->source('app/Modules/Platform/Services/UatDataset.php');
        self::assertStringContainsString('TEST-',$service);
        self::assertStringContainsString('اختبار —',$service);
        self::assertFileExists(dirname(__DIR__,2).'/docs/operations/RAJEH_UAT_GOLDEN_PATH.md');
        self::assertFileExists(dirname(__DIR__,2).'/.ai/V0_1_21_UAT_COVERAGE.md');
        self::assertStringContainsString("update(['source_version'", $this->source('app/Modules/Purchasing/Actions/SavePurchaseDistributionAction.php'));
    }
}
