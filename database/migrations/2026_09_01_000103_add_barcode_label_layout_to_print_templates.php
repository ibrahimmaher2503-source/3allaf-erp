<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('print_templates', fn (Blueprint $table) => $table->json('layout_settings')->nullable()->after('footer_text')); }
    public function down(): void { Schema::table('print_templates', fn (Blueprint $table) => $table->dropColumn('layout_settings')); }
};
