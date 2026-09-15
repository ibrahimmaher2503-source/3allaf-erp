<x-layouts::app :title="__('Rental assets')">
    @php
        $ar = str_starts_with(app()->getLocale(), 'ar');
        $pageTitle = match($mode) {
            'catalog' => $ar ? 'سجل الأصول' : 'Asset catalog',
            'calendar' => $ar ? 'تقويم الإتاحة والحجوزات' : 'Availability calendar',
            'reservations' => $ar ? 'الحجوزات والتسليم' : 'Reservations & checkout',
            'returns' => $ar ? 'الإرجاع والفحص والحالة' : 'Returns, inspection & condition',
            'history' => $ar ? 'الضرر والصيانة وسجل القيمة' : 'Damage, maintenance & value history',
            default => $ar ? 'نظرة عامة على الأصول والتأجير' : 'Assets & rental overview',
        };
        $assetTabs = [
            'dashboard' => [$ar ? 'نظرة عامة' : 'Overview', 'chart-bar-square'],
            'catalog' => [$ar ? 'سجل الأصول' : 'Asset catalog', 'archive-box'],
            'calendar' => [$ar ? 'التقويم' : 'Calendar', 'calendar-days'],
            'reservations' => [$ar ? 'الحجز والتسليم' : 'Reserve & checkout', 'arrows-right-left'],
            'returns' => [$ar ? 'الإرجاع والفحص' : 'Return & inspect', 'arrow-uturn-left'],
            'history' => [$ar ? 'الحالة والتاريخ' : 'Condition & history', 'clock'],
        ];
    @endphp
    <x-app.page :title="$pageTitle" :description="$ar ? 'إدارة الإتاحة والعهدة والحالة عبر دورة حياة موثقة ومقيدة بالصلاحيات.' : 'Manage availability, custody, and condition through a permission-controlled, traceable lifecycle.'" max-width="7xl">
        <x-slot:actions>@can('rental_assets.create')<flux:button href="{{ route('party.assets.index', ['mode' => 'catalog']) }}#create-asset" variant="primary" icon="plus">{{ app()->isLocale('ar-EG') ? 'ضيف أصل' : ($ar ? 'إضافة أصل' : 'Add asset') }}</flux:button>@endcan</x-slot:actions>
        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="{{ $ar ? 'أقسام الأصول والتأجير' : 'Assets and rental sections' }}">@foreach($assetTabs as $tab => [$label, $icon])<a href="{{ route('party.assets.index', ['mode' => $tab]) }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-3 text-sm font-semibold transition {{ $mode === $tab ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-text-muted hover:text-text-primary' }}"><flux:icon :name="$icon" class="size-4" />{{ $label }}</a>@endforeach</nav>
        @if (session('success')) <flux:callout variant="success">{{ session('success') }}</flux:callout> @endif
        @if ($errors->any()) <flux:callout variant="danger"><ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout> @endif
        <flux:callout variant="info" icon="information-circle">{{ __('Rental assets are separate from consumable stock. Reservations change availability only, and event costs are operational history, not a general-ledger posting.') }}</flux:callout>
        @if($mode === 'dashboard')<x-assets.dashboard :dashboard="$dashboard" />@endif
        @can('rental_assets.create')
            @if($mode === 'catalog')
            <flux:card id="create-asset" class="space-y-4">
                <div><flux:heading size="lg">{{ __('Add rental asset') }}</flux:heading><flux:text>{{ __('Use a stable code. Historical assets are never physically deleted.') }}</flux:text></div>
                <form method="POST" action="{{ route('party.assets.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <flux:input name="code" label="{{ __('Asset code') }}" required />
                    <flux:input name="name_en" label="{{ __('Name (English)') }}" required />
                    <flux:input name="name_ar" label="{{ __('Name (Arabic)') }}" required />
                    <flux:input name="category" label="{{ __('Category') }}" />
                    <flux:select name="branch_id" label="{{ __('Branch') }}" required><option value="">{{ __('Select') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} · {{ $branch->name_en }}</option>@endforeach</flux:select>
                    <flux:select name="store_id" label="{{ __('Store') }}" required><option value="">{{ __('Select') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }} · {{ $store->name_en }}</option>@endforeach</flux:select>
                    <flux:input name="location" label="{{ __('Current location') }}" />
                    <flux:select name="condition" label="{{ __('Condition') }}" required><option value="good">{{ __('Good') }}</option><option value="fair">{{ __('Fair') }}</option><option value="poor">{{ __('Poor') }}</option></flux:select>
                    @can('rental_assets.cost_edit')<flux:input name="cost_value" type="number" step="0.01" min="0" label="{{ __('Cost value') }}" />@endcan
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><flux:button type="submit" variant="primary">{{ __('Create asset') }}</flux:button></div>
                </form>
            </flux:card>
            @endif
        @endcan
        @if(in_array($mode, ['calendar', 'reservations'], true))
        <flux:card class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><flux:heading size="lg">{{ __('Reservation calendar') }}</flux:heading><flux:text>{{ __('Bounded reservations for the selected date and authorized location scope.') }}</flux:text></div>
                <flux:badge color="zinc">{{ $calendarReservations->count() }} / 100 {{ __('shown') }}</flux:badge>
            </div>
            <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><input type="hidden" name="mode" value="{{ $mode }}"><flux:input name="from" type="date" value="{{ request('from', $calendarStart->toDateString()) }}" label="{{ __('From') }}" /><flux:input name="to" type="date" value="{{ request('to', $calendarEnd->toDateString()) }}" label="{{ __('To') }}" /><flux:select name="store_id" label="{{ $ar ? 'موقع العمل' : 'Work location' }}"><option value="">{{ $ar ? 'كل المواقع المصرح بها' : 'All authorized locations' }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->code }} · {{ $ar ? $store->name_ar : $store->name_en }}</option>@endforeach</flux:select><div class="flex items-end"><flux:button type="submit" variant="primary" icon="funnel">{{ __('Apply filters') }}</flux:button></div></form>
            @if ($calendarReservations->isEmpty())
                <x-state.empty :title="__('No upcoming reservations.')" :description="__('New reservations will appear here after they pass the conflict check.')" />
            @else
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-[720px] w-full text-sm"><thead><tr class="bg-zinc-50 text-start dark:bg-zinc-800/60"><th class="p-3">{{ __('Asset') }}</th><th class="p-3">{{ __('Starts') }}</th><th class="p-3">{{ __('Ends') }}</th><th class="p-3">{{ __('Location') }}</th><th class="p-3">{{ __('Reference') }}</th></tr></thead><tbody>
                        @foreach ($calendarReservations as $reservation)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800"><td class="p-3 font-medium">{{ $reservation->asset->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $reservation->asset->name_ar : $reservation->asset->name_en }}</td><td class="p-3">{{ $reservation->starts_at?->format('Y-m-d H:i') }}</td><td class="p-3">{{ $reservation->ends_at?->format('Y-m-d H:i') }}</td><td class="p-3">{{ $reservation->store?->code ?: __('Not recorded') }}</td><td class="p-3">{{ $reservation->source_reference ?: __('Not recorded') }}</td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @endif
        </flux:card>
        @endif
        @if($mode === 'history')
            <form method="GET" class="grid gap-3 rounded-2xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5"><input type="hidden" name="mode" value="history"><flux:input name="q" value="{{ $term }}" label="{{ $ar ? 'الأصل' : 'Asset' }}" placeholder="{{ $ar ? 'الكود أو الاسم' : 'Code or name' }}" /><flux:input name="from" type="date" value="{{ request('from') }}" label="{{ __('From') }}" /><flux:input name="to" type="date" value="{{ request('to') }}" label="{{ __('To') }}" /><flux:select name="store_id" label="{{ $ar ? 'موقع العمل' : 'Work location' }}"><option value="">{{ $ar ? 'كل المواقع المصرح بها' : 'All authorized locations' }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->code }} · {{ $ar ? $store->name_ar : $store->name_en }}</option>@endforeach</flux:select><div class="flex items-end"><flux:button type="submit" variant="primary" icon="funnel">{{ __('Apply filters') }}</flux:button></div></form>
            <x-tables.data-panel :title="__('Immutable asset events')" :description="__('Depreciation, damage, inspection, loss, and maintenance events in your authorized scope.')">
                <div class="overflow-x-auto"><table class="data-table min-w-[920px] w-full"><thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Event') }}</th><th>{{ __('Status') }}</th><th>{{ __('Assessment') }}</th><th>{{ __('Responsible user') }}</th><th class="text-end">{{ __('Operational cost') }}</th><th class="text-end">{{ __('Approval') }}</th></tr></thead><tbody>
                    @forelse($historyEvents as $event)<tr><td><span class="font-mono">{{ $event->asset?->code }}</span></td><td>{{ str($event->event_type)->headline() }}</td><td><x-status.badge :status="$event->status" /></td><td>{{ $event->assessment }}</td><td>{{ $event->responsibleUser?->name ?: __('System') }}</td><td class="text-end tabular-nums">@can('rental_assets.cost_view'){{ $event->cost_value === null ? '—' : number_format((float) $event->cost_value, 2).' '.$event->cost_currency }}@else{{ __('Restricted') }}@endcan</td><td class="text-end">@if($event->status === 'submitted') @can('rental_assets.approve') @if($event->approvalRecord?->requester_id !== auth()->id())<form method="POST" action="{{ route('party.asset-events.approve', $event) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Approve asset event')">{{ __('Approve asset event') }}</x-actions.button></form>@else<flux:text class="text-xs text-text-muted">{{ __('Requester cannot approve') }}</flux:text>@endif @else<flux:text class="text-xs text-text-muted">{{ __('Awaiting reviewer') }}</flux:text>@endcan @else<flux:text class="text-xs text-text-muted">{{ __('Approved') }}</flux:text>@endif</td></tr>@empty<tr><td colspan="7"><x-state.empty :title="__('No asset history found.')" :description="__('Approved and pending asset events will appear here.')" /></td></tr>@endforelse
                </tbody></table></div><x-slot:footer>@if($historyEvents->hasPages()){{ $historyEvents->links() }}@endif</x-slot:footer>
            </x-tables.data-panel>
        @endif
        @if(in_array($mode, ['catalog', 'reservations', 'returns'], true))
        <flux:card class="overflow-hidden p-0">
            <div id="asset-filters" class="border-b border-border p-4">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><flux:heading size="lg">{{ __('Asset register') }}</flux:heading><flux:text>{{ app()->isLocale('ar-EG') ? 'ادوّر في الهوية والموقع والتصنيف، ثم صف النتائج ضمن نطاقك.' : ($ar ? 'ابحث في الهوية والموقع والتصنيف، ثم صف النتائج ضمن نطاقك.' : 'Search identity, location, and category, then filter within your scope.') }}</flux:text></div><flux:badge color="zinc">{{ number_format($assets->total()) }} {{ $ar ? 'سجل' : 'records' }}</flux:badge></div>
                <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"><input type="hidden" name="mode" value="{{ $mode }}"><flux:input name="q" value="{{ $term }}" label="{{ app()->isLocale('ar-EG') ? 'دوّر' : ($ar ? 'بحث' : 'Search') }}" placeholder="{{ $ar ? 'الكود أو الاسم أو الموقع' : 'Code, name, or location' }}" /><flux:select name="store_id" label="{{ $ar ? 'موقع العمل' : 'Work location' }}"><option value="">{{ $ar ? 'كل المواقع المصرح بها' : 'All authorized locations' }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->code }} · {{ $ar ? $store->name_ar : $store->name_en }}</option>@endforeach</flux:select><flux:select name="status" label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach(['available','reserved','checked_out','under_inspection','damaged','under_maintenance','retired','lost'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __((string) str($status)->replace('_', ' ')->headline()) }}</option>@endforeach</flux:select><flux:select name="condition" label="{{ __('Condition') }}"><option value="">{{ $ar ? 'كل الحالات' : 'All conditions' }}</option>@foreach(['good','fair','poor'] as $condition)<option value="{{ $condition }}" @selected(request('condition') === $condition)>{{ __(ucfirst($condition)) }}</option>@endforeach</flux:select><flux:select name="category" label="{{ __('Category') }}"><option value="">{{ $ar ? 'كل التصنيفات' : 'All categories' }}</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</flux:select><div class="flex items-end gap-2"><flux:button type="submit" variant="primary" icon="funnel">{{ __('Apply filters') }}</flux:button><flux:button href="{{ route('party.assets.index', ['mode' => $mode]) }}" variant="subtle" icon="arrow-path">{{ __('Reset') }}</flux:button></div></form>
            </div>
            <div class="hidden overflow-x-auto md:block"><table class="data-table min-w-[820px] w-full text-sm"><thead><tr><th>{{ __('Identity') }}</th><th>{{ __('Location') }}</th><th>{{ __('Availability') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Next action') }}</th></tr></thead><tbody>
                @forelse($assets as $asset)
                    @php($reservation = $asset->reservations->firstWhere('status', 'reserved'))
                    @php($checkout = $asset->checkouts->first())
                    @php($assetReturn = $asset->returns->first())
                    <tr class="border-t border-zinc-100 dark:border-zinc-800 align-top">
                        <td><div class="font-semibold">{{ $asset->code }}</div><div class="text-xs text-zinc-500">{{ str_starts_with(app()->getLocale(), 'ar') ? $asset->name_ar : $asset->name_en }}</div><div class="mt-1 text-xs text-zinc-500">{{ __('History records') }}: {{ $asset->events_count + $asset->returns_count + $asset->checkouts_count }}</div></td>
                        <td>{{ $asset->store?->code }}<div class="text-xs text-zinc-500">{{ $asset->location ?: __('Not recorded') }}</div></td>
                        <td><x-status.badge :status="$asset->status" /><div class="mt-1 text-xs text-zinc-500">{{ $asset->reservations_count }} {{ __('reservation records') }}</div></td>
                        <td>{{ ucfirst($asset->condition) }}</td>
                        <td class="min-w-[300px]"><x-assets.actions :asset="$asset" :reservation="$reservation" :checkout="$checkout" :asset-return="$assetReturn" /></td>
                    </tr>
                @empty <tr><td colspan="5"><x-state.empty :title="__('No rental assets yet.')" :description="__('Create the first asset to start the availability calendar and history.')" /></td></tr>@endforelse
            </tbody></table></div>
            <div class="grid gap-3 p-4 md:hidden">@forelse($assets as $asset)@php($reservation = $asset->reservations->firstWhere('status', 'reserved'))@php($checkout = $asset->checkouts->first())@php($assetReturn = $asset->returns->first())<article class="rounded-2xl border border-border bg-surface p-4"><header class="flex items-start justify-between gap-3"><div class="min-w-0"><strong class="block truncate font-mono" dir="ltr">{{ $asset->code }}</strong><span class="block truncate text-sm text-text-muted">{{ $ar ? $asset->name_ar : $asset->name_en }}</span></div><x-status.badge :status="$asset->status" /></header><dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-text-muted">{{ __('Location') }}</dt><dd>{{ $asset->store?->code }} · {{ $asset->location ?: '—' }}</dd></div><div><dt class="text-text-muted">{{ __('Condition') }}</dt><dd>{{ __(ucfirst($asset->condition)) }}</dd></div><div><dt class="text-text-muted">{{ __('Category') }}</dt><dd>{{ $asset->category ?: '—' }}</dd></div><div><dt class="text-text-muted">{{ $ar ? 'سجلات الدورة' : 'Lifecycle records' }}</dt><dd>{{ $asset->events_count + $asset->returns_count + $asset->checkouts_count }}</dd></div></dl><x-assets.actions class="mt-4" :asset="$asset" :reservation="$reservation" :checkout="$checkout" :asset-return="$assetReturn" /></article>@empty<x-state.empty :title="__('No rental assets yet.')" :description="__('Create the first asset to start the availability calendar and history.')" />@endforelse</div>
            <div class="border-t border-border p-4"><x-tables.pagination :paginator="$assets" /></div>
        </flux:card>
        @endif
    </x-app.page>
</x-layouts::app>
