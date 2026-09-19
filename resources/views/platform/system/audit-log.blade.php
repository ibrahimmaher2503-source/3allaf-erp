<?php

use App\Models\User;
use App\Modules\Platform\Actions\AuditLogValueRedactor;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuditLogPresentation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit Logs')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $category = '';
    public string $event = '';
    public string $actorId = '';
    public string $branchId = '';
    public string $storeId = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    #[Url]
    public string $mode = 'all';
    public ?int $selectedAuditId = null;

    public function mount(): void
    {
        Gate::authorize('access-activity-log');
        abort_unless(in_array($this->mode, ['all', 'override', 'print'], true), 404);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedActorId(): void
    {
        $this->resetPage();
    }

    public function updatedBranchId(): void
    {
        $this->resetPage();
    }

    public function updatedStoreId(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function showAudit(int $auditId): void
    {
        Gate::authorize('access-activity-log');

        $auditLog = AuditLog::query()->findOrFail($auditId);

        Gate::authorize('view', $auditLog);
        $this->selectedAuditId = $auditLog->id;
    }

    public function closeAudit(): void
    {
        $this->selectedAuditId = null;
    }

    public function render()
    {
        Gate::authorize('access-activity-log');
        $user = auth()->user();
        $baseQuery = AuditLog::query()->visibleTo($user);
        $logs = $this->applyFilters(clone $baseQuery)
            ->with('actor:id,name')
            ->latest('id')
            ->paginate(20);

        $selectedAudit = $this->selectedAuditId === null
            ? null
            : AuditLog::query()->visibleTo($user)->find($this->selectedAuditId);

        if ($selectedAudit !== null) {
            Gate::authorize('view', $selectedAudit);
        }

        $redactor = app(AuditLogValueRedactor::class);
        $presentation = app(AuditLogPresentation::class);

        return view('platform.system.audit-log', [
            'logs' => $logs,
            'categories' => (clone $baseQuery)->distinct()->orderBy('category')->pluck('category'),
            'events' => (clone $baseQuery)->distinct()->orderBy('event')->pluck('event'),
            'actors' => User::query()
                ->whereIn('id', (clone $baseQuery)->whereNotNull('actor_id')->select('actor_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'branches' => Branch::query()->visibleTo($user)->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'stores' => Store::query()->visibleTo($user)->orderBy('code')->get(['id', 'branch_id', 'code', 'name_ar', 'name_en']),
            'selectedAudit' => $selectedAudit,
            'selectedBeforeValues' => $selectedAudit === null ? null : $redactor->redactForViewer($selectedAudit->before_values, $user),
            'selectedAfterValues' => $selectedAudit === null ? null : $redactor->redactForViewer($selectedAudit->after_values, $user),
            'canExport' => Gate::allows('export', AuditLog::class),
            'exportConfigured' => filter_var(config('audit.export_max_rows'), FILTER_VALIDATE_INT) !== false && (int) config('audit.export_max_rows') > 0,
            'presentation' => $presentation,
            'exportUrl' => route('admin.audit.export', array_filter([
                'mode' => $this->mode === 'all' ? null : $this->mode,
                'search' => $this->search,
                'category' => $this->category,
                'event' => $this->event,
                'actor_id' => $this->actorId,
                'branch_id' => $this->branchId,
                'store_id' => $this->storeId,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
            ], fn ($value) => $value !== '')),
        ]);
    }

    private function applyFilters(Builder $query): Builder
    {
        return $query
            ->when($this->mode === 'print', fn (Builder $query) => $query->where('event', 'like', '%print%'))
            ->when($this->mode === 'override', fn (Builder $query) => $query->where(function (Builder $mode): void {
                $mode->where('event', 'like', '%override%')->orWhereJsonContains('metadata->override', true);
            }))
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('event', 'like', $search)
                        ->orWhere('source_type', 'like', $search)
                        ->orWhere('source_id', 'like', $search)
                        ->orWhere('request_id', 'like', $search)
                        ->orWhere('actor_name', 'like', $search);
                });
            })
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->when($this->actorId !== '', fn (Builder $query) => $query->where('actor_id', (int) $this->actorId))
            ->when($this->branchId !== '', fn (Builder $query) => $query->where('branch_id', (int) $this->branchId))
            ->when($this->storeId !== '', fn (Builder $query) => $query->where('store_id', (int) $this->storeId))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateFrom) === 1, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateTo) === 1, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo));
    }
}; ?>

