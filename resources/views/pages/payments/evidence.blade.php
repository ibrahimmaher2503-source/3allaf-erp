<x-layouts::app :title="__('Payment Evidence')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
        <div><flux:heading size="xl">{{ __('Payment Evidence') }}</flux:heading><flux:text class="mt-1">{{ __('Private evidence files linked to immutable electronic payment records. Every access is reauthorized and audited.') }}</flux:text></div>
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto] dark:border-zinc-800 dark:bg-zinc-900">
            <flux:input name="q" value="{{ request('q') }}" :label="__('Invoice')" />
            <flux:select name="method_id" :label="__('Method')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($methods as $method)<flux:select.option value="{{ $method->id }}" :selected="(string) request('method_id') === (string) $method->id">{{ str_starts_with(app()->getLocale(), 'ar') ? $method->name_ar : $method->name_en }}</flux:select.option>@endforeach</flux:select>
            <div class="flex items-end"><flux:button type="submit">{{ __('Filter') }}</flux:button></div>
        </form>
        <x-tables.table-shell :label="__('Payment Evidence')">
            <table class="data-table w-full min-w-[780px] text-sm"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Method') }}</th><th>{{ __('Store') }}</th><th>{{ __('File') }}</th><th>{{ __('Status') }}</th><th>{{ __('Uploaded by') }}</th></tr></thead><tbody>
                @forelse ($evidencePayments as $payment)
                    @php($attachment = $payment->evidenceAttachment)
                    <tr><td><a class="font-semibold text-primary hover:underline" href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->document_number }}</a></td><td>{{ str_starts_with(app()->getLocale(), 'ar') ? $payment->paymentMethod->name_ar : $payment->paymentMethod->name_en }}</td><td>{{ $payment->sale->store->code }}</td><td>@if ($attachment)<x-actions.button semantic="download" :label="__('View protected file')" :href="route('payments.evidence.show', $attachment)" target="_blank">{{ __('View protected file') }}</x-actions.button><div class="text-xs text-text-muted">{{ $attachment->detected_mime_type }} · {{ number_format($attachment->size_bytes / 1024, 1) }} KB</div>@else<span class="text-text-muted">{{ __('None') }}</span>@endif</td><td><x-status.badge :status="$attachment?->status->value ?? 'missing'" /></td><td>{{ $payment->creator->name }}</td></tr>
                @empty
                    <tr><td colspan="6"><x-state.empty :title="__('No protected payment evidence matches the selected filters.')" :description="__('Evidence appears here after an electronic payment is recorded.')" /></td></tr>
                @endforelse
            </tbody></table>
        </x-tables.table-shell>
        {{ $evidencePayments->links() }}
    </div>
</x-layouts::app>
