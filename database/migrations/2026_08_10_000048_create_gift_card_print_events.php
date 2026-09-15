<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_print_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->restrictOnDelete();
            $table->foreignId('printed_by')->constrained('users')->restrictOnDelete();
            $table->string('format', 30)->default('thermal');
            $table->boolean('is_reprint')->default(false);
            $table->text('reason')->nullable();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamp('printed_at');
            $table->timestamps();
            $table->index(['gift_card_id', 'printed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_print_events');
    }
};