<x-app.page
    :title="$mode === 'override' ? __('Override log') : ($mode === 'print' ? __('Print log') : __('Audit logs'))"
    :description="__('Append-only, permission-scoped operational history with protected before and after values.')"
    max-width="7xl"
    class="space-y-5"
    data-guide="audit-header"
>
    @if($canExport)
        <div class="flex flex-wrap items-center justify-end gap-3">
            @if($exportConfigured)
                <flux:button :href="$exportUrl" icon="arrow-down-tray">{{ __('Export filtered audit log') }}</flux:button>
            @else
                <flux:callout class="min-w-0 w-full" variant="warning" icon="exclamation-triangle">{{ __('Audit export is unavailable until an administrator approves and configures a positive export row limit.') }}</flux:callout>
            @endif
        </div>
    @endif
    <x-tables.data-panel :title="__('Audit events')" :description="__('Filter visible history by event, source, scope, actor, or request ID.')" data-guide="audit-table">
        <x-slot:toolbar>
            <x-tables.filter-bar data-guide="audit-filters">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <flux:input wire:model.live.debounce.400ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('Event, source, actor, or request ID')" />
                    <flux:select wire:model.live="category" :label="__('Category')">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option }}">{{ $presentation->category($option) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="event" :label="__('Event')">
                        <option value="">{{ __('All events') }}</option>
                        @foreach ($events as $option)
                            <option value="{{ $option }}">{{ $presentation->event($option) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="actorId" :label="__('Actor')">
                        <option value="">{{ __('All actors') }}</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                        @endforeach
                    </flux:select>
                    @if($branches->count() > 1)<flux:select wire:model.live="branchId" :label="__('Branch')">
                        <option value="">{{ __('All visible branches') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->code }} - {{ str_starts_with(app()->getLocale(), 'ar') ? $branch->name_ar : $branch->name_en }}</option>
                        @endforeach
                    </flux:select>@endif
                    <flux:select wire:model.live="storeId" :label="str_starts_with(app()->getLocale(), 'ar') ? 'موقع التشغيل' : 'Operating location'">
                        <option value="">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كل مواقع التشغيل' : 'All operating locations' }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }} - {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model.live="dateFrom" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" dir="ltr" :label="__('From date')" />
                    <flux:input wire:model.live="dateTo" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" dir="ltr" :label="__('To date')" />
                </div>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if ($logs->isEmpty())
            <x-state.empty :title="__('No audit events found')" :description="__('No visible events match the current filters.')" icon="clock" />
        @else
        <div class="hidden max-w-full overflow-x-auto sm:block">
            <flux:table class="min-w-[64rem]" aria-label="{{ __('Audit events') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('Time') }}</flux:table.column>
                    <flux:table.column>{{ __('Event') }}</flux:table.column>
                    <flux:table.column>{{ __('Actor') }}</flux:table.column>
                    <flux:table.column>{{ __('Source') }}</flux:table.column>
                    <flux:table.column>{{ __('Scope') }}</flux:table.column>
                    <flux:table.column>{{ __('Request ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($logs as $log)
                        <flux:table.row key="audit-{{ $log->id }}" class="data-table-row">
                            <flux:table.cell class="whitespace-nowrap font-mono text-xs">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="zinc">{{ $presentation->event($log->event) }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $log->actor_name ?? __('System') }}</flux:table.cell>
                            <flux:table.cell><span>{{ $presentation->source($log->source_type) }}</span>@if($log->source_id)<span class="ms-1 font-mono text-xs text-text-muted" dir="ltr">#{{ $log->source_id }}</span>@endif</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $log->branch_id ? __('Branch').' #'.$log->branch_id : ($log->store_id ? __('Store').' #'.$log->store_id : __('Global')) }}</flux:table.cell>
                            <flux:table.cell class="max-w-40 truncate font-mono text-xs" title="{{ $log->request_id }}">{{ $log->request_id }}</flux:table.cell>
                            <flux:table.cell><x-actions.button semantic="view" :label="__('View')" size="xs" wire:click="showAudit({{ $log->id }})" data-guide="audit-view-action">{{ __('View') }}</x-actions.button></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="space-y-3 sm:hidden">
            @foreach ($logs as $log)
                <x-cards.section-card class="space-y-3" :title="$presentation->event($log->event)">
                    <div class="flex items-center justify-between gap-3">
                        <flux:badge size="sm" color="zinc">{{ $presentation->event($log->event) }}</flux:badge>
                        <span class="whitespace-nowrap font-mono text-xs text-text-muted">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <dl class="grid gap-2 text-sm">
                        <div><dt class="text-text-muted">{{ __('Actor') }}</dt><dd>{{ $log->actor_name ?? __('System') }}</dd></div>
                        <div><dt class="text-text-muted">{{ __('Source') }}</dt><dd>{{ $presentation->source($log->source_type) }}@if($log->source_id)<span class="ms-1 font-mono text-xs" dir="ltr">#{{ $log->source_id }}</span>@endif</dd></div>
                        <div><dt class="text-text-muted">{{ __('Scope') }}</dt><dd>{{ $log->branch_id ? __('Branch').' #'.$log->branch_id : ($log->store_id ? __('Store').' #'.$log->store_id : __('Global')) }}</dd></div>
                    </dl>
                    <x-actions.button semantic="view" :label="__('View')" wire:click="showAudit({{ $log->id }})">{{ __('View') }}</x-actions.button>
                </x-cards.section-card>
            @endforeach
        </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between gap-3 sm:hidden">
                @if ($logs->onFirstPage())
                    <flux:button size="sm" variant="subtle" disabled>{{ __('Previous') }}</flux:button>
                @else
                    <flux:button size="sm" variant="subtle" wire:click="previousPage">{{ __('Previous') }}</flux:button>
                @endif

                <span class="text-sm text-text-muted">{{ __('Page :page', ['page' => $logs->currentPage()]) }}</span>

                @if ($logs->hasMorePages())
                    <flux:button size="sm" variant="subtle" wire:click="nextPage">{{ __('Next') }}</flux:button>
                @else
                    <flux:button size="sm" variant="subtle" disabled>{{ __('Next') }}</flux:button>
                @endif
            </div>
            <div class="hidden sm:block" data-guide="audit-pagination">{{ $logs->links() }}</div>
        </x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="selectedAuditId" class="max-w-3xl">
        @if ($selectedAudit)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-3">
                    <div><flux:heading size="lg">{{ __('Audit event details') }}</flux:heading><flux:text class="mt-1 text-text-muted">{{ $selectedAudit->event_id }}</flux:text></div>
                    <flux:button type="button" icon="x-mark" variant="subtle" size="sm" wire:click="closeAudit" aria-label="{{ __('Close') }}" />
                </div>
                <x-audit-panel :created-by-name="$selectedAudit->actor_name" :created-at="$selectedAudit->created_at->format('Y-m-d H:i:s')" :request-id="$selectedAudit->request_id">
                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><span class="text-text-muted">{{ __('Event') }}</span><p>{{ $presentation->event($selectedAudit->event) }}</p></div>
                        <div><span class="text-text-muted">{{ __('Category') }}</span><p>{{ $presentation->category($selectedAudit->category) }}</p></div>
                        <div><span class="text-text-muted">{{ __('Source') }}</span><p>{{ $presentation->source($selectedAudit->source_type) }}</p></div>
                        <div><span class="text-text-muted">{{ __('Source record ID') }}</span><p class="font-mono text-xs" dir="ltr">{{ $selectedAudit->source_id ?: '—' }}</p></div>
                        @if ($selectedAudit->reason_code || $selectedAudit->reason_text)
                            <div class="sm:col-span-2"><span class="text-text-muted">{{ __('Reason') }}</span><p>{{ trim(($selectedAudit->reason_code ? $selectedAudit->reason_code.': ' : '').$selectedAudit->reason_text) }}</p></div>
                        @endif
                    </div>
                </x-audit-panel>
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-cards.section-card :title="__('Before')"><pre class="overflow-x-auto text-xs">{{ json_encode($selectedBeforeValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></x-cards.section-card>
                    <x-cards.section-card :title="__('After')"><pre class="overflow-x-auto text-xs">{{ json_encode($selectedAfterValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></x-cards.section-card>
                </div>
            </div>
        @endif
    </flux:modal>
</x-app.page>
