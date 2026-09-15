<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['age_labels', 'characters', 'colours', 'genders'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('code', 50)->unique();
                $blueprint->string('name_ar');
                $blueprint->string('name_en');
                $blueprint->string('status', 20)->default('active')->index();
                $blueprint->unsignedInteger('sort_order')->default(0)->index();
                $blueprint->timestamps();
            });
        }
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('age_label_id')->nullable()->after('target_age')->constrained('age_labels')->nullOnDelete();
            $table->foreignId('character_id')->nullable()->after('character')->constrained('characters')->nullOnDelete();
            $table->foreignId('colour_id')->nullable()->after('colour')->constrained('colours')->nullOnDelete();
            $table->foreignId('gender_id')->nullable()->after('suitable_gender')->constrained('genders')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void { foreach (['age_label_id','character_id','colour_id','gender_id'] as $column) $table->dropForeign([$column]); $table->dropColumn(['age_label_id','character_id','colour_id','gender_id']); });
        foreach (['age_labels', 'characters', 'colours', 'genders'] as $table) Schema::dropIfExists($table);
    }
};
