<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Inventory\Actions\RecordStockCountContributionAction;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Models\Store;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? '';

if ($mode === 'setup') {
    DB::transaction(function (): void {
        $store = Store::query()->where('code', 'ST-998')->firstOrFail();
        foreach ([2, 3] as $id) {
            User::factory()->create(['id' => $id, 'username' => 'counter-'.$id, 'email' => 'counter-'.$id.'@invalid.test', 'is_super_admin' => true, 'status' => 'active']);
        }
        $count = StockCount::query()->create(['count_number' => 'CONCURRENT-HF7', 'count_type' => 'partial', 'scope_type' => 'partial', 'branch_id' => $store->branch_id, 'store_id' => $store->id, 'status' => 'in_progress', 'reference_at' => now(), 'opened_at' => now(), 'created_by' => 1, 'manager_id' => 1, 'opened_by' => 1, 'idempotency_key' => 'concurrent-hf7']);
        $count->locations()->create(['store_id' => $store->id, 'snapshot_at' => now()]);
        foreach ([2, 3] as $id) $count->members()->create(['user_id' => $id, 'role' => 'counter']);
        echo $count->id.' '.$store->id.PHP_EOL;
    });
    exit;
}

if ($mode === 'scan') {
    Auth::loginUsingId((int) $argv[2]);
    app(RecordStockCountContributionAction::class)->execute((int) $argv[3], (int) $argv[4], 'SKU-91060', '1', $argv[5], 'parallel-device-'.$argv[2], 'scan');
    echo 'OK'.PHP_EOL;
    exit;
}

$count = StockCount::query()->where('count_number', 'CONCURRENT-HF7')->firstOrFail();
echo 'CONTRIBUTIONS='.$count->contributions()->count().' TOTAL='.$count->contributions()->sum('quantity').PHP_EOL;
