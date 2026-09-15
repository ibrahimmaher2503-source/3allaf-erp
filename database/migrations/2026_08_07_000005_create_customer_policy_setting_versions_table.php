<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_policy_setting_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->string('value_type', 20)->default('text');
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_policy_setting_versions');
    }
};
