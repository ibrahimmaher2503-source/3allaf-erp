<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Actions\CreateExportJobAction;
use App\Modules\Reporting\Actions\EvaluateAlertsAction;
use App\Modules\Reporting\Models\Alert;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Queries\ReportSnapshot;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth', 'verified'])->group(function (): void {
    $reportTitles = [
        'sales' => __('Sales reports'),
        'customers' => __('Customer & loyalty reports'),
        'cash' => __('Cash & shift reports'),
        'purchasing' => __('Purchasing reports'),
        'inventory' => __('Inventory reports'),
        'parties' => __('Party reports'),
        'assets' => __('Rental asset reports'),
    ];

    $renderReport = function (Request $request, ReportSnapshot $snapshot, ?string $focusedModule = null) use ($reportTitles) {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'sort' => ['nullable', 'string', 'in:document,store,cashier,gross,discount,tax,total'], 'direction' => ['nullable', 'string', 'in:asc,desc'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
        $filters = $request->only([
            'date_from', 'date_to', 'branch_id', 'store_id', 'user_id', 'module',
            'supplier_id', 'customer_id', 'product_id', 'category_id', 'payment_method_id',
            'document_status', 'party_status',
            'product_type', 'product_status', 'brand_id', 'age_label_id', 'character_id', 'colour_id', 'gender_id',
            'search', 'sort', 'direction', 'page',
        ]);
        if ($focusedModule !== null) {
            $filters['module'] = $focusedModule;
        }
        $report = $snapshot->execute($user, $filters);
        $branches = Branch::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->get();
        $stores = Store::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->get();
        $users = User::query()->where('status', 'active')->when(! $user->is_super_admin, function (Builder $query) use ($user): void {
            $query->where(function (Builder $scope) use ($user): void {
                $scope->whereHas('branchScopes', fn (Builder $branch): Builder => $branch->where('status', 'active')->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id')))
                    ->orWhereHas('storeScopes', fn (Builder $store): Builder => $store->where('status', 'active')->whereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id')));
            });
        })->orderBy('name')->limit(200)->get(['id', 'name', 'username']);
        $products = ($user->can('pos_sales.view') || $user->can('inventory_stock_card.view'))
            ? Product::query()->sellable()->with(['parent:id,name_ar,name_en', 'variantValues.group', 'variantValues.value'])->orderBy('item_code')->limit(200)->get(['id', 'item_code', 'name_ar', 'name_en', 'parent_product_id'])
            : collect();
        $categories = ($user->can('products_categories_brands.view') || $user->can('pos_sales.view') || $user->can('inventory_stock_card.view'))
            ? Category::query()->where('status', 'active')->orderBy('code')->limit(200)->get(['id', 'code', 'name_en'])
            : collect();
        $paymentMethods = $user->can('pos_sales.payment_view')
            ? PaymentMethod::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_en'])
            : collect();
        $customerColumns = ['id', 'name_en'];
        if ($user->can('customers.sensitive')) {
            $customerColumns[] = 'phone_display';
        }
        $customers = $user->can('customers.view')
            ? Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name_en')->limit(200)->get($customerColumns)
            : collect();
        $suppliers = $user->can('suppliers.view') || $user->can('purchase_orders.view')
            ? Supplier::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'code', 'name_en'])
            : collect();
        $brands = Brand::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_en']);
        $ages = AgeLabel::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_en']);
        $characters = Character::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_en']);
        $colours = Colour::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_en']);
        $genders = Gender::query()->where('status', 'active')->orderBy('name_en')->limit(200)->get(['id', 'name_en']);

        $reportKey = $focusedModule ?? 'dashboard';
        $reportTitle = $focusedModule === null ? __('Reports and Export Center') : $reportTitles[$focusedModule];
        $recentExports = ($user->can('dashboard_reports.export_xlsx') || $user->can('dashboard_reports.export_pdf'))
            ? ExportJob::query()->where('requested_by', $user->id)->latest('id')->limit(5)->get(['id', 'report_key', 'format', 'status', 'row_count', 'created_at', 'completed_at'])
            : collect();

        return view('pages.reports.index', compact('report', 'reportKey', 'reportTitle', 'recentExports', 'branches', 'stores', 'users', 'products', 'categories', 'paymentMethods', 'customers', 'suppliers', 'brands', 'ages', 'characters', 'colours', 'genders'));
    };

    Route::get('reports', fn (Request $request, ReportSnapshot $snapshot) => $renderReport($request, $snapshot))
        ->middleware('can:dashboard_reports.view')->name('reports.index');

    foreach ([
        'sales' => 'sales',
        'customers' => 'customers',
        'cash-shifts' => 'cash',
        'purchasing' => 'purchasing',
        'inventory' => 'inventory',
        'parties' => 'parties',
        'rental-assets' => 'assets',
    ] as $path => $module) {
        Route::get('reports/'.$path, fn (Request $request, ReportSnapshot $snapshot) => $renderReport($request, $snapshot, $module))
            ->middleware('can:dashboard_reports.view')->name('reports.'.$module);
    }

    Route::post('reports/export', function (Request $request, CreateExportJobAction $action) {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'], 'store_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'], 'module' => ['nullable', 'string', 'in:sales,inventory,purchasing,cash,customers,parties,quotations,assets'],
            'supplier_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'product_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'], 'payment_method_id' => ['nullable', 'integer'],
            'document_status' => ['nullable', 'string', 'in:draft,submitted,approved,partially_received,received,awaiting_distribution,rejected,suspended,cancelled,closed'],
            'party_status' => ['nullable', 'string', 'in:draft,confirmed,closed,cancelled'],
            'product_type' => ['nullable', 'string', 'in:standard,service,digital,bundle,rental,party_consumable'], 'product_status' => ['nullable', 'string', 'in:active,inactive,archived'],
            'brand_id' => ['nullable', 'integer'], 'age_label_id' => ['nullable', 'integer'], 'character_id' => ['nullable', 'integer'], 'colour_id' => ['nullable', 'integer'], 'gender_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'], 'sort' => ['nullable', 'string', 'in:document,store,cashier,gross,discount,tax,total'], 'direction' => ['nullable', 'string', 'in:asc,desc'],
            'dataset' => ['nullable', 'string', 'in:products_barcodes,customers_groups,suppliers_groups,purchase_orders,purchase_invoices_receiving,opening_inventory,inventory_movements'],
            'format' => ['required', 'string', 'in:csv,xlsx,pdf'],
        ]);
        $job = $action->execute($user, $filters, $filters['format']);

        return back()->with('success', __('Export requested. It will appear in the export job center.'))->with('export_job_id', $job->id);
    })->name('reports.export');

    Route::get('alerts', function (Request $request, EvaluateAlertsAction $evaluator) {
        /** @var User $user */
        $user = $request->user();
        $evaluator->execute($user);
        $alerts = Alert::query()->visibleTo($user)->with(['branch', 'store'])->where('status', 'open')->latest('id')->paginate(20)->through(function (Alert $alert) use ($user): Alert {
            $alert->source_url = null;
            if ($alert->source_type === RentalAsset::class && $user->can('rental_assets.view')) {
                $asset = RentalAsset::query()->visibleTo($user)->whereKey((int) $alert->source_id)->first();
                if ($asset !== null) {
                    $alert->source_url = route('party.assets.index', ['q' => $asset->code]);
                }
            } elseif (in_array($alert->source_type, [StockBalance::class, Product::class], true) && $user->can('inventory_stock_card.view')) {
                $productId = (int) ($alert->metadata['product_id'] ?? $alert->source_id);
                if ($productId > 0) {
                    $alert->source_url = route('inventory.stock-card', $productId);
                }
            } elseif ($alert->source_type === Sale::class && $user->can('pos_sales.view')) {
                $alert->source_url = route('sales.show', (int) $alert->source_id);
            }

            return $alert;
        });

        return view('pages.alerts.index', compact('alerts'));
    })->middleware('can:dashboard_reports.view')->name('alerts.index');

    Route::post('alerts/{alert}/acknowledge', function (Request $request, Alert $alert) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.edit'), 403);
        $updated = DB::transaction(function () use ($user, $alert): bool {
            $locked = Alert::query()->visibleTo($user)->whereKey($alert->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'open') {
                return false;
            }
            $locked->update(['status' => 'acknowledged', 'acknowledged_by' => $user->id, 'acknowledged_at' => now()]);
            app(RecordAuditEvent::class)->execute('reporting', 'alert_acknowledged', $locked, after: ['status' => 'acknowledged'], branchId: $locked->branch_id, storeId: $locked->store_id);

            return true;
        });

        return back()->with('success', $updated ? __('Alert acknowledged.') : __('The alert was already acknowledged.'));
    })->middleware('can:dashboard_reports.edit')->name('alerts.acknowledge');

    Route::get('exports', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.export_xlsx') || $user->can('dashboard_reports.export_pdf'), 403);
        $jobs = ExportJob::query()->where('requested_by', $user->id)->latest('id')->paginate(20)->withQueryString();

        return view('pages.exports.index', compact('jobs'));
    })->name('exports.index');

    Route::get('exports/{job}/download', function (Request $request, ExportJob $job) {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('dashboard_reports.export_'.($job->format === 'csv' ? 'xlsx' : $job->format)) && $job->requested_by === $user->id, 403);
        abort_unless($job->status === 'ready' && $job->expires_at?->isFuture(), 410);
        abort_unless($job->storage_path !== null && Storage::disk($job->storage_disk ?: 'local')->exists($job->storage_path), 404);
        app(RecordAuditEvent::class)->execute('reporting', 'export_downloaded', $job, branchId: $job->branch_id, storeId: $job->store_id, metadata: ['row_count' => $job->row_count, 'filters' => $job->filters, 'expires_at' => $job->expires_at?->toIso8601String()]);
        $extension = $job->format;
        $mime = match($job->format) { 'pdf'=>'application/pdf','csv'=>'text/csv; charset=UTF-8',default=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' };

        return Storage::disk($job->storage_disk ?: 'local')->download($job->storage_path, 'toyjoy-'.$job->report_key.'-'.$job->id.'.'.$extension, ['Content-Type' => $mime]);
    })->name('exports.download');

    Route::get('exports/{job}/view', function (Request $request, ExportJob $job) {
        /** @var User $user */ $user=$request->user();
        abort_unless($user->can('dashboard_reports.export_'.($job->format === 'csv' ? 'xlsx' : $job->format)) && $job->requested_by === $user->id,403);
        abort_unless($job->status==='ready' && $job->expires_at?->isFuture() && $job->storage_path && Storage::disk($job->storage_disk?:'local')->exists($job->storage_path),410);
        return Storage::disk($job->storage_disk?:'local')->response($job->storage_path, null, ['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    })->name('exports.view');
});
