<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('sale_price', 12, 2)->nullable()->after('average_cost');
            $table->boolean('battery_required')->default(false)->after('fractional_quantity');
            $table->string('battery_details', 255)->nullable()->after('battery_required');
        });
        foreach ([['age','age_labels'], ['character','characters'], ['colour','colours'], ['gender','genders']] as [$name, $table]) {
            Schema::create("product_{$name}s", function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $blueprint->foreignId(rtrim($table, 's').'_id')->constrained($table)->restrictOnDelete();
                $blueprint->timestamps();
                $blueprint->unique(['product_id', rtrim($table, 's').'_id']);
            });
        }
    }
    public function down(): void
    {
        foreach (['ages','characters','colours','genders'] as $name) Schema::dropIfExists("product_{$name}");
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['sale_price','battery_required','battery_details']));
    }
};
