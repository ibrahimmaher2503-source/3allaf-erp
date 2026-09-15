<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\TranslationOverride;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;
use Throwable;

final class TranslationOverrideLoader extends FileLoader
{
    /** @var array<string, array<string, array<string, string>>> */
    private array $requestOverrides = [];

    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        if (! in_array($locale, ['ar', 'en'], true) || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        if (! Schema::hasTable('translation_overrides')) {
            return $lines;
        }

        try {
            $requestId = app()->bound('request') ? (string) request()->attributes->get('request_id', spl_object_id(request())) : 'console';
            $cacheKey = $requestId.':'.$locale;
            $overrides = $this->requestOverrides[$cacheKey] ??= TranslationOverride::query()
                ->where('locale', $locale)
                ->get(['group', 'translation_key', 'value'])
                ->groupBy('group')
                ->map(fn ($rows): array => $rows->mapWithKeys(fn (TranslationOverride $override): array => [$override->translation_key => $override->value])->all())
                ->all();

            foreach ($overrides[$group] ?? [] as $translationKey => $value) {
                if ($group === '*') {
                    $lines[$translationKey] = $value;
                } else {
                    Arr::set($lines, $translationKey, $value);
                }
            }
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            // Translation loading must remain available before migration and during DB outages.
        }

        return $lines;
    }
}
