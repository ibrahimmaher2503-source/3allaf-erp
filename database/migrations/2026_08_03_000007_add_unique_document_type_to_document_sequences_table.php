<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Superseded by the scoped composite key in
        // 2026_08_19_000002_add_cf13_cf14_contracts.php. Keeping this
        // historical migration as a no-op lets fresh databases build the
        // correct scope-aware uniqueness contract without a transient
        // document-type-only constraint.
    }

    public function down(): void
    {
        // No schema change is performed by this compatibility migration.
    }
};
