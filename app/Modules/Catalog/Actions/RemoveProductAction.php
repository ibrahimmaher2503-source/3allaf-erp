<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductDependencyService;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RemoveProductAction
{
    /** @return array{outcome:string,report:array<string,mixed>} */
    public function execute(int $productId, bool $permanent): array
    {
        Gate::authorize('products_categories_brands.logical_delete');
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));

        return DB::transaction(function () use ($actor, $company, $productId, $permanent): array {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $dependencies = app(ProductDependencyService::class); $report = $dependencies->inspect($product);
            $identity = $product->only(['id', 'item_code', 'name_ar', 'name_en', 'status', 'lock_version']);
            if ($permanent) {
                if (! $report['permanent_delete_allowed']) throw ValidationException::withMessages(['product' => __('Permanent deletion is blocked because this product has operational or historical dependencies. Archive it instead.')]);
                $dependencies->assertCompanyScope((int) $company->id, $product);
                $dependencies->removeConfiguration($product);
                $product->authorizePermanentRemoval()->delete();
                app(RecordAuditEvent::class)->execute('master_data', 'permanently_delete_dependency_free_product', $product, before: $identity, metadata: ['company_id' => $company->id, 'dependency_counts' => $report['counts'], 'configuration_counts' => $report['configuration_counts'], 'actor_id' => $actor->id]);
                return ['outcome' => 'deleted', 'report' => $report];
            }
            if ($product->status === 'inactive') return ['outcome' => 'archived', 'report' => $report];
            $product->update(['status' => 'inactive', 'lock_version' => $product->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('master_data', 'archive_product', $product, before: $identity, after: $product->fresh()->only(['id', 'item_code', 'status', 'lock_version']), metadata: ['company_id' => $company->id, 'dependency_counts' => $report['counts'], 'actor_id' => $actor->id]);
            return ['outcome' => 'archived', 'report' => $report];
        });
    }
}
