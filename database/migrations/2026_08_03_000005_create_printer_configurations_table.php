<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('printer_type')->default('thermal');
            $table->string('paper_size')->default('80mm');
            $table->string('template_name')->default('default_thermal');
            $table->string('connection_type')->default('network');
            $table->string('ip_address')->nullable();
            $table->integer('port')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_configurations');
    }
};
