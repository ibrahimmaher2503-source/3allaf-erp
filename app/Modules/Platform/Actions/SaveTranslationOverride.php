<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\TranslationOverride;
use App\Modules\Platform\Support\TranslationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveTranslationOverride
{
    public function execute(User $user, string $locale, string $group, string $key, string $value): void
    {
        Gate::forUser($user)->authorize('company_settings.edit');
        $entry = app(TranslationCatalog::class)->find($group, $key);

        if (! in_array($locale, ['ar', 'en'], true) || $entry === null) {
            throw ValidationException::withMessages(['translationKey' => __('Select a system translation key.')]);
        }

        if (trim($value) === '') {
            throw ValidationException::withMessages(['values.'.$locale => __('A translation value is required.')]);
        }

        if (mb_strlen($value) > 4000 || $this->placeholders($value) !== $this->placeholders($entry[$locale])) {
            throw ValidationException::withMessages(['values.'.$locale => __('Keep the same placeholders as the base translation.')]);
        }

        DB::transaction(function () use ($user, $locale, $group, $key, $value, $entry): void {
            $existing = TranslationOverride::query()->where(compact('locale', 'group'))->where('translation_key', $key)->first();
            $before = $existing?->only(['locale', 'group', 'translation_key', 'value', 'updated_by']);

            if ($value === $entry[$locale]) {
                $existing?->delete();
                if ($existing !== null) {
                    app(RecordAuditEvent::class)->execute('platform', 'translation_override_reset', TranslationOverride::class, $before, null, metadata: ['locale' => $locale, 'group' => $group, 'translation_key' => $key]);
                }

                return;
            }

            $override = TranslationOverride::query()->updateOrCreate(
                ['locale' => $locale, 'group' => $group, 'translation_key' => $key],
                ['value' => $value, 'updated_by' => $user->id],
            );
            app(RecordAuditEvent::class)->execute('platform', 'translation_override_saved', $override, $before, $override->only(['locale', 'group', 'translation_key', 'value', 'updated_by']));
        });
    }

    /** @return array<int, string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        sort($matches[0]);

        return $matches[0];
    }
}
