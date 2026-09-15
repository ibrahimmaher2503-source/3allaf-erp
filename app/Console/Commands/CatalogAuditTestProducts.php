<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductDependencyService;
use App\Modules\Platform\Models\Company;
use Illuminate\Console\Command;

final class CatalogAuditTestProducts extends Command
{
    protected $signature = 'catalog:audit-test-products {--company-id=1} {--company-code=TOY&JOY-01}';
    protected $description = 'Root-only read-only dependency audit for explicitly named QA/UAT catalog products.';

    public function handle(ProductDependencyService $dependencies): int
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) { $this->error('This production audit command must be run as root.'); return self::FAILURE; }
        $company = Company::query()->whereKey((int) $this->option('company-id'))->where('code', (string) $this->option('company-code'))->where('status', 'active')->first();
        if ($company === null) { $this->error('Exact active company identity was not verified. No data was read beyond the identity check.'); return self::FAILURE; }
        $products = Product::query()->where(function ($query): void {
            $query->where('name_en', 'like', '%QA Product%')->orWhere('name_en', 'like', '%TEST-Golden Product%')
                ->orWhere('name_en', 'like', '%UAT%')->orWhere('name_en', 'like', '%DEMO%')
                ->orWhere('item_code', 'like', 'TEST-%')->orWhere('item_code', 'like', 'QA-%')
                ->orWhere('item_code', 'like', 'UAT-%')->orWhere('item_code', 'like', 'DEMO-%');
        })->orderBy('id')->get();
        $this->line('MODE=READ_ONLY'); $this->line('COMPANY_ID='.$company->id); $this->line('COMPANY_CODE='.$company->code); $this->line('MATCHES='.$products->count());
        foreach ($products as $product) $this->line(json_encode(['id' => $product->id, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'status' => $product->status, 'dependencies' => $dependencies->inspect($product)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
