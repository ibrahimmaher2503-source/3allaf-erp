<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->default('TBD')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('commercial_registration')->nullable();
            $table->string('currency_code')->default('TBD')->nullable();
            $table->string('currency_symbol')->default('TBD')->nullable();
            $table->string('timezone')->default('UTC')->nullable();
            $table->string('locale_default')->default('ar')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->text('policy_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
