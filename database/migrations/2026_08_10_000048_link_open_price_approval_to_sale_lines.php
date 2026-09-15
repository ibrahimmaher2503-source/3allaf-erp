<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->foreignId('open_price_approval_record_id')
                ->nullable()
                ->after('open_price_authorized_by')
                ->constrained('approval_records')
                ->nullOnDelete();
            $table->index('open_price_approval_record_id', 'sale_lines_open_price_approval_index');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropIndex('sale_lines_open_price_approval_index');
            $table->dropConstrainedForeignId('open_price_approval_record_id');
        });
    }
};
