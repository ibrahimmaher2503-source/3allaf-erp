<?php

use App\Http\Controllers\LocaleController;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Actions\ExportAuditLogs;
use App\Modules\Platform\Actions\RecordSetupDecision;
use App\Modules\Platform\Actions\SaveCityAction;
use App\Modules\Platform\Http\Controllers\DashboardAssistantController;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Queries\AdministrationDashboard;
use App\Modules\Platform\Support\InitialSetupStatus;
use App\Modules\Platform\Support\SetupContinuation;
use App\Modules\Platform\Support\TutorialRegistry;
use App\Modules\Platform\Support\UserFlowRegistry;
use App\Modules\Platform\Support\WorkContext;
use App\Support\UserSafeError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->post('locale', LocaleController::class)->name('locale.switch');

$router->middleware(['auth', 'verified'])->group(function () use ($router) {
    $router->post('ui/work-context', function (Request $request, WorkContext $context) {
        $validated = $request->validate(['store_id' => ['nullable', 'integer', 'min:1']]);
        $context->select($request->user(), filled($validated['store_id'] ?? null) ? (int) $validated['store_id'] : null);

        return back();
    })->name('platform.work-context');
    $router->post('ui/preferences', [DashboardAssistantController::class, 'preferences'])->name('platform.ui-preferences');
    $router->post('ui/tutorial-progress', [DashboardAssistantController::class, 'tutorialProgress'])->name('platform.tutorial-progress');
    $router->livewire('notifications', 'platform::system.notifications')->name('notifications.index');
    $router->get('help/screens/{screenId}', [DashboardAssistantController::class, 'screen'])->whereIn('screenId', TutorialRegistry::screenIds())->name('platform.help.screen');
    $router->get('help/flows/{flowId}', [DashboardAssistantController::class, 'flow'])->whereIn('flowId', array_keys(UserFlowRegistry::all()))->name('platform.help.flow');

    $router->view('initial-setup', 'platform.initial-setup')->middleware('can:company_settings.edit')->name('initial-setup');
    $router->post('initial-setup/decisions', function (Request $request, RecordSetupDecision $action, InitialSetupStatus $status, SetupContinuation $continuation) {
        $validated = $request->validate([
            'step_key' => ['required', 'string', 'max:80'],
            'decision' => ['required', 'string', 'in:skipped,deferred'],
        ]);
        $action->execute($validated['step_key'], $validated['decision']);
        $steps = collect($status->snapshot()['steps'])->filter(fn (array $step): bool => $step['can_access'])->values();
        $next = $continuation->next($steps);

        return $next
            ? redirect()->to($next['route'].(str_contains($next['route'], '?') ? '&' : '?').http_build_query(['setup' => 1, 'setup_step' => $next['key']]))
            : redirect()->route('initial-setup');
    })->middleware('can:company_settings.edit')->name('setup.decisions.store');
    $router->get('admin', fn (AdministrationDashboard $dashboard) => view('platform.admin.overview', ['dashboard' => $dashboard->for(auth()->user())]))
        ->middleware('can:access-administration-center')->name('admin.overview');
    $router->livewire('admin/translations', 'platform::admin.translation-editor')->middleware('can:company_settings.edit')->name('admin.translations');
    $router->livewire('admin/settings', 'platform::admin.settings')->middleware('can:company_settings.view')->name('admin.settings');
    $router->get('admin/settings/cities', function (Request $request) {
        $companyId = (int) Store::query()->visibleTo($request->user())->where('status', 'active')->value('company_id');
        abort_if($companyId < 1, 404);
        $cities = City::query()->visibleToCompany($companyId)->with('governorate')->when($request->filled('q'), fn ($query) => $query->where(fn ($scope) => $scope->where('name_ar', 'like', '%'.trim((string) $request->q).'%')->orWhere('name_en', 'like', '%'.trim((string) $request->q).'%')->orWhere('code', 'like', '%'.trim((string) $request->q).'%')))->orderBy('governorate_id')->orderBy('sort_order')->paginate(30)->withQueryString();
        $governorates = Governorate::query()->active()->orderBy('sort_order')->get();

        return view('platform.admin.cities', compact('cities', 'governorates'));
    })->middleware('can:company_settings.view')->name('admin.settings.cities');
    $router->post('admin/settings/cities', function (Request $request, SaveCityAction $action) {
        $companyId = (int) Store::query()->visibleTo($request->user())->where('status', 'active')->value('company_id');
        $data = $request->validate(['governorate_id' => 'required|integer', 'code' => 'required|alpha_dash:ascii|max:50', 'name_ar' => 'required|string|max:120', 'name_en' => 'required|string|max:120', 'sort_order' => 'nullable|integer|min:0', 'status' => 'required|in:active,inactive']);
        try {
            $action->execute($companyId, $data);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['city' => UserSafeError::message($e)]);
        }

        return back()->with('success', __('City / locality saved.'));
    })->middleware('can:company_settings.edit')->name('admin.settings.cities.store');
    $router->put('admin/settings/cities/{city}', function (Request $request, City $city, SaveCityAction $action) {
        $companyId = (int) Store::query()->visibleTo($request->user())->where('status', 'active')->value('company_id');
        $data = $request->validate(['governorate_id' => 'required|integer', 'code' => 'required|alpha_dash:ascii|max:50', 'name_ar' => 'required|string|max:120', 'name_en' => 'required|string|max:120', 'sort_order' => 'nullable|integer|min:0', 'status' => 'required|in:active,inactive']);
        try {
            $action->execute($companyId, $data, $city);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['city' => UserSafeError::message($e)]);
        }

        return back()->with('success', __('City / locality saved.'));
    })->middleware('can:company_settings.edit')->name('admin.settings.cities.update');
    foreach ([
        'admin/company' => 'company',
        'admin/payment-methods' => 'payments',
        'admin/tax-settings' => 'tax',
        'admin/document-sequences' => 'sequences',
        'admin/printers' => 'printers',
    ] as $legacyPath => $tab) {
        $router->get($legacyPath, fn () => redirect()->route('admin.settings', ['tab' => $tab]))
            ->middleware('can:company_settings.view')
            ->name('admin.settings.compatibility.'.$tab);
    }
    $router->get('admin/settings/printers/{printer}/preview', function (PrinterConfiguration $printer) {
        abort_unless(auth()->user()?->can('company_settings.view'), 403);

        $printer = PrinterConfiguration::visibleTo(auth()->user())->findOrFail($printer->id)->load(['branch', 'store']);

        return view('platform.admin.printer-preview', compact('printer'));
    })->name('admin.settings.printer-preview');
    $router->get('admin/settings/print-templates/{template}/preview', function (PrintTemplate $template) {
        abort_unless(auth()->user()?->can('company_settings.view'), 403);
        $template = PrintTemplate::visibleTo(auth()->user())->findOrFail($template->id);
        return view('platform.admin.print-template-preview', compact('template'));
    })->name('admin.settings.template-preview');
    $router->livewire('admin/branches', 'platform::admin.branches')->middleware('can:branches_stores.view')->name('admin.branches');
    $router->livewire('admin/stores', 'platform::admin.stores')->middleware('can:branches_stores.view')->name('admin.stores');
    $router->livewire('admin/cash-drawers', 'platform::admin.drawers')->middleware('can:drawers_payments_tax_numbering_printers.view')->name('admin.cash-drawers');
    // Keep the historical URL usable while the canonical route remains guarded.
    $router->get('admin/drawers', fn () => redirect()->route('admin.cash-drawers'))
        ->middleware('can:drawers_payments_tax_numbering_printers.view')
        ->name('admin.drawers.compatibility');
    $router->livewire('admin/authorization-baseline', 'platform::admin.authorization-baseline')->middleware('can:users_roles_permissions.view')->name('admin.authorization-baseline');
    $router->livewire('admin/roles', 'platform::admin.roles')->middleware('can:manage-global-role-catalog')->name('admin.roles');
    $router->livewire('admin/roles/{role}/permissions', 'platform::admin.role-permissions')->whereNumber('role')->middleware('can:manage-global-role-catalog')->name('admin.role-permissions');

    $router->livewire('admin/system/health', 'platform::system.health')->middleware('can:audit_logs.view')->name('system.health');
    $router->livewire('admin/system/backups', 'platform::system.backups')->middleware('can:audit_logs.view')->name('system.backups');
    $router->livewire('admin/audit', 'platform::system.audit-log')->middleware('can:access-activity-log')->name('admin.audit');
    $router->get('admin/audit/export', function (Request $request) {
        $filters = $request->validate([
            'mode' => ['nullable', 'string', 'in:all,override,print'],
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:150'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'store_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return app(ExportAuditLogs::class)->execute($filters);
    })->middleware('can:access-activity-log')->name('admin.audit.export');
    $router->livewire('approvals', 'platform::system.approval-inbox')->middleware('can:view-approval-inbox')->name('admin.approvals');
    $router->get('approvals/{approval}/attachments/{attachment}', function (ApprovalRecord $approval, Attachment $attachment) {
        abort_unless($attachment->purpose === 'approval_evidence'
            && $attachment->source_type === ApprovalRecord::class
            && $attachment->source_id === (string) $approval->id, 404);
        Gate::authorize('view', $approval);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $candidate): bool => Gate::forUser($user)->allows('view', $approval)
                && $candidate->source_type === ApprovalRecord::class
                && $candidate->source_id === (string) $approval->id,
        );
    })->name('admin.approvals.attachments.download');
    $router->livewire('admin/system/ui-showcase', 'platform::system.ui-showcase')->middleware('can:dashboard_reports.view')->name('system.ui-showcase');
    $router->view('system/app', 'platform.system.app')->middleware('can:dashboard_reports.view')->name('system.app');
});

$router->get('forbidden', function () {
    return response()->view('errors.403', [], 403);
})->name('forbidden');
