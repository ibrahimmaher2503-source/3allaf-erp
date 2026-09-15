<?php

namespace App\Modules\Platform\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Throwable;

class GetPlatformStatus
{
    /**
     * Get provider-neutral, local-safe platform health indicators.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $requestId = Context::get('request_id') ?? 'REQ-LOCAL';
        $timestamp = now()->toIso8601String();

        // 1. Database Check
        $dbStatus = 'healthy';
        $dbDriver = config('database.default', 'mysql');
        $dbMessage = 'Connected';

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            $dbStatus = 'down';
            $dbMessage = 'Database connection check failed';
        }

        // 2. Storage Check
        $storageStatus = 'healthy';
        $storageMessage = 'Storage directory writable';

        if (! is_writable(storage_path('app'))) {
            $storageStatus = 'degraded';
            $storageMessage = 'Storage directory is not writable';
        }

        // 3. Cache Check
        $cacheStatus = 'healthy';
        $cacheDriver = config('cache.default', 'file');
        $cacheMessage = 'Cache store operational';

        try {
            Cache::put('health_check_ping', true, 10);
            if (! Cache::get('health_check_ping')) {
                $cacheStatus = 'degraded';
                $cacheMessage = 'Cache write/read mismatch';
            }
        } catch (Throwable $e) {
            $cacheStatus = 'degraded';
            $cacheMessage = 'Cache store check failed';
        }

        // Overall Status Determination
        $overallStatus = 'healthy';
        if ($dbStatus === 'down') {
            $overallStatus = 'down';
        } elseif ($storageStatus === 'degraded' || $cacheStatus === 'degraded') {
            $overallStatus = 'degraded';
        }

        return [
            'status' => $overallStatus,
            'request_id' => $requestId,
            'timestamp' => $timestamp,
            'application' => [
                'name' => config('app.name', 'TOY & JOY'),
                'environment' => app()->environment(),
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone', 'UTC'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'components' => [
                'database' => [
                    'status' => $dbStatus,
                    'driver' => $dbDriver,
                    'message' => $dbMessage,
                ],
                'storage' => [
                    'status' => $storageStatus,
                    'message' => $storageMessage,
                ],
                'cache' => [
                    'status' => $cacheStatus,
                    'driver' => $cacheDriver,
                    'message' => $cacheMessage,
                ],
            ],
        ];
    }
}
