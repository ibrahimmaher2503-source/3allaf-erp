@php($displayName = str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en)
@php($user = auth()->user())
@php($sourcePresenter = app(\App\Modules\Platform\Support\AuditLogPresentation::class))
<x-layouts::app :title="__('Loyalty ledger').' · '.$displayName">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-cyan-700">{{ __('Loyalty') }}</p>
                <flux:heading size="xl" class="mt-2">{{ __('Loyalty ledger') }}</flux:heading>
                <flux:text class="mt-1">{{ $displayName }} · <span dir="ltr">{{ $customer->phone_display }}</span></flux:text>
            </div>
            <div class="flex flex-wrap gap-2"><x-context-help :title="__('Loyalty behavior')" :label="__('Open loyalty help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('Points are earned only from approved eligible sales under the active company policy.') }}</li><li>{{ __('Redemptions use available unexpired points oldest first.') }}</li><li>{{ __('Manual adjustments require the configured approval separation and never rewrite ledger history.') }}</li></ul></x-context-help><flux:button href="{{ route('customers.show', $customer) }}" variant="subtle" icon="arrow-left">{{ __('Customer profile') }}</flux:button><flux:button href="{{ route('customers.index') }}" variant="ghost">{{ __('All customers') }}</flux:button></div>
        </div>
        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="ledger-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                    <div><flux:heading id="ledger-heading" size="lg">{{ __('Immutable points history') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('Every entry has a source, rule snapshot, idempotency key, and before/after balance.') }}</flux:text></div>
                    <div class="text-end"><div class="text-3xl font-black tabular-nums" dir="ltr">{{ number_format($balance) }}</div><div class="text-xs text-slate-500">{{ __('Current balance') }}</div></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-start text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-zinc-950/50"><tr><th class="px-5 py-3">{{ __('Event') }}</th><th class="px-5 py-3">{{ __('Points') }}</th><th class="px-5 py-3">{{ __('Balance') }}</th><th class="px-5 py-3">{{ __('Effective / expiry') }}</th><th class="px-5 py-3">{{ __('Source') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                            @forelse ($entries as $entry)
                                <tr><td class="px-5 py-4"><div class="font-semibold">{{ __($entry->event_type) }}</div><div class="mt-1 text-xs text-slate-500">{{ $entry->activity }} · {{ $entry->rule_version }}</div></td><td class="px-5 py-4 font-mono font-bold {{ $entry->points < 0 ? 'text-rose-700' : 'text-emerald-700' }}" dir="ltr">{{ $entry->points > 0 ? '+' : '' }}{{ number_format($entry->points) }}</td><td class="px-5 py-4 font-mono" dir="ltr">{{ number_format($entry->balance_after) }}</td><td class="px-5 py-4 text-xs text-slate-500"><div>{{ optional($entry->effective_at)->format('Y-m-d H:i') }}</div>@if ($entry->expires_at)<div class="mt-1">{{ __('Expires') }}: {{ $entry->expires_at->format('Y-m-d H:i') }}</div>@endif</td><td class="px-5 py-4 text-xs"><div>{{ $sourcePresenter->source($entry->source_type) }}</div><div class="mt-1 font-mono text-slate-500">{{ $entry->source_id ?? '—' }}</div></td></tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center"><x-state.empty :title="__('No loyalty entries yet.')" :description="__('The first approved customer sale will create an earn entry when the policy is configured.')"><x-slot:action>@can('company_settings.view')<flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="subtle" icon="cog-6-tooth">{{ __('Review loyalty policy') }}</flux:button>@endcan</x-slot:action></x-state.empty></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($entries->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-zinc-800">{{ $entries->links() }}</div>@endif
            </section>

            <aside class="space-y-6">
                @can('loyalty.redeem')
                    <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20">
                        <flux:heading size="lg">{{ __('Redeem points') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Redemption is FIFO, cannot exceed available unexpired points, and is linked to an approved sale.') }}</flux:text>
                        <form method="POST" action="{{ route('customers.loyalty.redeem', $customer) }}" class="mt-4 space-y-3">
                            @csrf
                            <flux:select name="source_sale_id" :label="__('Approved sale')" required>
                                @foreach ($approvedSales as $sale)<flux:select.option value="{{ $sale->id }}">{{ $sale->document_number ?? '#'.$sale->id }} · {{ $sale->total }}</flux:select.option>@endforeach
                            </flux:select>
                            <flux:input name="points" :label="__('Points')" type="number" min="1" step="1" required />
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <flux:button type="submit" variant="primary" :disabled="$approvedSales->isEmpty()">{{ __('Record redemption') }}</flux:button>
                        </form>
                    </section>
                @endcan
                @can('loyalty.adjust')
                    <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
                        <flux:heading size="lg">{{ __('Request adjustment') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Adjustments are pending until a separate authorized approver accepts them. Negative adjustments cannot create a negative balance.') }}</flux:text>
                        <form method="POST" action="{{ route('customers.loyalty.adjustments.store', $customer) }}" class="mt-4 space-y-3">
                            @csrf
                            <flux:input name="points" :label="__('Signed points')" type="number" min="-1000000" max="1000000" step="1" required />
                            <flux:input name="source_reference" :label="__('Source reference')" />
                            <flux:textarea name="reason" :label="__('Reason')" required />
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <flux:button type="submit" variant="subtle">{{ __('Submit for approval') }}</flux:button>
                        </form>
                    </section>
                @endcan
                @can('loyalty.expire')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ __('Expiry processing') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Due entries are consumed oldest-first through compensating immutable expiry entries.') }}</flux:text>
                        <form method="POST" action="{{ route('customers.loyalty.expire', $customer) }}" class="mt-4">@csrf<flux:button type="submit" variant="subtle">{{ __('Post due expiry') }}</flux:button></form>
                    </section>
                @endcan
            </aside>
        </div>

        @if ($adjustments->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Adjustment approvals') }}</flux:heading>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($adjustments as $adjustment)
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800"><div class="flex justify-between gap-3"><span class="font-mono font-bold" dir="ltr">{{ $adjustment->points > 0 ? '+' : '' }}{{ number_format($adjustment->points) }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold dark:bg-zinc-800">{{ __($adjustment->status) }}</span></div><p class="mt-2 text-sm">{{ $adjustment->reason }}</p><p class="mt-2 text-xs text-slate-500">{{ __('Requested') }}: {{ optional($adjustment->created_at)->format('Y-m-d H:i') }}</p>@if ($adjustment->approvalRecord && $adjustment->approvalRecord->approval_state?->value === 'pending' && $user->can('loyalty.approve'))<div class="mt-3 flex flex-wrap gap-2"><form method="POST" action="{{ route('loyalty.adjustments.approve', $adjustment->approvalRecord) }}">@csrf<x-actions.button type="submit" semantic="approve" :label="__('Approve and post')">{{ __('Approve and post') }}</x-actions.button></form><form method="POST" action="{{ route('loyalty.adjustments.reject', $adjustment->approvalRecord) }}" class="flex flex-wrap items-end gap-2">@csrf<flux:input name="decision_note" :label="__('Rejection reason')" required /><x-actions.button type="submit" semantic="reject" :label="__('Reject')">{{ __('Reject') }}</x-actions.button></form></div>@endif</div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
