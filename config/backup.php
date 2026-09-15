<?php

/*
 * Keep the maintained package defaults, while making the project baseline
 * explicit: include the application files (including protected attachments),
 * verify every archive, and never silently send backup notifications to the
 * package placeholder address.
 */
$config = require base_path('vendor/spatie/laravel-backup/config/backup.php');

$config['backup']['verify_backup'] = true;
$config['backup']['source']['files']['include'] = [
    app_path(),
    base_path('bootstrap'),
    config_path(),
    database_path(),
    public_path(),
    resource_path(),
    base_path('routes'),
    storage_path('app/private'),
];
$config['backup']['source']['files']['exclude'] = array_merge(
    $config['backup']['source']['files']['exclude'],
    [
        base_path('testing'),
        base_path('artifacts'),
        base_path('.git'),
        storage_path('logs'),
        storage_path('app/backup-temp'),
        storage_path('app/private/TOY & JOY'),
        storage_path('app/private/tsk001-smoke'),
        storage_path('app/private/staging-files'),
        storage_path('app/private/staging-all'),
    ],
);
$config['backup']['password'] = env('BACKUP_ARCHIVE_PASSWORD');
$config['backup']['encryption'] = env('BACKUP_ARCHIVE_ENCRYPTION', 'default');
$config['backup']['destination']['disks'] = array_values(array_filter(
    explode(',', (string) env('BACKUP_DISKS', 'local')),
));
$config['backup']['destination']['continue_on_failure'] = false;
$config['backup']['notifications']['notifications'] = [];
$config['backup']['notifications']['mail']['to'] = env('BACKUP_NOTIFICATION_EMAIL');
$config['backup']['monitor_backups'][0]['disks'] = $config['backup']['destination']['disks'];

return $config;
