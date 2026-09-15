<?php

namespace App\Modules\Platform\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

final class TranslationCatalog
{
    /** @return array<string, array{group: string, key: string, ar: string, en: string}> */
    public function all(): array
    {
        $catalog = [];
        $jsonKeys = array_fill_keys(array_unique(array_merge(
            array_keys($this->json('ar')),
            array_keys($this->json('en')),
        )), true);

        foreach (['ar', 'en'] as $locale) {
            foreach ($this->lines($locale) as $group => $lines) {
                foreach (Arr::dot($lines) as $key => $value) {
                    if (! is_string($value) || ($group !== '*' && isset($jsonKeys[$group.'.'.$key]))) {
                        continue;
                    }

                    $id = $group.'|'.$key;
                    $catalog[$id] ??= ['group' => $group, 'key' => $key, 'ar' => null, 'en' => null];
                    $catalog[$id][$locale] = $value;
                }
            }
        }

        foreach ($catalog as &$entry) {
            $fallbackKey = $entry['group'] === '*' ? $entry['key'] : $entry['group'].'.'.$entry['key'];
            $entry['ar'] = $entry['ar'] ?? $entry['en'] ?? $fallbackKey;
            $entry['en'] = $entry['en'] ?? $fallbackKey;
        }

        return $catalog;
    }

    /** @return array{group: string, key: string, ar: string, en: string}|null */
    public function find(string $group, string $key): ?array
    {
        return $this->all()[$group.'|'.$key] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    private function lines(string $locale): array
    {
        $lines = ['*' => $this->json($locale)];

        foreach (File::files(lang_path($locale)) as $file) {
            if ($file->getExtension() === 'php') {
                $lines[$file->getFilenameWithoutExtension()] = require $file->getPathname();
            }
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    private function json(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return File::exists($path) ? (json_decode(File::get($path), true) ?: []) : [];
    }
}
