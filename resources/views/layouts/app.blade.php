@include('layouts.app.sidebar', [
    'title' => $title ?? null,
    'slot' => $slot,
])
