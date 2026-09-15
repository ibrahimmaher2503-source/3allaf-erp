<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['store_id', 'posted_at', 'id'], 'sm_store_posted_id_idx');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['model_number', 'status'], 'prod_model_status_idx');
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notif_owner_read_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notif_owner_read_idx'));
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('prod_model_status_idx');
        });
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('sm_store_posted_id_idx'));
    }
};
