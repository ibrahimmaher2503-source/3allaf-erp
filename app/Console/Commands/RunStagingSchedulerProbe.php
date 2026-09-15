<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunStagingSchedulerProbe extends Command
{
    protected $signature = 'platform:staging-scheduler-probe';

    protected $description = 'Record one idempotent staging scheduler execution.';

    public function handle(): int
    {
        $key = 'staging-scheduler-probe:'.now()->format('Y-m-d-H-i');
        $executed = Cache::add($key, now()->toIso8601String(), now()->addMinutes(5));

        if ($executed) {
            Log::info('staging_scheduler_probe.executed', ['key' => $key]);
            $this->info('executed '.$key);
        } else {
            Log::info('staging_scheduler_probe.duplicate', ['key' => $key]);
            $this->info('duplicate '.$key);
        }

        return self::SUCCESS;
    }
}
