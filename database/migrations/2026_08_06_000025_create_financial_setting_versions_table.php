<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_setting_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('value_type', 30);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('approval_record_id')->nullable();
            $table->unsignedInteger('version');
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['key', 'effective_from']);
            $table->index(['key', 'effective_to']);
            $table->index('approval_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_setting_versions');
    }
};
