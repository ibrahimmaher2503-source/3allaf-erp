<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Http\Request;

final class InitialSetupRouteMap
{
    /** @return array<string, list<string>> */
    public static function groups(): array
    {
        return InitialSetupStepRegistry::groups();
    }

    public function resolve(Request $request): ?string
    {
        $route = (string) $request->route()?->getName();

        return match (true) {
            $route === 'admin.settings' => match ((string) $request->query('tab', 'company')) {
                'payments' => 'payment-methods', 'tax' => 'taxes', 'sequences' => 'document-sequences',
                'printers' => $request->query('section') === 'print-templates' ? 'print-templates' : 'printers',
                default => 'company',
            },
            str_starts_with($route, 'admin.branches') => 'branches-stores',
            str_starts_with($route, 'admin.stores') => 'warehouses',
            str_starts_with($route, 'admin.cash-drawers') => 'cash-drawers',
            str_starts_with($route, 'admin.authorization') || str_starts_with($route, 'admin.users') || str_starts_with($route, 'admin.roles') || str_starts_with($route, 'admin.role-permissions') => 'users-scopes',
            str_starts_with($route, 'catalog.categories') => 'categories',
            str_starts_with($route, 'catalog.brands') => 'brands',
            str_starts_with($route, 'catalog.product-options') => 'product-options',
            str_starts_with($route, 'catalog.products') => 'product-masters',
            str_starts_with($route, 'pricing.') => 'prices',
            str_starts_with($route, 'inventory.opening') => 'opening-configuration',
            str_starts_with($route, 'customers.groups.') => 'customer-groups',
            str_starts_with($route, 'customers.') => 'customers',
            $route === 'party.readiness' => 'party-readiness',
            in_array($route, ['catalog.suppliers', 'suppliers.index'], true) && $request->query('section') === 'supplier-groups' => 'supplier-groups',
            in_array($route, ['catalog.suppliers', 'suppliers.index'], true) => 'suppliers',
            default => null,
        };
    }
}
