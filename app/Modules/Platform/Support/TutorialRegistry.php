<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use LogicException;

/**
 * Loads one self-contained definition per registered screen.
 *
 * Add a new guide by creating one PHP file under Platform/Tutorials and
 * returning the documented guide array. Keep selectors stable and localized
 * copy in the definition; this registry owns discovery, validation, and lookup.
 */
final class TutorialRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $guides = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$guides !== null) {
            return self::$guides;
        }

        $guides = [];
        $paths = glob(app_path('Modules/Platform/Tutorials/*.php')) ?: [];
        sort($paths);

        foreach ($paths as $path) {
            $screenId = pathinfo($path, PATHINFO_FILENAME);
            $guide = require $path;

            if (! is_array($guide)) {
                throw new LogicException("Tutorial definition [{$screenId}] must return an array.");
            }

            $guide['screen_id'] = $guide['screen_id'] ?? $screenId;
            self::assertDefinition($screenId, $guide);
            $guides[$screenId] = $guide;
        }

        return self::$guides = $guides;
    }

    /** @return array<string, mixed>|null */
    public static function forRoute(?string $routeName): ?array
    {
        if (! $routeName) {
            return null;
        }

        foreach (self::all() as $guide) {
            if (in_array($routeName, $guide['route_names'], true)) {
                return $guide;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $screenId): ?array
    {
        return self::all()[$screenId] ?? null;
    }

    /** @return list<string> */
    public static function screenIds(): array
    {
        return array_keys(self::all());
    }

    /** @param array<string, mixed> $guide */
    private static function assertDefinition(string $screenId, array $guide): void
    {
        $required = [
            'screen_id',
            'route_names',
            'title',
            'purpose',
            'when_to_use',
            'permissions',
            'approved_actions',
            'stories',
            'flows',
            'acceptance_criteria',
            'sections',
            'tour_steps',
            'version',
            'updated_at',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $guide)) {
                throw new LogicException("Tutorial definition [{$screenId}] is missing [{$key}].");
            }
        }

        if ($guide['screen_id'] !== $screenId || ! is_array($guide['route_names']) || $guide['route_names'] === []) {
            throw new LogicException("Tutorial definition [{$screenId}] has an invalid identity or route list.");
        }
    }
}
