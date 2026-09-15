<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

class RestorePlatformBackup extends Command
{
    protected $signature = 'platform:backup:restore
        {archive : Backup archive path or path on the configured local disk}
        {target : Empty isolated directory to receive the restored payload}';

    protected $description = 'Extract a verified platform backup into an isolated restore directory';

    public function handle(): int
    {
        $archive = $this->argument('archive');
        $target = $this->argument('target');
        $archivePath = is_file($archive) ? $archive : storage_path('app/private/'.$archive);
        $targetPath = realpath($target) ?: $target;

        try {
            $this->assertSafeTarget($targetPath);

            $zip = new ZipArchive;
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('The backup archive could not be opened.');
            }

            $password = config('backup.backup.password');
            if (is_string($password) && $password !== '') {
                $zip->setPassword($password);
            }

            if (! $this->containsDatabaseDump($zip)) {
                $zip->close();
                throw new RuntimeException('The backup archive has no database dump and cannot be trusted.');
            }

            if (! is_dir($targetPath) && ! mkdir($targetPath, 0750, true) && ! is_dir($targetPath)) {
                throw new RuntimeException('The isolated restore directory could not be created.');
            }

            if (! $zip->extractTo($targetPath)) {
                throw new RuntimeException('The backup archive could not be extracted.');
            }

            $zip->close();
            Log::info('Platform backup restored into an isolated directory.', [
                'archive' => basename($archivePath),
                'target' => $targetPath,
                'actor' => 'console',
            ]);
            $this->info("Backup extracted to {$targetPath}. Review and verify it before any controlled import.");

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            Log::error('Platform backup restore failed.', [
                'archive' => basename($archivePath),
                'target' => $targetPath,
                'error' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function assertSafeTarget(string $targetPath): void
    {
        $basePath = realpath(base_path());
        $resolvedTarget = realpath($targetPath);
        if ($resolvedTarget === false) {
            $parent = realpath(dirname($targetPath));
            $resolvedTarget = $parent === false
                ? null
                : $parent.DIRECTORY_SEPARATOR.basename($targetPath);
        }

        if ($resolvedTarget !== null && ($resolvedTarget === $basePath || str_starts_with($resolvedTarget, $basePath.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Restore target must be outside the live application directory.');
        }

        if (is_dir($targetPath) && count(array_diff(scandir($targetPath) ?: [], ['.', '..'])) > 0) {
            throw new RuntimeException('Restore target must be empty and isolated.');
        }
    }

    protected function containsDatabaseDump(ZipArchive $zip): bool
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_ends_with(strtolower($name), '.sql')) {
                return true;
            }
        }

        return false;
    }
}
