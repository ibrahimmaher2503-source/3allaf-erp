<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Printer Configuration Preview') }}</title>
    @vite(['resources/css/app.css'])
    <style>@media print {.no-print{display:none!important} body{background:#fff!important}.print-container{border:0!important;box-shadow:none!important}}</style>
</head>
<body class="bg-zinc-100 p-4 sm:p-8">
    @php
        $printerType = [
            'thermal' => __('Thermal printer'),
            'a4' => __('Office printer'),
            'label' => __('Label printer'),
            'pdf' => __('PDF output'),
        ][$printer->printer_type] ?? $printer->printer_type;
        $connectionType = [
            'network' => __('Network'),
            'usb' => __('USB'),
            'bluetooth' => __('Bluetooth'),
            'browser' => __('Browser print'),
        ][$printer->connection_type] ?? $printer->connection_type;
    @endphp
    <div class="no-print mx-auto mb-4 flex max-w-3xl justify-end"><button type="button" onclick="window.print()" class="rounded bg-zinc-900 px-4 py-2 text-sm text-white">{{ __('Print') }}</button></div>
    <main class="print-container mx-auto max-w-3xl space-y-4 rounded-xl border border-zinc-200 bg-white p-8">
        <div class="flex items-start justify-between border-b border-zinc-300 pb-4">
            <div>
                <h1 class="text-xl font-bold">{{ $printer->name }}</h1>
                <p class="text-sm text-zinc-600">{{ __('Printer setup preview') }}</p>
            </div>
            <div class="text-end text-xs font-mono">
                <div>{{ $printerType }}</div>
                <div>{{ $printer->paper_size }}</div>
            </div>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <dt class="font-semibold">{{ __('Print template key') }}</dt><dd>{{ $printer->template_name }}</dd>
            <dt class="font-semibold">{{ __('Connection') }}</dt><dd>{{ $connectionType }}</dd>
            <dt class="font-semibold">{{ __('Scope') }}</dt><dd>{{ $printer->store ? __('Location').': '.$printer->store->code : ($printer->branch ? __('Branch').': '.$printer->branch->code : __('Global workspace')) }}</dd>
            <dt class="font-semibold">{{ __('Status') }}</dt><dd>{{ __($printer->status) }}</dd>
        </dl>
        <div class="border border-dashed border-zinc-400 p-6 text-center text-sm text-zinc-600">
            {{ __('This preview checks the saved printer profile and its assigned template key only. No document, payment data, or hardware behavior is tested.') }}
        </div>
    </main>
</body>
</html>
