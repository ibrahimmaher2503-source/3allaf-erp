<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_children') && Schema::hasColumn('customer_children', 'name_en')) {
            DB::statement('ALTER TABLE customer_children MODIFY name_en VARCHAR(190) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_children') && Schema::hasColumn('customer_children', 'name_en')) {
            DB::statement("UPDATE customer_children SET name_en = '' WHERE name_en IS NULL");
            DB::statement("ALTER TABLE customer_children MODIFY name_en VARCHAR(190) NOT NULL DEFAULT ''");
        }
    }
};
