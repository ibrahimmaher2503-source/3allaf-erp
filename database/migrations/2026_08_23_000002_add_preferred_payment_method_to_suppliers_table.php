<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('preferred_payment_method_id')
                ->nullable()
                ->index()
                ->constrained('payment_methods')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropForeign(['preferred_payment_method_id']);
            $table->dropIndex(['preferred_payment_method_id']);
            $table->dropColumn('preferred_payment_method_id');
        });
    }
};
