<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('status')->default('active'); // active, inactive, maintenance
            $table->text('policy_notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            // Keep the identifier below MySQL's 64-character limit while
            // preserving the full scope/status index definition.
            $table->index(
                ['company_id', 'branch_id', 'store_id', 'assigned_user_id', 'status'],
                'cash_drawers_scope_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawers');
    }
};
