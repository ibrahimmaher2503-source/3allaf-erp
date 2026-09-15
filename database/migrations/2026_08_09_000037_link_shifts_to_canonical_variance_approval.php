<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table): void {
            // This is a reference to the one canonical Platform workflow. It
            // is intentionally not a second status or decision store.
            $table->foreignId('variance_approval_record_id')
                ->nullable()
                ->after('variance_approved_by')
                ->constrained('approval_records')
                ->nullOnDelete()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table): void {
            $table->dropUnique(['variance_approval_record_id']);
            $table->dropConstrainedForeignId('variance_approval_record_id');
        });
    }
};
