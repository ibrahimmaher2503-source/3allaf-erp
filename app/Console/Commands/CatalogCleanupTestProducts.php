<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductDependencyService;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CatalogCleanupTestProducts extends Command
{
    protected $signature = 'catalog:cleanup-test-products {ids* : Explicit audited product IDs} {--company-id=1} {--company-code=TOY&JOY-01} {--confirm=}';
    protected $description = 'Root-only explicit deletion of confirmed dependency-free QA/UAT products.';

    public function handle(ProductDependencyService $dependencies): int
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) { $this->error('This cleanup command must be run as root.'); return self::FAILURE; }
        if (! hash_equals('DELETE-DEPENDENCY-FREE-TEST-PRODUCTS', (string) $this->option('confirm'))) { $this->error('Exact cleanup confirmation token is required.'); return self::FAILURE; }
        $company = Company::query()->whereKey((int) $this->option('company-id'))->where('code', (string) $this->option('company-code'))->where('status', 'active')->first();
        if ($company === null) { $this->error('Exact active company identity was not verified.'); return self::FAILURE; }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->argument('ids')))));
        if ($ids === []) { $this->error('At least one explicit audited product ID is required.'); return self::FAILURE; }

        return DB::transaction(function () use ($company, $dependencies, $ids): int {
            $products = Product::query()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($products->count() !== count($ids)) { $this->error('One or more explicit product IDs no longer exist.'); return self::FAILURE; }
            foreach ($products as $product) {
                if (! preg_match('/(?:^|\b)(?:QA|TEST|UAT|DEMO)(?:-|\b)/i', $product->item_code.' '.$product->name_en)) { $this->error('Product '.$product->id.' is not explicitly QA/UAT-labelled.'); return self::FAILURE; }
                $report = $dependencies->inspect($product);
                if (! $report['permanent_delete_allowed']) { $this->error('Product '.$product->id.' now has dependencies; cleanup aborted.'); return self::FAILURE; }
                try {
                    $dependencies->assertCompanyScope((int) $company->id, $product);
                } catch (\Throwable $exception) {
                    $this->error('Product '.$product->id.' failed the centralized company-scope guard: '.$exception->getMessage());
                    return self::FAILURE;
                }
            }
            foreach ($products as $product) {
                $before = $product->only(['id', 'item_code', 'name_ar', 'name_en', 'status']); $report = $dependencies->inspect($product);
                $dependencies->removeConfiguration($product); $product->authorizePermanentRemoval()->delete();
                app(RecordAuditEvent::class)->execute('master_data', 'root_cleanup_dependency_free_test_product', $product, before: $before, metadata: ['company_id' => $company->id, 'dependency_counts' => $report['counts'], 'command' => 'catalog:cleanup-test-products']);
                $this->line('DELETED_PRODUCT_ID='.$product->id);
            }
            return self::SUCCESS;
        });
    }
}
