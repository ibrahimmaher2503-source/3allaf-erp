<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\UserUiPreference;
use Illuminate\Support\Facades\DB;

final class SaveTutorialProgress
{
    /** @var list<string> */
    public const STATUSES = ['not_started', 'in_progress', 'completed', 'dismissed'];

    public function execute(User $user, string $screenId, string $status): UserUiPreference
    {
        return DB::transaction(function () use ($user, $screenId, $status): UserUiPreference {
            $preference = $user->uiPreference()->firstOrCreate([], UserUiPreference::defaults());
            $progress = is_array($preference->tutorial_progress) ? $preference->tutorial_progress : [];
            $progress[$screenId] = [
                'status' => $status,
                'updated_at' => now()->toIso8601String(),
            ];

            $preference->forceFill(['tutorial_progress' => $progress])->save();

            return $preference->fresh();
        });
    }
}
