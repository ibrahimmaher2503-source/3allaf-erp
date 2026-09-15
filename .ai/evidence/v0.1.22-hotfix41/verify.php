<?php

declare(strict_types=1);

use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix41', 'Version mismatch.');

    $reportView = file_get_contents($root.'/resources/views/pages/reports/index.blade.php');
    $exportView = file_get_contents($root.'/resources/views/pages/exports/index.blade.php');
    $reportingViews = $reportView."\n".$exportView;

    $assert(! preg_match('/(?:__|trans)\s*\(\s*(?:str\s*\(|Str::of\s*\()/m', $reportingViews), 'A Stringable expression can still reach a translation helper.');
    $assert(! preg_match('/@lang\s*\(\s*(?:str\s*\(|Str::of\s*\()/m', $reportingViews), 'A Stringable expression can still reach @lang.');
    $assert(substr_count($reportingViews, '__((string) str(') === 5, 'Expected scalar report-label translation boundaries are incomplete.');

    foreach (['ar', 'en', 'ar-EG'] as $locale) {
        $translations = json_decode(file_get_contents($root."/lang/{$locale}.json"), true, 512, JSON_THROW_ON_ERROR);
        foreach (['Reports and Export Center', 'Export dataset', 'Document status'] as $key) {
            $assert(filled($translations[$key] ?? null), "{$locale} missing {$key}.");
        }
    }

    $migrationDiff = trim((string) shell_exec('git -C '.escapeshellarg($root).' diff --name-only 08d733692895f1f3bf3f5be70ed17f5c37b5ddb1 -- database/migrations 2>/dev/null'));
    $assert($migrationDiff === '', 'Hotfix41 must not add or change migrations.');

    echo "HOTFIX41_FOCUSED_VERIFICATION=PASS stringable_translation_boundary=scalar migration_change=none database_mutation=none\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX41_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
