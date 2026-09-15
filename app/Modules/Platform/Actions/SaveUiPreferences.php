<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\UserUiPreference;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class SaveUiPreferences
{
    private const ALLOWED = [
        'appearance' => ['light', 'dark', 'system'],
        'accent_color' => ['teal', 'indigo', 'amber', 'rose'],
        'sidebar_mode' => ['expanded', 'collapsed'],
        'navbar_mode' => ['sticky', 'static'],
        'content_width' => ['compact', 'wide'],
        'table_density' => ['comfortable', 'compact'],
        'font_scale' => ['small', 'normal', 'large'],
        'reduced_motion' => [true, false, 0, 1, '0', '1'],
    ];

    public function execute(User $user, array $input): UserUiPreference
    {
        $values = Arr::only($input, array_keys(self::ALLOWED));

        foreach (self::ALLOWED as $key => $allowed) {
            if (array_key_exists($key, $values) && ! in_array($values[$key], $allowed, true)) {
                throw ValidationException::withMessages([$key => 'Unsupported UI preference.']);
            }
        }

        if (array_key_exists('reduced_motion', $values)) {
            $values['reduced_motion'] = filter_var($values['reduced_motion'], FILTER_VALIDATE_BOOLEAN);
        }

        return $user->uiPreference()->updateOrCreate([], [
            ...UserUiPreference::defaults(),
            ...$values,
        ]);
    }
}
