<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->integer('padding_length')->default(6);
            $table->bigInteger('next_value')->default(1);
            $table->string('reset_rule')->default('never')->nullable();
            $table->string('status')->default('active');
            $table->integer('lock_version')->default(1);
            $table->text('policy_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
