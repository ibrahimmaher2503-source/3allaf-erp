@php
    $initial = $products->map(function ($product) use ($restored, $prefill) {
        $selection = collect($restored)->first(
            fn ($row) => (int) ($row['product_id'] ?? 0) === $product->id
        ) ?? [];

        return [
            'id' => $product->id,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'item_code' => $product->item_code,
            'model_number' => $product->model_number,
            'barcodes' => $product->barcodes->map(fn ($barcode) => [
                'id' => $barcode->id,
                'barcode' => $barcode->barcode,
                'is_primary' => (bool) $barcode->is_primary,
            ])->values(),
            'copies' => (int) ($selection['copies'] ?? ($prefill[$product->id] ?? 1)),
            'barcode_id' => (int) ($selection['barcode_id'] ?? $product->barcodes->first()?->id),
        ];
    })->values();
@endphp

<x-app.page
    :title="__('Barcode labels')"
    :description="__('Search for products, select label copies, then preview the real barcode labels.')"
    :breadcrumbs="__('Products & inventory')"
    max-width="6xl"
    class="pricing-screen"
>
    <x-slot:actions>
        <flux:button href="{{ route('pricing.labels') }}" variant="subtle" icon="arrow-path">
            {{ __('Reset') }}
        </flux:button>
        <flux:button href="{{ route('pricing.index') }}" variant="subtle" icon="arrow-left">
            {{ __('Back to pricing') }}
        </flux:button>
    </x-slot:actions>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Labels could not be prepared') }}">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    @include('pricing.labels.workspace')
</x-app.page>

@include('pricing.labels.workspace-script')
