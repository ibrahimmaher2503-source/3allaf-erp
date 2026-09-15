<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('step_key', 80);
            $table->string('decision', 20);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['company_id', 'step_key', 'id']);
            $table->index(['company_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_decisions');
    }
};
