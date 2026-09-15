<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Inventory\Actions\ApproveOpeningInventoryAction;
use App\Modules\Inventory\Actions\ImportOpeningInventoryWorkbookAction;
use App\Modules\Inventory\Actions\ReverseOpeningInventoryAction;
use App\Modules\Inventory\Actions\SaveOpeningInventoryDraftAction;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\OpeningInventoryZeroDecision;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\ImportTemplateFactory;
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

$router = app('router');
$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $render = function (?OpeningInventoryDocument $document = null) {
        Gate::authorize($document === null ? 'inventory_stock_card.create' : 'inventory_stock_card.view');
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($user, request()->input('company_id'));
        if ($document !== null) {
            abort_unless((int) $document->company_id === (int) $company->id, 404);
        }
        $documents = OpeningInventoryDocument::query()->where('company_id', $company->id)->latest('id')->limit(25)->get();
        $documentLines = $document?->lines()->with(['product', 'store'])->orderBy('id')->paginate(100, pageName: 'document_lines');
        $documentTotal = $document === null ? null : $document->lines()->sum('total_value');
        $documentManualEditable = $document === null || (Gate::allows('inventory_stock_card.edit')
            && $document->status === 'draft'
            && $document->lines()->count() <= 500
            && $document->lines()->distinct()->count('store_id') <= 1);
        return view('inventory.opening', compact('document', 'documents', 'company', 'documentLines', 'documentTotal', 'documentManualEditable'));
    };
    $router->get('inventory/opening', fn () => $render())->middleware('can:inventory_stock_card.create')->name('inventory.opening.index');
    $router->get('inventory/opening/{document}', fn (OpeningInventoryDocument $document) => $render($document))->whereNumber('document')->middleware('can:inventory_stock_card.view')->name('inventory.opening.show');
    $router->get('inventory/opening-template.xlsx', function (ImportTemplateFactory $templates) {
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        return $templates->openingInventory($actor, request()->input('company_id'));
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.template');
    $router->post('inventory/opening/import', function (ImportOpeningInventoryWorkbookAction $action) {
        $data = request()->validate(['workbook' => ['required', 'file', 'mimes:xlsx', 'max:51200'], 'document_date' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $result = $action->execute($data['workbook']->getRealPath(), $data['notes'] ?? null, $data['document_date'] ?? null);
        session(['opening_inventory_rejections' => $result['rejections']]);
        $target = $result['document'] ? route('inventory.opening.show', $result['document']) : route('inventory.opening.index');
        return redirect($target)->with('success', __('Opening inventory import: :total total, :accepted accepted, :rejected rejected.', $result));
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.import');
    $router->get('inventory/opening-import-rejections.xlsx', function (MasterDataDocument $documents) {
        $rows = session('opening_inventory_rejections', []); abort_if($rows === [], 404);
        return $documents->xlsx('opening-inventory-rejections.xlsx', [__('Original row'), ...ImportOpeningInventoryWorkbookAction::HEADERS, __('Rejection reason')], $rows);
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.rejections');
    $router->post('inventory/opening', function (SaveOpeningInventoryDraftAction $action) {
        request()->merge(['lines' => collect(request()->input('lines', []))->filter(fn (array $line): bool => collect($line)->contains(fn ($value): bool => filled($value)))->values()->all()]);
        $data = request()->validate(['document_date' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:100000'], 'lines.*.product_id' => ['required', 'integer'], 'lines.*.store_id' => ['required', 'integer'], 'lines.*.quantity' => ['required', 'integer', 'min:1'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0']]);
        $document = $action->execute($data['lines'], notes: $data['notes'] ?? null, documentDate: $data['document_date'] ?? null);
        return redirect()->route('inventory.opening.show', $document)->with('success', __('Opening inventory draft saved. Review totals before approval.'));
    })->middleware('can:inventory_stock_card.create')->name('inventory.opening.store');
    $router->post('inventory/opening/{document}/approve', function (OpeningInventoryDocument $document, ApproveOpeningInventoryAction $action) { $action->execute($document->id); return back()->with('success', __('Opening inventory approved and posted once.')); })->whereNumber('document')->middleware('can:inventory_stock_card.approve')->name('inventory.opening.approve');
    $router->post('inventory/opening/{document}/reverse', function (OpeningInventoryDocument $document, ReverseOpeningInventoryAction $action) { $data = request()->validate(['reason' => ['required', 'string', 'max:1000']]); $action->execute($document->id, $data['reason']); return redirect()->route('inventory.opening.index')->with('success', __('Opening inventory reversal posted with audit history.')); })->whereNumber('document')->middleware('can:inventory_stock_card.reverse')->name('inventory.opening.reverse');
    $router->post('inventory/opening/start-without-stock', function () {
        Gate::authorize('inventory_stock_card.approve');
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        $validated = request()->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $decision = OpeningInventoryZeroDecision::query()->updateOrCreate(['company_id' => $company->id], ['decided_by' => Auth::id(), 'reason' => $validated['reason'] ?? null, 'decided_at' => now()]);
        app(RecordAuditEvent::class)->execute('inventory', 'start_without_opening_inventory', $decision, after: $decision->only(['company_id', 'decided_by', 'decided_at']));
        return back()->with('success', __('The audited decision to start without opening inventory was recorded.'));
    })->middleware('can:inventory_stock_card.approve')->name('inventory.opening.zero');
});
