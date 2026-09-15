<?php

namespace App\Console\Commands;

use App\Modules\Platform\Actions\BackfillLegacySettingsAuditLogs as BackfillAction;
use Illuminate\Console\Command;

class BackfillLegacySettingsAuditLogs extends Command
{
    protected $signature = 'platform:backfill-legacy-settings-audit';

    protected $description = 'Idempotently copy historical settings audit rows into audit_logs';

    public function handle(BackfillAction $backfill): int
    {
        $inserted = $backfill->execute();

        $this->info("Legacy settings audit backfill complete: {$inserted} row(s) inserted.");

        return self::SUCCESS;
    }
}
