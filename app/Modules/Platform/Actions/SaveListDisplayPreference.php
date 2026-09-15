<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\UserUiPreference;
use Illuminate\Support\Facades\DB;

final class SaveListDisplayPreference
{
    public function execute(User $user, bool $showSearchFiltersByDefault): UserUiPreference
    {
        return DB::transaction(function () use ($user, $showSearchFiltersByDefault): UserUiPreference {
            $preference = $user->uiPreference()->lockForUpdate()->firstOrCreate([], UserUiPreference::defaults());
            $progress = is_array($preference->tutorial_progress) ? $preference->tutorial_progress : [];
            $progress[UserUiPreference::DISPLAY_PREFERENCES_KEY] = [
                'show_search_filters_by_default' => $showSearchFiltersByDefault,
            ];

            $preference->forceFill(['tutorial_progress' => $progress])->save();

            return $preference->fresh();
        });
    }
}
