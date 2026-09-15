<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 2);
            $table->string('group', 120);
            $table->string('translation_key', 255);
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['locale', 'group', 'translation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
    }
};
