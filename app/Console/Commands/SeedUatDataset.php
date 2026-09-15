<?php

namespace App\Console\Commands;

use App\Modules\Platform\Services\UatDataset;
use Illuminate\Console\Command;

final class SeedUatDataset extends Command
{
    protected $signature = 'rajeh:uat-seed {--company= : Existing active company ID} {--dry-run : Report without writing}';
    protected $description = 'Create an explicit marked reversible Rajeh ERP UAT dataset.';

    public function handle(UatDataset $dataset): int
    {
        $company=(int)$this->option('company'); if($company<1){$this->error('--company=<id> is required.');return self::INVALID;}
        $result=$dataset->seed($company,(bool)$this->option('dry-run')); $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); return self::SUCCESS;
    }
}
