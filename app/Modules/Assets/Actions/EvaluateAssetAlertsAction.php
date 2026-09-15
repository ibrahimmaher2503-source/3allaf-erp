<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Reporting\Models\Alert;
use Illuminate\Support\Facades\DB;

final class EvaluateAssetAlertsAction
{
    public function execute(?User $user = null): int
    {
        $created = 0;
        RentalAsset::query()->when($user !== null, fn ($query) => $query->visibleTo($user))->whereIn('status', ['damaged', 'under_maintenance', 'lost'])->chunkById(100, function ($assets) use (&$created): void {
            foreach ($assets as $asset) {
                $alert = Alert::query()->firstOrCreate(['alert_key' => 'asset:'.$asset->id.':'.$asset->status], [
                    'alert_type' => 'asset_issue', 'severity' => $asset->status === 'lost' ? 'critical' : 'warning',
                    'title' => 'Asset requires attention', 'description' => 'Asset '.$asset->code.' is '.$asset->status.'.',
                    'source_type' => RentalAsset::class, 'source_id' => (string) $asset->id, 'branch_id' => $asset->branch_id,
                    'store_id' => $asset->store_id, 'status' => 'open', 'metadata' => ['asset_status' => $asset->status],
                ]);
                if ($alert->wasRecentlyCreated) $created++;
            }
        });

        return $created;
    }
}
