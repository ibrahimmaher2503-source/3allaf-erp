<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawers', function (Blueprint $table): void {
            $table->foreignId('treasury_cash_account_id')->nullable()->after('assigned_user_id')
                ->constrained('cash_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('treasury_cash_account_id');
        });
    }
};
