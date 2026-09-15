<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunPlatformBackup extends Command
{
    protected $signature = 'platform:backup:run {--only-db} {--only-files}';

    protected $description = 'Run the configured verified platform backup with production safety checks';

    public function handle(): int
    {
        if (config('app.env') === 'production' && blank(config('backup.backup.password'))) {
            $this->error('BACKUP_ARCHIVE_PASSWORD must be configured before a production backup can run.');
            Log::critical('Production backup refused because archive encryption is not configured.');

            return self::FAILURE;
        }

        $options = [
            '--disable-notifications' => true,
            '--isolated' => true,
        ];

        if ($this->option('only-db')) {
            $options['--only-db'] = true;
        }

        if ($this->option('only-files')) {
            $options['--only-files'] = true;
        }

        $exitCode = Artisan::call('backup:run', $options, $this->output);

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
