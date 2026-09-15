@php
    $cairoArabic = str_replace('\\', '/', resource_path('fonts/cairo/pdf/cairo-arabic-variable.ttf'));
    $cairoLatin = str_replace('\\', '/', resource_path('fonts/cairo/pdf/cairo-latin-variable.ttf'));
@endphp
@foreach ([400, 500, 600, 700, 800, 900] as $weight)
@font-face {
    font-family: 'Cairo PDF Arabic';
    src: url('{{ $cairoArabic }}') format('truetype');
    font-style: normal;
    font-weight: {{ $weight }};
}
@font-face {
    font-family: 'Cairo PDF Latin';
    src: url('{{ $cairoLatin }}') format('truetype');
    font-style: normal;
    font-weight: {{ $weight }};
}
@endforeach
