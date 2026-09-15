<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('type')->default('selling');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('status')->default('active');
            $table->boolean('allows_negative_stock')->default(false);
            $table->text('policy_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
