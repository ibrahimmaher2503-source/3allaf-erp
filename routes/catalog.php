<?php

use App\Http\Controllers\TransactionProductSearchController;
use App\Modules\Catalog\Actions\DownloadProductImportErrorsAction;
use App\Modules\Catalog\Actions\StageCatalogReferenceImportAction;
use App\Modules\Catalog\Actions\StageSupplierImportAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierImportBatch;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Store;
use App\Support\DataExchange\ImportTemplateFactory;
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->get('transaction-products/search', TransactionProductSearchController::class)
        ->name('transaction-products.search');
    $router->livewire('catalog/products', 'catalog::products')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products');

    $router->livewire('catalog/products/import', 'catalog::product-import')
        ->middleware('can:products_categories_brands.create')
        ->name('catalog.products.import');

    $router->livewire('catalog/product-options', 'catalog::product-options')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.product-options');

    $router->view('catalog/lookups', 'catalog.lookups')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.lookups');

    $router->get('catalog/products/import/{batch}/errors', function (ProductImportBatch $batch, DownloadProductImportErrorsAction $action) {
        return $action->execute($batch);
    })->whereNumber('batch')->middleware('can:products_categories_brands.export')->name('catalog.products.import.errors');

    $router->get('catalog/products/import/{batch}/source/{attachment}', function (ProductImportBatch $batch, Attachment $attachment) {
        abort_unless($attachment->purpose === 'import_source' && $attachment->source_type === ProductImportBatch::class && $attachment->source_id === (string) $batch->id, 404);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $candidate): bool => Gate::forUser($user)->allows('products_categories_brands.view')
                && $candidate->source_type === ProductImportBatch::class
                && $candidate->source_id === (string) $batch->id,
        );
    })->whereNumber('batch')->middleware('can:products_categories_brands.view')->name('catalog.products.import.source');

    $router->livewire('catalog/products/create', 'catalog::product-form')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products.create');

    $router->livewire('catalog/products/{product}/edit', 'catalog::product-form')
        ->whereNumber('product')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products.edit');

    $router->livewire('catalog/products/{product}', 'catalog::product-detail')
        ->whereNumber('product')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.products.show');

    $router->get('catalog/products/{product}/media/{attachment}', function (Product $product, Attachment $attachment) {
        Gate::authorize('products_categories_brands.view');

        $authorized = $attachment->purpose === 'product_image'
            && $attachment->source_type === Product::class
            && $attachment->source_id === (string) $product->id
            && $product->images()->where('attachment_id', $attachment->id)->exists();

        if (! $authorized) {
            abort(403);
        }

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $sourceAttachment): bool => Gate::forUser($user)->allows('products_categories_brands.view')
                && $sourceAttachment->purpose === 'product_image'
                && $sourceAttachment->source_type === Product::class
                && $sourceAttachment->source_id === (string) $product->id
                && $product->images()->where('attachment_id', $sourceAttachment->id)->exists(),
        );
    })->whereNumber('product')->name('catalog.products.media');

    $router->livewire('catalog/categories', 'catalog::categories')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.categories');

    $router->livewire('catalog/brands', 'catalog::brands')
        ->middleware('can:products_categories_brands.view')
        ->name('catalog.brands');

    $router->livewire('catalog/suppliers', 'catalog::suppliers')
        ->middleware('can:suppliers.view')
        ->name('catalog.suppliers');

    $router->livewire('catalog/suppliers/import', 'catalog::supplier-import')
        ->middleware('can:suppliers.create')->name('catalog.suppliers.import');
    $router->get('catalog/suppliers/import/template', fn (ImportTemplateFactory $templates) => $templates->supplier(auth()->user(), request('company_id')))->middleware('can:suppliers.create')->name('catalog.suppliers.import.template');
    $router->get('catalog/suppliers/import/{batch}/rejections', function (SupplierImportBatch $batch, MasterDataDocument $documents) {
        $companyIds = Store::query()->visibleTo(auth()->user())->select('company_id');
        abort_unless(SupplierImportBatch::query()->whereKey($batch->id)->whereIn('company_id', $companyIds)->exists()
            && ((int) $batch->created_by === (int) auth()->id() || auth()->user()->can('suppliers.edit')), 404);
        $headers = [__('Original row'), ...StageSupplierImportAction::HEADERS, __('Rejection reason')];
        $rows = $batch->rows()->where('status', 'invalid')->orderBy('row_number')->get()->map(fn ($row) => [$row->row_number, ...array_map(fn ($field) => $row->raw_data[$field] ?? '', StageSupplierImportAction::HEADERS), collect($row->errors)->join(' | ')]);

        return $documents->xlsx('supplier-import-rejections-'.$batch->id.'.xlsx', $headers, $rows);
    })->middleware('can:suppliers.view')->name('catalog.suppliers.import.rejections');
    $router->get('catalog/suppliers/export/{format}', function (string $format, MasterDataDocument $documents) {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $query = Supplier::query()->with('supplierGroup.parent')->limit(5000);
        $term = trim((string) request('q'));
        if ($term !== '') {
            $query->where(fn ($q) => $q->where('code', 'like', '%'.$term.'%')->orWhere('name_ar', 'like', '%'.$term.'%')->orWhere('name_en', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%'));
        }
        if (in_array(request('status'), ['active', 'inactive'], true)) {
            $query->where('status', request('status'));
        }
        if ((int) request('group_id') > 0) {
            $query->whereHas('supplierGroup', fn ($group) => $group->whereKey((int) request('group_id')));
        }
        $headers = [__('Code'), __('Arabic name'), __('English name'), __('Phone'), __('Email'), __('Group path'), __('Supplier Settlement Method'), __('Payment terms'), __('Status')];
        $rows = $query->get()->map(fn ($s) => [$s->code, $s->name_ar, $s->name_en, $s->phone, $s->email, collect([$s->supplierGroup?->parent?->name_ar, $s->supplierGroup?->name_ar])->filter()->join(' / '), __($s->settlement_method), $s->payment_terms, __($s->status)]);

        return $format === 'pdf' ? $documents->pdf('suppliers.pdf', __('Suppliers'), $headers, $rows) : $documents->xlsx('suppliers.xlsx', $headers, $rows);
    })->middleware('can:suppliers.view')->name('catalog.suppliers.export');

    $router->livewire('catalog/reference-import', 'catalog::reference-import')
        ->middleware('can:products_categories_brands.create')
        ->name('catalog.reference-import');
    $router->get('catalog/reference-import/template/{type}', fn (string $type, ImportTemplateFactory $templates) => $templates->catalogReference(auth()->user(), $type, request('company_id')))->middleware('can:products_categories_brands.create')->name('catalog.reference-import.template');

    $router->livewire('suppliers', 'catalog::suppliers')
        ->middleware('can:suppliers.view')
        ->name('suppliers.index');
});
