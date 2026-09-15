<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class InitialSetupStepRegistry
{
    /** @return array<string, array{label:string,description:string,route:string,permission:string,parameters:array<string,string>,destination_key:string,required:bool,enabled:bool}> */
    public static function steps(): array
    {
        return [
            'company' => self::step('Company identity', 'Use the approved bilingual identity, legal details, currency, timezone, and contact information.', 'admin.settings', 'company_settings.view', ['tab' => 'company'], 'company-identity'),
            'branches-stores' => self::step('Branches', 'Create active branches and complete their basic details.', 'admin.branches', 'branches_stores.view'),
            'warehouses' => self::step('Warehouses & Sales Outlets', 'Add active warehouses and explicit sales outlets with their real branch context. Cash drawers select their exact sales outlet.', 'admin.stores', 'branches_stores.view'),
            'cash-drawers' => self::step('Cash drawers', 'Assign active cash drawers to the stores that will receive controlled payments.', 'admin.cash-drawers', 'drawers_payments_tax_numbering_printers.view'),
            'users-scopes' => self::step('Users, roles, and scopes', 'Create the opening team, assign roles, and scope every non-administrator to approved branches or stores.', 'admin.authorization-baseline', 'users_roles_permissions.view'),
            'payment-methods' => self::step('Payment methods', 'Define the persisted payment methods staff may recognize and reconcile.', 'admin.settings', 'company_settings.view', ['tab' => 'payments'], 'payment-methods'),
            'taxes' => self::step('Taxes', 'Review the saved tax treatment before any invoice or POS tax choice is enabled.', 'admin.settings', 'company_settings.view', ['tab' => 'tax'], 'tax-settings'),
            'document-sequences' => self::step('Document sequences', 'Configure persisted prefixes, counters, scope, and reset rules without changing posted history.', 'admin.settings', 'company_settings.view', ['tab' => 'sequences'], 'document-sequences'),
            'printers' => self::step('Printer profiles', 'Review an active printer profile and its saved destination; hardware acceptance remains separate.', 'admin.settings', 'company_settings.view', ['tab' => 'printers', 'section' => 'printer-profiles'], 'printer-profiles'),
            'print-templates' => self::step('Print-template assignments', 'Review the existing template key assigned to each printer profile. Template layouts are not edited in this workspace.', 'admin.settings', 'company_settings.view', ['tab' => 'printers', 'section' => 'print-templates'], 'print-templates'),
            'categories' => self::step('Categories', 'Create the approved active category hierarchy before adding product masters.', 'catalog.categories', 'products_categories_brands.view'),
            'brands' => self::step('Brand masters', 'Brand masters are optional. Create active brands only where the catalog needs branded products.', 'catalog.brands', 'products_categories_brands.view', required: false),
            'customer-groups' => self::step('Customer groups', 'Maintain the persisted customer-group hierarchy before customer registration.', 'customers.groups.index', 'customers.view'),
            'customers' => self::step('Customers', 'Add genuine customer data only after non-empty latest consent purpose, wording, and retention policies are saved; never preload fabricated personal data.', 'customers.index', 'customers.view', required: false),
            'party-readiness' => self::step('Party readiness and policies', 'Save the separate Party workflow, privacy, service, scheduling, and invoice policy configuration before taking Party bookings. Readiness remains incomplete until the owner defines the exact mandatory Party policy subset.', 'party.readiness', 'party_bookings_invoices.view', required: false, enabled: false),
            'supplier-groups' => self::step('Supplier groups', 'Maintain the persisted supplier-group hierarchy before supplier registration.', 'catalog.suppliers', 'suppliers.view', ['section' => 'supplier-groups'], 'supplier-groups'),
            'suppliers' => self::step('Suppliers', 'Create at least one active supplier and select its financial-settlement method. Payment terms and supplier-product references remain optional.', 'catalog.suppliers', 'suppliers.view', ['section' => 'supplier-masters'], 'supplier-masters'),
            'product-options' => self::step('Product Filters', 'Configure reusable product filters, such as sizes and colours, before building products with variations.', 'catalog.product-options', 'products_categories_brands.view', required: false),
            'product-masters' => self::step('Product Cards', 'Build active sellable product cards and valid variation families from the approved catalog. Product import remains an optional action inside this workspace.', 'catalog.products', 'products_categories_brands.view'),
            'prices' => self::step('Approved selling prices', 'Configure valid reusable price lists from Product Card base prices and assign every active Sales Outlet.', 'pricing.lists', 'pricing_lists.view'),
            'opening-configuration' => self::step('Opening inventory', 'Approve a dedicated Opening Inventory document, or record the authorized audited decision to start without opening inventory. Unit cost comes from Product Cards and posting uses the inventory ledger.', 'inventory.opening.index', 'inventory_stock_card.create'),
        ];
    }

    /** @return array<string, list<string>> */
    public static function groups(): array
    {
        $groups = [
            'basics' => ['company', 'branches-stores', 'warehouses', 'cash-drawers', 'users-scopes'],
            'settings' => ['payment-methods', 'taxes', 'document-sequences', 'printers', 'print-templates'],
            'operations' => ['supplier-groups', 'suppliers', 'customer-groups', 'customers', 'party-readiness', 'product-masters', 'categories', 'brands', 'product-options', 'prices', 'opening-configuration'],
        ];

        $steps = self::steps();

        return array_map(
            static fn (array $keys): array => array_values(array_filter($keys, static fn (string $key): bool => $steps[$key]['enabled'])),
            $groups,
        );
    }

    /** @return array<string, array{label:string,description:string,route:string,permission:string,parameters:array<string,string>,destination_key:string,required:bool,enabled:bool}> */
    public static function activeSteps(): array
    {
        return array_filter(self::steps(), static fn (array $step): bool => $step['enabled']);
    }

    /** @param array<string,string> $parameters @return array{label:string,description:string,route:string,permission:string,parameters:array<string,string>,destination_key:string,required:bool,enabled:bool} */
    private static function step(string $label, string $description, string $route, string $permission, array $parameters = [], ?string $destinationKey = null, bool $required = true, bool $enabled = true): array
    {
        return compact('label', 'description', 'route', 'permission', 'parameters', 'enabled') + ['destination_key' => $destinationKey ?? '', 'required' => $required];
    }
}
