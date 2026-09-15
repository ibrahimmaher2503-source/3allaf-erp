@props(['readiness', 'showCounts' => false, 'compact' => false])

@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $affected = collect($readiness['affected_products'] ?? []);
@endphp

<section {{ $attributes->class(['rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 sm:p-4', 'text-sm' => $compact]) }} aria-label="{{ __('Product and pricing readiness') }}" data-pricing-readiness>
    @if ($showCounts)
        <div class="grid gap-2 sm:grid-cols-3">
            <div><span class="block text-xs text-text-muted">{{ __('Inherited prices') }}</span><strong dir="ltr">{{ $readiness['inherited_count'] }}</strong></div>
            <div><span class="block text-xs text-text-muted">{{ __('Effective prices') }}</span><strong dir="ltr">{{ $readiness['effective_count'] }}</strong></div>
            <div><span class="block text-xs text-text-muted">{{ __('Manual overrides') }}</span><strong dir="ltr">{{ $readiness['manual_override_count'] }}</strong></div>
        </div>
        <p class="mt-2 text-xs text-text-muted">{{ __('List 0 inherits the Product Card base consumer price by default.') }} {{ __('Manual overrides are explicit exceptions only. A zero count does not mean List 0 has no effective prices.') }}</p>
    @endif

    @if (! ($readiness['product_cards_complete'] ?? false))
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <strong>{{ __('Affected Product Cards: :count', ['count' => $readiness['affected_count']]) }}</strong>
            @if ($readiness['has_more_affected'] ?? false)
                <a href="{{ route('catalog.products', ['readiness' => 'affected']) }}" class="font-semibold text-primary underline" wire:navigate>{{ __('View all affected products') }}</a>
            @endif
        </div>
        <ul class="mt-2 grid gap-2">
            @foreach ($affected as $product)
                <li class="flex flex-col gap-2 rounded-lg border border-amber-500/20 bg-surface p-2 sm:flex-row sm:items-center sm:justify-between">
                    <span class="min-w-0">
                        <strong class="block truncate">{{ $isArabic ? ($product['name_ar'] ?: __('Name not provided')) : ($product['name_en'] ?: __('Name not provided')) }}</strong>
                        <span class="text-xs text-text-muted"><span dir="ltr">{{ $product['item_code'] }}</span> · {{ __('Current base consumer price') }}: <span dir="ltr">{{ $product['sale_price'] === null ? __('Not set') : $product['sale_price'] }}</span></span>
                    </span>
                    @if($product['issue_message'] ?? null)<p class="text-xs text-rose-700">{{ $product['issue_message'] }}</p>@endif
                    <flux:button :href="route('catalog.products.edit', ['product' => $product['id'], 'focus' => 'base-consumer-price']) . '#base-consumer-price'" size="sm" variant="subtle" wire:navigate>{{ __('Edit base consumer price') }}</flux:button>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-amber-900 dark:text-amber-100">{{ __('Complete the affected Product Cards before Pricing can be ready.') }}</p>
    @else
        <p class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">{{ __('All applicable Product Cards have valid mandatory data and positive base consumer prices.') }}</p>
    @endif
</section>
