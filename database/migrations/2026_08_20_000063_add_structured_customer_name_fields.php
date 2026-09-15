<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'first_name_ar' => 'VARCHAR(190) NULL',
            'last_name_ar' => 'VARCHAR(190) NULL',
            'first_name_en' => 'VARCHAR(190) NULL',
            'last_name_en' => 'VARCHAR(190) NULL',
        ] as $column => $definition) {
            if (! Schema::hasColumn('customers', $column)) {
                DB::statement("ALTER TABLE customers ADD COLUMN {$column} {$definition} AFTER name_en");
            }
        }
    }

    public function down(): void
    {
        foreach (['first_name_ar', 'last_name_ar', 'first_name_en', 'last_name_en'] as $column) {
            if (Schema::hasColumn('customers', $column)) {
                DB::statement("ALTER TABLE customers DROP COLUMN {$column}");
            }
        }
    }
};
