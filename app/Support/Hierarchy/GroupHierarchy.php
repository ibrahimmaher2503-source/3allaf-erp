<?php

declare(strict_types=1);

namespace App\Support\Hierarchy;

use Illuminate\Support\Collection;

final class GroupHierarchy
{
    /** @param Collection<int, object> $groups @return Collection<int, object> */
    public static function flatten(Collection $groups, ?string $search = null): Collection
    {
        $byParent = $groups->groupBy(fn (object $group): int => (int) ($group->parent_id ?? 0));
        $matches = collect();
        $needle = mb_strtolower(trim((string) $search));
        if ($needle !== '') {
            foreach ($groups as $group) {
                if (str_contains(mb_strtolower(implode(' ', [(string) ($group->name_ar ?? ''), (string) ($group->name_en ?? ''), (string) ($group->code ?? '')])), $needle)) {
                    $matches->put((int) $group->id, true);
                    $parent = $group->parent_id ? $groups->firstWhere('id', $group->parent_id) : null;
                    while ($parent !== null) {
                        $matches->put((int) $parent->id, true);
                        $parent = $parent->parent_id ? $groups->firstWhere('id', $parent->parent_id) : null;
                    }
                }
            }
        }

        $result = collect();
        $visit = function (int $parentId, int $depth, string $path) use (&$visit, $byParent, $matches, $needle, $result): void {
            foreach (($byParent->get($parentId) ?? collect())->sortBy([['sort_order', 'asc'], ['name_ar', 'asc'], ['id', 'asc']]) as $group) {
                if ($needle !== '' && ! $matches->has((int) $group->id)) {
                    continue;
                }
                $group->setAttribute('hierarchy_depth', $depth);
                $group->setAttribute('hierarchy_path', ltrim($path.' / '.($group->name_ar ?: $group->name_en), ' /'));
                $result->push($group);
                $visit((int) $group->id, $depth + 1, (string) $group->hierarchy_path);
            }
        };
        $visit(0, 0, '');

        return $result;
    }

    /** @param Collection<int, object> $groups @return Collection<int, object> */
    public static function leaves(Collection $groups): Collection
    {
        $parentIds = $groups->pluck('parent_id')->filter()->map(fn ($id): int => (int) $id)->flip();

        return $groups->filter(fn (object $group): bool => ! $parentIds->has((int) $group->id));
    }
}
