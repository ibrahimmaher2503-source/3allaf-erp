<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Store;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceImportBatch;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Policies\SupplierReturnPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $purchaseHistory = static function (Request $request, string $mode) {
        $user = $request->user();
        abort_unless($user !== null && $user->can('purchase_invoices_supplier_returns.view'), 403);
        $visibleStoreIds = Store::query()->visibleTo($user)->pluck('id');
        $supplierId = $request->integer('supplier_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));

        $approvedInvoices = PurchaseInvoice::query()
            ->where('status', 'approved')->whereIn('store_id', $visibleStoreIds)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($productId, fn ($query) => $query->whereHas('lines', fn ($lines) => $lines->where('product_id', $productId)))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('invoice_date', '<=', $dateTo));

        $suppliers = Supplier::query()->whereIn('id', (clone $approvedInvoices)->select('supplier_id'))->orderBy('name_en')->get();
        $products = Product::query()->whereIn('id', PurchaseInvoiceLine::query()
            ->whereHas('invoice', fn ($query) => $query->where('status', 'approved')->whereIn('store_id', $visibleStoreIds))
            ->select('product_id'))->orderBy('item_code')->get(['id', 'item_code', 'name_ar', 'name_en']);

        $invoices = $mode === 'supplier'
            ? (clone $approvedInvoices)->with(['supplier', 'store'])->latest('invoice_date')->latest('id')->paginate(20)->withQueryString()
            : null;
        $costLines = $mode === 'cost'
            ? PurchaseInvoiceLine::query()->with(['invoice.supplier', 'invoice.store', 'product'])
                ->whereHas('invoice', fn ($query) => $query->where('status', 'approved')->whereIn('store_id', $visibleStoreIds)
                    ->when($supplierId, fn ($scope) => $scope->where('supplier_id', $supplierId))
                    ->when($dateFrom !== '', fn ($scope) => $scope->whereDate('invoice_date', '>=', $dateFrom))
                    ->when($dateTo !== '', fn ($scope) => $scope->whereDate('invoice_date', '<=', $dateTo)))
                ->when($productId, fn ($query) => $query->where('product_id', $productId))
                ->latest('id')->paginate(30)->withQueryString()
            : null;
        $supplierReturns = $mode === 'supplier'
            ? PurchaseReturn::query()->with(['supplier', 'store', 'purchaseInvoice'])
                ->where('status', 'approved')->whereIn('store_id', $visibleStoreIds)
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when($dateFrom !== '', fn ($query) => $query->whereDate('return_date', '>=', $dateFrom))
                ->when($dateTo !== '', fn ($query) => $query->whereDate('return_date', '<=', $dateTo))
                ->latest('return_date')->latest('id')->limit(20)->get()
            : collect();
        $lastPrices = $mode === 'supplier' && $supplierId
            ? ProductSupplier::query()->with('product')->where('supplier_id', $supplierId)
                ->whereNotNull('last_purchase_price')->latest('last_purchase_date')->limit(50)->get()
            : collect();

        return view('purchasing.history', compact('mode', 'invoices', 'costLines', 'supplierReturns', 'lastPrices', 'suppliers', 'products', 'supplierId', 'productId', 'dateFrom', 'dateTo'));
    };

    $router->livewire('purchasing/orders', 'purchasing::orders')
        ->middleware('can:purchase_orders.view')
        ->name('purchasing.orders');

    $router->livewire('purchasing/orders/create', 'purchasing::orders')
        ->middleware('can:purchase_orders.create')
        ->name('purchasing.orders.create');

    $router->livewire('purchasing/orders/{order}/edit', 'purchasing::orders')
        ->whereNumber('order')->middleware('can:purchase_orders.edit')
        ->name('purchasing.orders.edit');

    $router->livewire('purchasing/invoices', 'purchasing::invoices')
        ->middleware('can:purchase_invoices_supplier_returns.view')
        ->name('purchasing.invoices');

    $router->get('purchasing/supplier-history', fn (Request $request) => $purchaseHistory($request, 'supplier'))
        ->middleware('can:purchase_invoices_supplier_returns.view')->name('purchasing.history.suppliers');
    $router->get('purchasing/cost-history', fn (Request $request) => $purchaseHistory($request, 'cost'))
        ->middleware('can:purchase_invoices_supplier_returns.view')->name('purchasing.history.costs');

    $router->livewire('purchasing/invoices/import', 'purchasing::invoice-import')
        ->middleware('can:purchase_invoices_supplier_returns.view')
        ->name('purchasing.invoices.import');
    $router->get('purchasing/invoices/import/{batch}/source/{attachment}', function (string $batch, string $attachment) {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = $actor->id;
        $batch = PurchaseInvoiceImportBatch::query()
            ->where('created_by', $actorId)
            ->findOrFail((int) $batch);
        $attachment = Attachment::query()
            ->where('purpose', 'import_source')
            ->where('source_type', PurchaseInvoiceImportBatch::class)
            ->where('source_id', (string) $batch->id)
            ->where('uploaded_by', $actorId)
            ->findOrFail((int) $attachment);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $candidate): bool => Gate::forUser($user)->allows('purchase_invoices_supplier_returns.view')
                && $candidate->source_type === PurchaseInvoiceImportBatch::class
                && $candidate->source_id === (string) $batch->id,
        );
    })->whereNumber('batch')->middleware('can:purchase_invoices_supplier_returns.view')->name('purchasing.invoices.import.source');

    $router->livewire('purchasing/returns', 'purchasing::returns')
        ->middleware('can:purchase_returns.view')
        ->name('purchasing.returns');

    $router->livewire('purchasing/returns/settings', 'purchasing::return-settings')
        ->middleware('can:company_settings.view')
        ->name('purchasing.returns.settings');

    $router->get('purchasing/returns/{return}', function (string $return) {
        Gate::authorize('purchase_returns.view');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $return = PurchaseReturn::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with(['supplier', 'store', 'purchaseInvoice', 'reason', 'creator', 'submitter', 'approver', 'lines.product'])
            ->findOrFail((int) $return);

        return view('purchasing.return-detail', [
            'return' => $return,
            'movements' => StockMovement::query()->where('source_type', PurchaseReturn::class)->where('source_id', $return->id)->latest('id')->get(),
            'audits' => AuditLog::query()->where('source_type', PurchaseReturn::class)->where('source_id', (string) $return->id)->latest('id')->limit(50)->get(),
            'approvals' => ApprovalRecord::query()->where('source_type', 'purchase_returns')->where('source_id', (string) $return->id)->latest('id')->get(),
        ]);
    })->whereNumber('return')->middleware('can:purchase_returns.view')->name('purchasing.returns.show');

    $router->get('purchasing/returns/{return}/print', function (string $return) {
        Gate::authorize('purchase_returns.print');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $return = PurchaseReturn::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with(['supplier', 'store', 'purchaseInvoice', 'reason', 'creator', 'approver', 'lines.product'])
            ->findOrFail((int) $return);

        return view('purchasing.return-print', [
            'return' => $return,
            'policy' => app(SupplierReturnPolicy::class),
        ]);
    })->whereNumber('return')->middleware('can:purchase_returns.print')->name('purchasing.returns.print');

    $router->get('purchasing/invoices/{invoice}/print', function (string $invoice) {
        Gate::authorize('purchase_invoices_supplier_returns.print');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
        $invoice = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with(['supplier', 'store', 'purchaseOrder', 'lines.product'])
            ->findOrFail((int) $invoice);

        return view('purchasing.invoice-print', compact('invoice'));
    })->name('purchasing.invoices.print');

    $router->get('purchasing/invoices/{invoice}/destinations/{store}/labels', function (string $invoice, string $store) {
        Gate::authorize('purchase_invoices.print_labels');
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $destination = Store::query()->visibleTo($actor)->where('type','selling')->whereKey((int) $store)->firstOrFail();
        $invoice = PurchaseInvoice::query()->where('status','approved')->with(['distributions'=>fn($q)=>$q->where('destination_store_id',$destination->id)])->findOrFail((int)$invoice);
        abort_if($invoice->distributions->isEmpty(),404);
        return redirect()->route('pricing.labels', ['invoice' => $invoice->id, 'store' => $destination->id]);
    })->whereNumber('invoice')->whereNumber('store')->name('purchasing.invoices.destination-labels');

    $router->get('purchasing/invoices/export', function () {
        Gate::authorize('purchase_invoices_supplier_returns.export');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
        $invoices = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with(['supplier', 'store'])
            ->latest('id')
            ->limit(5000)
            ->get();

        return response()->streamDownload(function () use ($invoices): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                abort(500, 'Unable to open the CSV output stream.');
            }
            fputcsv($handle, ['invoice_number', 'supplier_code', 'supplier_reference', 'store_code', 'status', 'invoice_date', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, array_map(static fn (mixed $value): string => is_string($value) && preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'".$value : (string) $value, [$invoice->invoice_number, $invoice->supplier?->code, $invoice->supplier_reference, $invoice->store?->code, $invoice->status, $invoice->invoice_date?->format('Y-m-d'), $invoice->subtotal, $invoice->tax_amount, $invoice->discount_amount, $invoice->total_amount]));
            }
            fclose($handle);
        }, 'purchase-invoices.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('purchasing.invoices.export');

    $router->get('purchasing/invoices/settings', function () {
        Gate::authorize('company_settings.view');

        $versions = FinancialSettingVersion::query()
            ->orderBy('key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($items) => $items->first());

        return view('purchasing.invoice-settings', [
            'versions' => $versions,
        ]);
    })->middleware('can:company_settings.view')->name('purchasing.invoices.settings');
    $router->get('purchasing/invoices/readiness', function () {
        Gate::authorize('purchase_orders.view');

        return view('purchasing.invoice-readiness', [
            'decisionGroups' => [
                ['key' => 'cost', 'title' => ['ar' => 'سياسة التكلفة', 'en' => 'Cost policy'], 'items' => 10],
                ['key' => 'tax', 'title' => ['ar' => 'الضريبة', 'en' => 'Tax'], 'items' => 5],
                ['key' => 'discount', 'title' => ['ar' => 'الخصومات', 'en' => 'Discount'], 'items' => 7],
                ['key' => 'import', 'title' => ['ar' => 'استيراد الفواتير', 'en' => 'Invoice import'], 'items' => 10],
                ['key' => 'receiving', 'title' => ['ar' => 'الاستلام ومراجعة الفاتورة', 'en' => 'Receiving and matching'], 'items' => 15],
                ['key' => 'opening-stock', 'title' => ['ar' => 'المخزون الافتتاحي', 'en' => 'Opening stock'], 'items' => 9],
                ['key' => 'master-data', 'title' => ['ar' => 'الفروع والمخازن والبيانات الأساسية', 'en' => 'Branches, stores, and real master data'], 'items' => 6],
                ['key' => 'authorization', 'title' => ['ar' => 'الصلاحيات والحدود', 'en' => 'Authorization and limits'], 'items' => 19],
            ],
            'blockers' => [
                ['title' => ['ar' => 'بيانات الفروع والمخازن', 'en' => 'Real branch and store data'], 'detail' => ['ar' => 'لسه قائمة الفروع والمخازن وقواعد تفعيلها وتحديد نطاقها ما اتعتمدتش.', 'en' => 'The production list and activation/scope policy are not approved yet.']],
                ['title' => ['ar' => 'السياسات المالية والتجارية', 'en' => 'Commercial and financial policies'], 'detail' => ['ar' => 'قواعد الضرائب والخصومات والترقيم والطباعة لسه محتاجة قرار.', 'en' => 'Tax, discount, numbering, and print policies still require owner decisions.']],
                ['title' => ['ar' => 'بيانات الموردين وشروط التعامل', 'en' => 'Supplier data and terms'], 'detail' => ['ar' => 'البيانات الفعلية للموردين وشروط التعامل معاهم لسه ما اتسجلتش.', 'en' => 'Actual supplier and commercial data has not been supplied yet.']],
                ['title' => ['ar' => 'الإعدادات الافتراضية', 'en' => 'Local engineering defaults'], 'detail' => ['ar' => 'القيم الحالية مجرد إعدادات مؤقتة ومش اعتماد نهائي للتشغيل.', 'en' => 'Current defaults are not production approval.']],
            ],
        ]);
    })->middleware('can:purchase_orders.view')->name('purchasing.invoices.readiness');

    $router->get('purchasing/orders/{order}/print', function (PurchaseOrder $order) {
        Gate::authorize('purchase_orders.print');
        $user = Auth::user();
        abort_unless(
            $order->store_id === null || $user?->is_super_admin || ($user !== null && Store::query()->visibleTo($user)->whereKey($order->store_id)->exists()),
            403,
        );

        return view('purchasing.print', [
            'order' => $order->load(['branch', 'supplier', 'store', 'creator', 'lines.product']),
        ]);
    })->whereNumber('order')->middleware('can:purchase_orders.print')->name('purchasing.orders.print');

    $router->post('purchasing/orders/{order}/pdf', function (PurchaseOrder $order) {
        app(\App\Modules\Purchasing\Actions\GeneratePurchaseOrderPdfAction::class)->execute($order, app()->getLocale());
        return back()->with('success', __('Purchase-order PDF generated and stored privately.'));
    })->whereNumber('order')->middleware('can:purchase_orders.print')->name('purchasing.orders.pdf.generate');

    $deliverOrderPdf = function (PurchaseOrder $order, \App\Modules\Purchasing\Models\PurchaseOrderDocument $document, bool $download = false) {
        abort_unless((int) $document->purchase_order_id === (int) $order->id, 404);
        $attachment = $document->attachment()->firstOrFail();
        $authorizer = fn (User $user, Attachment $candidate): bool => $user->can('purchase_orders.print') && $candidate->source_type === PurchaseOrder::class && $candidate->source_id === (string) $order->id;
        if (!$download) return app(DeliverAttachment::class)->execute($attachment, $authorizer);
        app(\App\Modules\Platform\Actions\AuthorizeAttachmentAccess::class)->execute($attachment, $authorizer);
        return \Illuminate\Support\Facades\Storage::disk($attachment->storage_disk)->download($attachment->storage_path, $order->po_number.'-v'.$document->version.'-'.$document->locale.'.pdf');
    };
    $router->get('purchasing/orders/{order}/pdf/{document}/view', fn (PurchaseOrder $order, \App\Modules\Purchasing\Models\PurchaseOrderDocument $document) => $deliverOrderPdf($order, $document))->whereNumber('order')->whereNumber('document')->middleware('can:purchase_orders.print')->name('purchasing.orders.pdf.view');
    $router->get('purchasing/orders/{order}/pdf/{document}/download', fn (PurchaseOrder $order, \App\Modules\Purchasing\Models\PurchaseOrderDocument $document) => $deliverOrderPdf($order, $document, true))->whereNumber('order')->whereNumber('document')->middleware('can:purchase_orders.print')->name('purchasing.orders.pdf.download');
});
