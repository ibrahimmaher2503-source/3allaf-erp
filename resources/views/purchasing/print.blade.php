@php
    use App\Modules\Platform\Models\UserUiPreference;

    $uiPreference = auth()->user()?->uiPreference;
    $uiPreferences = array_merge(UserUiPreference::defaults(), $uiPreference?->only(array_keys(UserUiPreference::defaults())) ?? []);
@endphp
<!DOCTYPE html>
<html
    lang="{{ app()->getLocale() }}"
    dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}"
    data-appearance="{{ $uiPreferences['appearance'] }}"
    data-accent="{{ $uiPreferences['accent_color'] }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (() => {
            const root = document.documentElement;
            const appearance = root.dataset.appearance;
            const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
            const effectiveAppearance = appearance === 'system' ? (systemDark ? 'dark' : 'light') : appearance;
            root.classList.toggle('dark', effectiveAppearance === 'dark');
        })();
    </script>
    <title>{{ __('Purchase Order') }} - {{ $order->po_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; padding: 0 !important; margin: 0 !important; }
            .print-container { width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 font-sans p-4 sm:p-8 min-h-screen">
    <div class="max-w-4xl mx-auto mb-4 no-print flex items-center justify-between">
        <a href="{{ route('purchasing.orders') }}" class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 flex items-center gap-1">
            {{ str_starts_with(app()->getLocale(), 'ar') ? '→' : '←' }} {{ __('Back to Purchase Orders') }}
        </a>
        <button onclick="window.print()" class="px-4 py-2 bg-primary hover:bg-primary/90 text-accent-foreground rounded-lg text-sm font-medium transition shadow">
            🖨️ {{ __('Print A4 document') }}
        </button>
    </div>

    <div class="print-container max-w-4xl mx-auto bg-white dark:bg-zinc-800 p-8 rounded-xl shadow-lg border border-zinc-200 dark:border-zinc-700 space-y-6">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">TOY & JOY</h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Purchase order copy') }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-mono mt-1">{{ __('Tax registration: not configured') }}</p>
            </div>
            <div class="text-end">
                <div class="inline-block px-3 py-1 text-xs font-semibold rounded-full uppercase tracking-wider
                    @if($order->status === 'draft') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                    @elseif($order->status === 'submitted') bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300
                    @elseif($order->status === 'partially_received') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300
                    @elseif($order->status === 'received') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                    @elseif($order->status === 'cancelled') bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300
                    @else bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300 @endif">
                    {{ \App\Modules\Platform\Support\UiLabel::status($order->status) }}
                </div>
                <h2 class="text-xl font-mono font-bold mt-2 text-zinc-900 dark:text-white">{{ $order->po_number }}</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Order Date') }}: {{ $order->order_date?->format('Y-m-d') }}</p>
            </div>
        </div>

        <!-- Supplier & Store Info -->
        <div class="grid grid-cols-2 gap-6 text-sm">
            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700/60">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Supplier Details') }}</h3>
                <p class="font-bold text-zinc-900 dark:text-white">{{ str_starts_with(app()->getLocale(), 'ar') ? $order->supplier->name_ar : ($order->supplier->name_en ?: $order->supplier->name_ar) }}</p>
                <p class="text-xs font-mono text-zinc-500 dark:text-zinc-400">{{ __('Code') }}: {{ $order->supplier->code }}</p>
                @if($order->supplier->contact_name)<p class="text-xs text-zinc-600 dark:text-zinc-300 mt-1">{{ __('Contact') }}: {{ $order->supplier->contact_name }}</p>@endif
                @if($order->supplier->phone)<p class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('Phone') }}: {{ $order->supplier->phone }}</p>@endif
                @if($order->supplier->tax_number)<p class="text-xs text-zinc-600 dark:text-zinc-300 font-mono">{{ __('Tax ID') }}: {{ $order->supplier->tax_number }}</p>@endif
            </div>

            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700/60">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Branch and receiving location') }}</h3>
                <p class="font-bold text-zinc-900 dark:text-white">{{ str_starts_with(app()->getLocale(), 'ar') ? $order->branch?->name_ar : $order->branch?->name_en }}</p>
                @if($order->store)
                    <p class="font-bold text-zinc-900 dark:text-white">{{ str_starts_with(app()->getLocale(), 'ar') ? $order->store->name_ar : $order->store->name_en }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Store Code') }}: {{ $order->store->code }}</p>
                @else
                    <p class="text-xs text-zinc-500 italic">{{ __('No specific store assigned') }}</p>
                @endif
                @if($order->expected_delivery_date)<p class="text-xs text-zinc-600 dark:text-zinc-300 mt-1">{{ __('Expected Delivery') }}: {{ $order->expected_delivery_date->format('Y-m-d') }}</p>@endif
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-lg">
            <table class="w-full text-start text-sm">
                <thead class="bg-zinc-100 dark:bg-zinc-700/50 text-xs font-semibold uppercase text-zinc-600 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-2.5 text-center w-12">#</th>
                        <th class="px-4 py-2.5 text-start">{{ __('Product') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Qty Ordered') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Unit Cost') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Subtotal') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($order->lines as $line)
                        <tr>
                            <td class="px-4 py-2.5 text-center text-xs text-zinc-500 font-mono">{{ $line->line_number }}</td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ str_starts_with(app()->getLocale(), 'ar') ? $line->product->name_ar : ($line->product->name_en ?: $line->product->name_ar) }}</div>
                                <div class="text-xs font-mono text-zinc-500">{{ $line->product->sku ?: $line->product->code }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-end font-mono"><x-product-quantity :value="$line->quantity_ordered" /></td>
                            <td class="px-4 py-2.5 text-end font-mono">{{ number_format((float)$line->unit_cost, 2) }}</td>
                            <td class="px-4 py-2.5 text-end font-mono font-semibold">{{ number_format((float)$line->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals Summary -->
        <div class="flex justify-end">
            <div class="w-64 space-y-2 text-sm bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                    <span>{{ __('Subtotal') }}:</span>
                    <span class="font-mono font-medium text-zinc-900 dark:text-white">{{ number_format((float)$order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                    <span>{{ __('Tax Rate / Amount') }}:</span>
                    <span class="font-mono text-xs text-zinc-500">{{ __('Not configured') }} / {{ number_format((float)$order->tax_amount, 2) }}</span>
                </div>
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-2 flex justify-between font-bold text-base text-zinc-900 dark:text-white">
                    <span>{{ __('Total') }}:</span>
                    <span class="font-mono">{{ number_format((float)$order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Notes / Cancel Reason -->
        @if($order->cancel_reason)
            <div class="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/40 text-xs text-rose-800 dark:text-rose-300">
                <strong>{{ __('Cancellation Reason') }}:</strong> {{ $order->cancel_reason }}
            </div>
        @endif

        @if($order->notes)
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-400">
                <strong>{{ __('Notes') }}:</strong> {{ $order->notes }}
            </div>
        @endif

        <!-- Footer Signatures -->
        <div class="pt-8 grid grid-cols-2 gap-8 text-center text-xs text-zinc-500 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="h-12 border-b border-dashed border-zinc-300 dark:border-zinc-600 mb-2"></div>
                <p>{{ __('Prepared By') }}</p>
                <p class="font-medium text-zinc-700 dark:text-zinc-300">{{ $order->creator?->name ?: __('System Administrator') }}</p>
            </div>
            <div>
                <div class="h-12 border-b border-dashed border-zinc-300 dark:border-zinc-600 mb-2"></div>
                <p>{{ __('Authorized Supplier Signature / Stamp') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
