<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();
    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix38', 'Candidate version mismatch.');
    config(['cache.default' => 'array', 'session.driver' => 'array', 'session.lottery' => [0, 100]]);
    app('cache')->forgetDriver();
    app('session')->forgetDrivers();
    $user = User::query()->where('status', 'active')->where('is_super_admin', true)->orderBy('id')->first();
    $assert($user instanceof User, 'No active Super Admin is available for authenticated smoke rendering.');
    $kernel = $app->make(HttpKernel::class);
    $sessionManager = app('session');
    $encrypter = app('encrypter');

    $render = static function (string $uri, string $locale) use ($app, $encrypter, $kernel, $sessionManager, $user): array {
        /** @var SessionStore $session */
        $session = $sessionManager->driver();
        $session->setId(bin2hex(random_bytes(20)));
        $session->start();
        $session->put('locale', $locale);
        $session->save();
        $cookie = $encrypter->encrypt(CookieValuePrefix::create($session->getName(), $encrypter->getKey()).$session->getId(), false);
        $request = Request::create($uri, 'GET', [], [$session->getName() => $cookie, 'locale' => $locale], [], ['HTTP_ACCEPT' => 'text/html']);
        $request->setUserResolver(static fn (): User => $user);
        Auth::setUser($user);
        $app->instance('request', $request);
        $response = $kernel->handle($request);
        try { return [$response->getStatusCode(), (string) $response->getContent()]; }
        finally { $kernel->terminate($request, $response); }
    };

    DB::beginTransaction();
    try {
        $checks = [
            'ar' => ['/admin/settings?tab=payments', 'إضافة طريقة دفع'],
            'en' => ['/admin/settings?tab=tax', 'Add Tax'],
            'ar-EG' => ['/admin/settings?tab=printers', 'افتح مساعدة الطابعات والقوالب'],
        ];
        foreach ($checks as $locale => [$uri, $needle]) {
            [$status, $html] = $render($uri, $locale);
            $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';
            $assert($status === 200, "{$locale} smoke returned HTTP {$status}.");
            $assert((bool) preg_match('/<html\s+lang="'.preg_quote($locale, '/').'"\s+dir="'.$direction.'"/s', $html), "{$locale} lang/dir mismatch.");
            $assert(str_contains($html, $needle), "{$locale} focused settings content is missing.");
            echo "HOTFIX38_AUTHENTICATED_SMOKE=PASS locale={$locale} uri={$uri}\n";
        }
    } finally {
        DB::rollBack();
    }
    echo "HOTFIX38_SMOKE_RENDER=PASS locales=ar,en,ar-EG database_mutation=rolled_back\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX38_SMOKE_RENDER=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
