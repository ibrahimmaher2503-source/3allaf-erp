<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class ApplicationNavigation
{
    private const ACTIVE_ROUTES = [
        'sales.invoices' => ['sales.invoices', 'sales.show', 'sales.print', 'sales.receipt.*'],
        'customers.index' => ['customers.index', 'customers.create', 'customers.show'],
        'customers.groups.index' => ['customers.groups.*'],
        'pos.shift' => ['pos.shift*'],
        'purchasing.orders' => ['purchasing.orders*'],
        'purchasing.invoices' => ['purchasing.invoices*', 'purchasing.receiv*'],
        'purchasing.returns' => ['purchasing.returns*'],
        'catalog.suppliers' => ['catalog.suppliers*', 'suppliers.*'],
        'catalog.products' => ['catalog.products*', 'catalog.product.*'],
        'catalog.categories' => ['catalog.categories*'],
        'catalog.brands' => ['catalog.brands*'],
        'catalog.product-options' => ['catalog.product-options*'],
        'inventory.balances' => ['inventory.balances*', 'inventory.movements*', 'inventory.stock-card*'],
        'inventory.transfers' => ['inventory.transfers*'],
        'inventory.counts' => ['inventory.counts*', 'inventory.adjustments*'],
        'pricing.labels' => ['pricing.labels*', 'barcodes.*'],
        'pricing.index' => ['pricing.index', 'pricing.approvals', 'pricing.focus', 'pricing.lists'],
        'parties.bookings.index' => ['parties.bookings*'],
        'parties.calendar' => ['parties.calendar*'],
        'parties.invoices.index' => ['parties.invoices*'],
        'parties.orders.index' => ['parties.orders*'],
        'reports.index' => ['reports.*', 'exports.*'],
        'feed-store.operations' => ['feed-store.*'],
        'admin.branches' => ['admin.branches*'],
        'admin.stores' => ['admin.stores*'],
        'admin.authorization-baseline' => ['admin.authorization-baseline*', 'admin.users*'],
        'admin.roles' => ['admin.roles*'],
        'initial-setup' => ['initial-setup*'],
        'admin.settings' => ['admin.settings*', 'admin.printers*'],
        'admin.audit' => ['admin.audit*'],
        'admin.approvals' => ['admin.approvals*'],
    ];
    /** @return array<int, array<string, mixed>> */
    public function for(User $user, string $locale): array
    {
        $configuredGroups = config('navigation', []);

        if (! is_array($configuredGroups)) {
            return [];
        }

        $groups = collect($configuredGroups)
            ->filter(static fn (mixed $group): bool => is_array($group))
            ->filter(static fn (array $group): bool => (bool) ($group['enabled'] ?? true))
            ->map(function (array $group) use ($user, $locale): array {
                $group['label'] = $this->localizedLabel($group['label'] ?? null, $locale);
                $items = is_array($group['items'] ?? null) ? $group['items'] : [];
                $group['items'] = collect($items)
                    ->filter(static fn (mixed $item): bool => is_array($item))
                    ->filter(function (array $item) use ($user): bool {
                        $type = $this->entryType($item, 'item');

                        if (in_array($type, ['heading', 'separator'], true)) {
                            return true;
                        }

                        $route = $item['route'] ?? null;
                        $permission = $item['permission'] ?? null;

                        return is_string($route)
                            && $route !== ''
                            && is_string($permission)
                            && $permission !== ''
                            && Route::has($route)
                            && $user->can($permission);
                    })
                    ->map(function (array $item) use ($locale, $user): array {
                        $type = $this->entryType($item, 'item');
                        $item['label'] = $this->localizedLabel($item['label'] ?? null, $locale);

                        if (in_array($type, ['heading', 'separator'], true)) {
                            return $item;
                        }

                        $route = (string) ($item['route'] ?? '');
                        $parameters = is_array($item['parameters'] ?? null) ? $item['parameters'] : [];
                        $item['url'] = route($route, $parameters);
                        $activeRoutes = $item['active_routes'] ?? self::ACTIVE_ROUTES[$route] ?? [$route];
                        $activeRoutes = is_array($activeRoutes) ? array_values(array_filter($activeRoutes, 'is_string')) : [$route];
                        $item['active'] = $activeRoutes !== []
                            && request()->routeIs(...$activeRoutes)
                            && collect($parameters)->every(function (mixed $value, string $key): bool {
                                $actual = request($key, request()->route($key));

                                return ($key === 'section' && $value === 'supplier-masters' && blank($actual))
                                    || (string) $actual === (string) $value;
                            });

                        if ($route === 'initial-setup') {
                            $item = $this->withSetupSteps($item, $user);
                        }

                        return $item;
                    })->values()->all();

                $group['active'] = collect($group['items'])->contains(
                    fn (array $item): bool => (bool) ($item['active'] ?? false) || (bool) ($item['children_active'] ?? false)
                );

                return $group;
            })
            ->filter(fn (array $group): bool => in_array($this->entryType($group, 'group'), ['heading', 'separator'], true)
                || ($group['items'] ?? []) !== [])
            ->values()->all();

        return $this->deduplicateDestinations($this->normalizeForRendering($groups, $locale));
    }

    /**
     * Normalize every renderer-facing field, including optional keys, before Blade sees it.
     *
     * @param  iterable<mixed>  $groups
     * @return list<array<string, mixed>>
     */
    public function normalizeForRendering(iterable $groups, string $locale): array
    {
        return collect($groups)
            ->filter(static fn (mixed $group): bool => is_array($group))
            ->values()
            ->map(fn (array $group, int $index): array => $this->normalizeGroup($group, $locale, $index, 'root'))
            ->all();
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function withSetupSteps(array $item, User $user): array
    {
        $steps = collect(InitialSetupStepRegistry::activeSteps());
        $permissions = $user->is_super_admin ? null : app(RequestPermissionLookup::class)->for($user);
        $labels = ['basics' => __('Essentials'), 'settings' => __('Settings'), 'operations' => __('Operational Readiness')];
        $icons = ['basics' => 'building-office', 'settings' => 'cog-6-tooth', 'operations' => 'check-circle'];

        $item['label'] = __('Basic Data');
        $item['subgroups'] = collect(InitialSetupStepRegistry::groups())->map(function (array $keys, string $key) use ($steps, $permissions, $labels, $icons): array {
            $children = collect($keys)->map(function (string $stepKey) use ($steps, $permissions): ?array {
                $step = $steps->get($stepKey);
                if ($step === null || ! Route::has($step['route']) || ($permissions !== null && ! isset($permissions[$step['permission']]))) {
                    return null;
                }

                $url = route($step['route'], $step['parameters'] + [
                    'setup' => 'true',
                    'setup_step' => $stepKey,
                ]);

                $canonicalRoutes = match ($stepKey) {
                    'categories' => ['catalog.categories*', 'catalog.brands*', 'catalog.product-options*'],
                    'customers' => ['customers.index', 'customers.create', 'customers.show', 'customers.import*'],
                    default => [$step['route']],
                };
                $isCanonicalDestination = in_array($stepKey, ['categories', 'customers'], true)
                    && request()->routeIs(...$canonicalRoutes);

                return [
                    'key' => $stepKey,
                    'label' => __($step['label']),
                    'route' => $step['route'],
                    'parameters' => $step['parameters'],
                    'url' => $url,
                    'active' => $isCanonicalDestination || (request()->routeIs($step['route'])
                        && request()->boolean('setup')
                        && request()->query('setup_step') === $stepKey),
                ];
            })->filter()->values()->all();

            return [
                'key' => $key,
                'label' => $labels[$key],
                'icon' => $icons[$key],
                'items' => $children,
                'active' => collect($children)->contains('active', true),
            ];
        })->filter(fn (array $group): bool => $group['items'] !== [])->values()->all();
        $item['children_active'] = collect($item['subgroups'])->contains('active', true);
        $item['active'] = request()->routeIs('initial-setup');

        return $item;
    }

    /** @param array<string, mixed> $group @return array<string, mixed> */
    private function normalizeGroup(array $group, string $locale, int $index, string $parentKey): array
    {
        $type = $this->entryType($group, 'group');
        $label = $this->localizedLabel($group['label'] ?? null, $locale);
        $key = $this->navigationKey($group, $type, $label, $parentKey, $index);
        $rawItems = is_array($group['items'] ?? null) ? $group['items'] : [];
        $items = collect($rawItems)
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(fn (array $item, int $itemIndex): array => $this->normalizeItem($item, $locale, $itemIndex, $key))
            ->all();
        $active = (bool) ($group['active'] ?? false)
            || collect($items)->contains(fn (array $item): bool => $item['active'] || $item['children_active']);
        $destination = collect($items)->first(
            fn (array $item): bool => $item['active'] && ! in_array($item['type'], ['heading', 'separator'], true) && $item['url'] !== '#'
        ) ?? collect($items)->first(
            fn (array $item): bool => ! in_array($item['type'], ['heading', 'separator'], true) && $item['url'] !== '#'
        );

        return array_replace($group, [
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'icon' => $this->scalarString($group['icon'] ?? null, 'squares-2x2'),
            'items' => $items,
            'active' => $active,
            'url' => is_array($destination) ? $destination['url'] : '#',
        ]);
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function normalizeItem(array $item, string $locale, int $index, string $parentKey): array
    {
        $type = $this->entryType($item, 'item');
        $label = $this->localizedLabel($item['label'] ?? null, $locale);
        $key = $this->navigationKey($item, $type, $label, $parentKey, $index);
        $rawSubgroups = $item['subgroups'] ?? ($type === 'group' ? ($item['items'] ?? []) : []);
        $rawSubgroups = is_array($rawSubgroups) ? $rawSubgroups : [];
        $subgroups = collect($rawSubgroups)
            ->filter(static fn (mixed $group): bool => is_array($group))
            ->values()
            ->map(fn (array $group, int $groupIndex): array => $this->normalizeGroup($group, $locale, $groupIndex, $key))
            ->all();
        $active = (bool) ($item['active'] ?? false);
        $childrenActive = (bool) ($item['children_active'] ?? false)
            || collect($subgroups)->contains(fn (array $group): bool => $group['active']);

        return array_replace($item, [
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'icon' => $this->scalarString($item['icon'] ?? null, 'document-text'),
            'url' => $this->scalarString($item['url'] ?? null, '#'),
            'active' => $active,
            'children_active' => $childrenActive,
            'subgroups' => $subgroups,
        ]);
    }

    /** @param array<string, mixed> $entry */
    private function navigationKey(array $entry, string $type, string $label, string $parentKey, int $index): string
    {
        $explicit = $this->scalarString($entry['key'] ?? null);

        if ($explicit !== '') {
            return $explicit;
        }

        $stableData = [
            'parent' => $parentKey,
            'type' => $type,
            'route' => $this->scalarString($entry['route'] ?? null),
            'group' => $this->scalarString($entry['group'] ?? null),
            'label' => $label,
            'url' => $this->scalarString($entry['url'] ?? null),
            'index' => $index,
        ];
        $encoded = json_encode($stableData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return 'auto-'.substr(hash('sha256', $encoded), 0, 20).'-'.$index;
    }

    /** @param array<string, mixed> $entry */
    private function entryType(array $entry, string $default): string
    {
        if (($entry['separator'] ?? false) === true) {
            return 'separator';
        }

        if (($entry['heading'] ?? false) === true) {
            return 'heading';
        }

        $type = strtolower($this->scalarString($entry['type'] ?? null, $default));

        return in_array($type, ['group', 'item', 'heading', 'separator'], true) ? $type : $default;
    }

    private function localizedLabel(mixed $label, string $locale): string
    {
        if (is_array($label)) {
            $candidates = [$label[str_starts_with($locale, 'ar') ? 'ar' : 'en'] ?? null, $label['en'] ?? null, $label['ar'] ?? null, ...array_values($label)];

            foreach ($candidates as $candidate) {
                $value = $this->scalarString($candidate);

                if ($value !== '') {
                    return $value;
                }
            }
        } else {
            $value = $this->scalarString($label);

            if ($value !== '') {
                return $value;
            }
        }

        return str_starts_with($locale, 'ar') ? 'التنقل' : 'Navigation';
    }

    private function scalarString(mixed $value, string $default = ''): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            $value = trim((string) $value);

            return $value !== '' ? $value : $default;
        }

        return $default;
    }

    /** @param list<array<string,mixed>> $groups @return list<array<string,mixed>> */
    private function deduplicateDestinations(array $groups): array
    {
        $seen = [];

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                if (($item['subgroups'] ?? []) !== []) {
                    foreach ($item['subgroups'] as &$subcategory) {
                        $subcategory['items'] = array_values(array_filter(
                            $subcategory['items'],
                            function (array $child) use (&$seen): bool {
                                $identity = $this->destinationIdentity($child);
                                if ($identity === '' || ! isset($seen[$identity])) {
                                    if ($identity !== '') $seen[$identity] = true;
                                    return true;
                                }

                                return false;
                            },
                        ));
                    }
                    unset($subcategory);
                    $item['subgroups'] = array_values(array_filter($item['subgroups'], fn (array $subcategory): bool => ($subcategory['items'] ?? []) !== []));
                    foreach ($item['subgroups'] as &$subcategory) {
                        $subcategory['active'] = collect($subcategory['items'])->contains('active', true);
                    }
                    unset($subcategory);
                    $item['children_active'] = collect($item['subgroups'])->contains('active', true);
                    continue;
                }

                $identity = $this->destinationIdentity($item);
                if ($identity !== '') $seen[$identity] = true;
            }
            unset($item);
            $group['active'] = collect($group['items'])->contains(
                fn (array $item): bool => (bool) ($item['active'] ?? false) || (bool) ($item['children_active'] ?? false)
            );
        }
        unset($group);

        return $groups;
    }

    /** @param array<string,mixed> $entry */
    private function destinationIdentity(array $entry): string
    {
        if (in_array($entry['type'] ?? '', ['heading', 'separator'], true)) return '';

        $url = $this->scalarString($entry['url'] ?? null);
        if ($url === '' || $url === '#') return '';
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        unset($query['setup'], $query['setup_step']);
        if (($query['section'] ?? null) === 'supplier-masters') unset($query['section']);
        ksort($query);

        return ($parts['path'] ?? $url).($query === [] ? '' : '?'.http_build_query($query));
    }
}
