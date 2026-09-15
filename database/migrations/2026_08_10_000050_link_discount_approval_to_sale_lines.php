<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->foreignId('discount_approval_record_id')->nullable()->after('discount_replaced_at')->constrained('approval_records')->nullOnDelete();
            $table->index('discount_approval_record_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_approval_record_id');
        });
    }
};
