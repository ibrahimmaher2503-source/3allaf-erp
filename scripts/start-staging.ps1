$ErrorActionPreference = 'Stop'
$env:APP_ENV = 'staging'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8793'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'toyjoy_tsk_env_20260809'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
$env:QUEUE_CONNECTION = 'database'
$env:CACHE_STORE = 'database'
$env:SESSION_DRIVER = 'database'
$env:FILESYSTEM_DISK = 'local'
$env:BACKUP_ARCHIVE_PASSWORD = 'StagingOnly-Backup-2026!'
$env:PATH = 'C:\xampp\mysql\bin;' + $env:PATH

$logDirectory = 'C:\staging'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$process = Start-Process -FilePath 'php' `
    -ArgumentList 'artisan serve --host=127.0.0.1 --port=8793' `
    -WorkingDirectory (Join-Path $PSScriptRoot '..') `
    -WindowStyle Hidden `
    -PassThru `
    -RedirectStandardOutput (Join-Path $logDirectory 'toyjoy-staging-server.out.log') `
    -RedirectStandardError (Join-Path $logDirectory 'toyjoy-staging-server.err.log')
Write-Output $process.Id
