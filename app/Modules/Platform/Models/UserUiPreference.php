<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserUiPreference extends Model
{
    public const DISPLAY_PREFERENCES_KEY = '_display_preferences';

    protected $table = 'user_ui_preferences';

    protected $fillable = [
        'appearance', 'accent_color', 'sidebar_mode', 'navbar_mode',
        'content_width', 'table_density', 'font_scale', 'reduced_motion', 'tutorial_progress',
    ];

    protected function casts(): array
    {
        return ['reduced_motion' => 'boolean', 'tutorial_progress' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaults(): array
    {
        return [
            'appearance' => 'system', 'accent_color' => 'teal', 'sidebar_mode' => 'expanded',
            'navbar_mode' => 'sticky', 'content_width' => 'wide', 'table_density' => 'comfortable',
            'font_scale' => 'normal', 'reduced_motion' => false, 'tutorial_progress' => [],
        ];
    }

    public function showSearchFiltersByDefault(): bool
    {
        $progress = is_array($this->tutorial_progress) ? $this->tutorial_progress : [];

        return (bool) ($progress[self::DISPLAY_PREFERENCES_KEY]['show_search_filters_by_default'] ?? false);
    }
}
