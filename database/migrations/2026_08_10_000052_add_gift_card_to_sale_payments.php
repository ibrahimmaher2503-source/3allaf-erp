<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retains the immutable tender-to-card source link for POS redemptions.
     * The linked Gift Card ledger retains the complementary Sale source link.
     */
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->foreignId('gift_card_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('gift_cards')
                ->restrictOnDelete();
            $table->index(['gift_card_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->dropForeign(['gift_card_id']);
            $table->dropIndex(['gift_card_id', 'sale_id']);
            $table->dropColumn('gift_card_id');
        });
    }
};
