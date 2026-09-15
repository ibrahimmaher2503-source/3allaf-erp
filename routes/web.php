<?php

use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Support\Facades\Route;

Route::get('build/{path}', function (string $path) {
    $buildDirectory = realpath(public_path('build'));
    $file = realpath(public_path('build/'.$path));

    abort_unless(
        $buildDirectory !== false
        && $file !== false
        && str_starts_with($file, $buildDirectory.DIRECTORY_SEPARATOR)
        && is_file($file),
        404,
    );

    $contentType = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'css' => 'text/css; charset=utf-8',
        'js', 'mjs' => 'application/javascript; charset=utf-8',
        'json', 'map' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        default => 'application/octet-stream',
    };

    return response()->file($file, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Content-Type' => $contentType,
    ]);
})->where('path', '.*')->name('build.asset');

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (InitialSetupStatus $setupStatus, \App\Modules\Platform\Support\OperationalDashboardSnapshot $dashboard) {
        $user = request()->user();
        abort_unless($user instanceof \App\Models\User, 403);

        return view('dashboard', [
            'setup' => $setupStatus->snapshot(),
            'dashboard' => $dashboard->for($user),
        ]);
    })->middleware('can:dashboard_reports.view')->name('dashboard');
    require __DIR__.'/retail.php';
    require __DIR__.'/customers.php';
    require __DIR__.'/party.php';
    require __DIR__.'/assets.php';
    require __DIR__.'/quotations.php';
    require __DIR__.'/reporting.php';
    require __DIR__.'/feed-store.php';
    require __DIR__.'/returns-gifts.php';
});

require __DIR__.'/platform.php';
require __DIR__.'/catalog.php';
require __DIR__.'/catalog-data-exchange.php';
require __DIR__.'/purchasing.php';
require __DIR__.'/pricing.php';
require __DIR__.'/inventory.php';
require __DIR__.'/opening-inventory.php';
require __DIR__.'/settings.php';
