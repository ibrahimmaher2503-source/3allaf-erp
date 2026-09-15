<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backwards-compatible, authorization-only baseline for isolated fixtures.
 */
final class CanonicalAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        app(ProductionSeeder::class)->seedCanonicalAuthorizationAndBootstrapAdministrator();
    }

    /** @return array<string, list<string>> */
    public static function productionSafeRolePermissions(): array
    {
        return ProductionSeeder::productionSafeRolePermissions();
    }
}
