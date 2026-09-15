<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ui_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('appearance')->default('system');
            $table->string('accent_color')->default('teal');
            $table->string('sidebar_mode')->default('expanded');
            $table->string('navbar_mode')->default('sticky');
            $table->string('content_width')->default('wide');
            $table->string('table_density')->default('comfortable');
            $table->string('font_scale')->default('normal');
            $table->boolean('reduced_motion')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ui_preferences');
    }
};
