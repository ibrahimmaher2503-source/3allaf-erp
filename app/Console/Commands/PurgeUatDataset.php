<?php

namespace App\Console\Commands;

use App\Modules\Platform\Services\UatDataset;
use Illuminate\Console\Command;

final class PurgeUatDataset extends Command
{
    protected $signature = 'rajeh:uat-purge {--company= : Existing active company ID} {--confirm : Confirm destructive marked-record purge} {--dry-run : Report without deleting}';
    protected $description = 'Purge only the selected company marked Rajeh ERP UAT batch.';

    public function handle(UatDataset $dataset): int
    {
        $company=(int)$this->option('company'); if($company<1){$this->error('--company=<id> is required.');return self::INVALID;}
        $dry=(bool)$this->option('dry-run'); if(!$dry&&!$this->option('confirm')){$this->error('Real purge requires --confirm.');return self::INVALID;}
        $result=$dataset->purge($company,$dry,(bool)$this->option('confirm')); $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); return self::SUCCESS;
    }
}
