<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_jobs', function (Blueprint $table): void {
            $table->char('snapshot_hash', 64)->nullable()->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $table): void {
            $table->dropColumn('snapshot_hash');
        });
    }
};
