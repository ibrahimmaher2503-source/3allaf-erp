<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ str_starts_with(app()->getLocale(), 'ar')?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('Barcode label preview') }}</title>
    @php($width=(float)$settings['width_mm']) @php($height=(float)$settings['height_mm']) @php($a4=$paper_size==='a4')
    <style>
        @page{size:{{ $a4?'A4':$width.'mm '.$height.'mm' }};margin:0}
        @include('pages.exports.partials.cairo-pdf-styles')
        *{box-sizing:border-box}html,body{margin:0;padding:0;font-family:{{ str_starts_with(app()->getLocale(), 'ar')?'Cairo PDF Arabic':'Cairo PDF Latin' }},Arial,sans-serif;background:#f4f4f5;color:#111827}
        .toolbar{position:sticky;top:0;z-index:2;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;padding:12px;background:#fff;border-bottom:1px solid #d4d4d8}
        .toolbar a,.toolbar button{min-height:44px;border:1px solid #0f766e;border-radius:8px;padding:8px 14px;background:#fff;color:#0f766e;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.toolbar .primary{background:#0f766e;color:#fff}
        .preview-shell{margin:18px auto;width:max-content;max-width:100%;overflow:auto;background:#fff;box-shadow:0 8px 30px #0002}
        .label-grid{display:grid;grid-template-columns:repeat({{ $a4?(int)$settings['a4_columns']:1 }},{{ $width }}mm);grid-template-rows:repeat({{ $a4?(int)$settings['a4_rows']:1 }},{{ $height }}mm);column-gap:{{ (float)($settings['horizontal_gap_mm']??$settings['gap_mm']) }}mm;row-gap:{{ (float)($settings['vertical_gap_mm']??$settings['gap_mm']) }}mm;direction:ltr;align-content:start;justify-content:start;@if($a4) width:210mm;height:297mm;padding:{{ (float)$settings['margin_mm'] }}mm;break-after:page;page-break-after:always;@endif}
        .label{direction:{{ str_starts_with(app()->getLocale(), 'ar')?'rtl':'ltr' }};width:{{ $width }}mm;height:{{ $height }}mm;overflow:hidden;break-inside:avoid;page-break-inside:avoid;text-align:center;padding:{{ $a4?0.8:(float)$settings['margin_mm'] }}mm;background:#fff;display:flex;flex-direction:column;align-items:stretch;justify-content:center}
        .name{font-weight:800;font-size:8pt;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.barcode-svg{display:block;width:100%;height:14mm;shape-rendering:crispEdges}.meta{font-size:6.5pt;line-height:1.2}.price{font-size:9pt;line-height:1.15;font-weight:900}.technical{direction:ltr;unicode-bidi:isolate;font-family:monospace}
        @media print{html,body{background:#fff}.toolbar{display:none!important}.preview-shell{margin:0;box-shadow:none;overflow:visible}.label-grid{margin:0}@if(!$a4).label{page-break-after:always}.label:last-child{page-break-after:auto}@endif}
        @if($pdf??false)html,body,.preview-shell{margin:0;background:#fff;box-shadow:none;overflow:visible}.label-grid{margin:0;page-break-after:auto}.label-grid+.label-grid{page-break-before:always}@if($a4).label-grid{width:194mm;height:281mm}@else.label-grid{display:block;width:{{ $width-(2*(float)$settings['margin_mm']) }}mm;height:{{ $height-(2*(float)$settings['margin_mm']) }}mm;margin:{{ (float)$settings['margin_mm'] }}mm}.label{width:100%;height:100%;padding:0}.barcode-svg{height:7mm}.name{font-size:6pt;line-height:1}.meta{font-size:5pt;line-height:1}.price{font-size:7pt;line-height:1}@endif @endif
    </style>
</head>
<body>
@if($toolbar??false)<nav class="toolbar no-print" aria-label="{{ __('Label preview actions') }}"><a href="{{ route('pricing.labels') }}">{{ __('Back to labels') }}</a><a href="{{ route('pricing.labels') }}">{{ __('Reset') }}</a><button type="button" class="primary" onclick="window.print()">{{ __('Print') }}</button><a href="{{ route('pricing.labels.download') }}" class="primary">{{ __('Download PDF') }}</a></nav>@endif
@php($pages=$a4?collect($labels)->chunk(max(1,(int)$settings['a4_rows']*(int)$settings['a4_columns'])):collect($labels)->map(fn($label)=>collect([$label])))
@unless($pdf??false)<div class="preview-shell" data-canonical-label-renderer data-paper-size="{{ $paper_size }}" data-dpi="{{ $settings['dpi'] }}" data-label-width-mm="{{ $width }}" data-label-height-mm="{{ $height }}" data-barcode-label-count="{{ count($labels) }}">@endunless
@foreach($pages as $page)<main class="label-grid">
@foreach($page as $label)
    <article class="label" data-barcode-symbol data-barcode-value="{{ $label['barcode']->barcode }}" data-copy="{{ $label['copy'] }}">
        @if($settings['show_name'])<div class="name">{{ str_starts_with(app()->getLocale(), 'ar')?$label['product']->name_ar:($label['product']->name_en?:$label['product']->name_ar) }}</div>@endif
        @if($pdf??false)<img class="barcode-svg" alt="{{ $label['barcode']->barcode }}" src="{{ app(\App\Modules\Pricing\Services\BarcodeSvgRenderer::class)->renderPngDataUri($label['barcode']->barcode,$settings['symbology']??'auto') }}">@else{!! app(\App\Modules\Pricing\Services\BarcodeSvgRenderer::class)->render($label['barcode']->barcode,$settings['symbology']??'auto') !!}@endif
        <div class="meta technical" data-human-barcode>{{ $label['barcode']->barcode }}</div>
        @if($settings['show_item_code'])<div class="meta"><span>{{ __('Item code') }}:</span> <span class="technical">{{ $label['product']->item_code }}</span></div>@endif
        @if($settings['show_list_code']&&$label['list'])<div class="meta"><span>{{ __('Price list') }}:</span> <span class="technical">{{ $label['list']->code }}</span></div>@endif
        @if($settings['show_outlet']&&$label['store'])<div class="meta">{{ str_starts_with(app()->getLocale(), 'ar')?$label['store']->name_ar:$label['store']->name_en }}</div>@endif
        @if($settings['show_price']&&$label['price']!==null)<div class="price">{{ number_format((float)$label['price'],2) }} {{ str_starts_with(app()->getLocale(), 'ar')?'ج.م':'EGP' }}</div>@endif
    </article>
@endforeach
</main>@endforeach
@unless($pdf??false)</div>@endunless
</body></html>
