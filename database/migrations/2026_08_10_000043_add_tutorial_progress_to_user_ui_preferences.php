<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ui_preferences', function (Blueprint $table): void {
            $table->json('tutorial_progress')->nullable()->after('reduced_motion');
        });
    }

    public function down(): void
    {
        Schema::table('user_ui_preferences', function (Blueprint $table): void {
            $table->dropColumn('tutorial_progress');
        });
    }
};
