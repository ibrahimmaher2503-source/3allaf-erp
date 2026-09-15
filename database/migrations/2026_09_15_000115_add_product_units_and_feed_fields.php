<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->string('dimension', 30);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'dimension']);
        });

        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 20, 6);
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_sale_unit')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
            $table->index(['product_id', 'is_base_unit']);
            $table->index(['product_id', 'is_purchase_unit']);
            $table->index(['product_id', 'is_sale_unit']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('feed_kind', 30)->nullable()->after('product_type')->index();
            $table->string('animal_type', 30)->nullable()->after('feed_kind')->index();
            $table->decimal('protein_percentage', 5, 2)->nullable()->after('animal_type');
            $table->boolean('track_batches')->default(false)->after('protein_percentage');
            $table->boolean('track_expiry')->default(false)->after('track_batches');
        });

        $now = now();
        $canonicalUnits = [
            'KG' => ['name_ar' => 'كجم', 'name_en' => 'Kilogram', 'dimension' => 'weight', 'decimal_places' => 3],
            'BAG' => ['name_ar' => 'شيكارة', 'name_en' => 'Bag', 'dimension' => 'count', 'decimal_places' => 3],
            'TON' => ['name_ar' => 'طن', 'name_en' => 'Ton', 'dimension' => 'weight', 'decimal_places' => 3],
            'PIECE' => ['name_ar' => 'قطعة', 'name_en' => 'Piece', 'dimension' => 'count', 'decimal_places' => 0],
            'LITER' => ['name_ar' => 'لتر', 'name_en' => 'Liter', 'dimension' => 'volume', 'decimal_places' => 3],
        ];

        foreach ($canonicalUnits as $code => $unit) {
            DB::table('units')->insert([
                'code' => $code,
                ...$unit,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $unitIds = DB::table('units')->pluck('id', 'code');
        $aliases = [
            '' => 'PIECE', 'piece' => 'PIECE', 'pieces' => 'PIECE', 'pc' => 'PIECE', 'pcs' => 'PIECE', 'قطعة' => 'PIECE',
            'kg' => 'KG', 'kilogram' => 'KG', 'kilograms' => 'KG', 'كجم' => 'KG', 'كيلو' => 'KG', 'كيلوجرام' => 'KG',
            'bag' => 'BAG', 'bags' => 'BAG', 'sack' => 'BAG', 'شيكارة' => 'BAG', 'شكارة' => 'BAG',
            'ton' => 'TON', 'tons' => 'TON', 'tonne' => 'TON', 'طن' => 'TON',
            'liter' => 'LITER', 'litre' => 'LITER', 'liters' => 'LITER', 'litres' => 'LITER', 'l' => 'LITER', 'لتر' => 'LITER',
        ];

        DB::table('products')->select(['id', 'unit_of_measure'])->orderBy('id')->chunkById(500, function ($products) use (&$unitIds, $aliases, $now): void {
            foreach ($products as $product) {
                $legacyName = trim((string) $product->unit_of_measure);
                $normalized = mb_strtolower($legacyName);
                $code = $aliases[$normalized] ?? 'LEGACY_'.strtoupper(substr(hash('sha256', $normalized), 0, 12));

                if (! isset($unitIds[$code])) {
                    $unitId = DB::table('units')->insertGetId([
                        'code' => $code,
                        'name_ar' => $legacyName,
                        'name_en' => $legacyName,
                        'dimension' => 'count',
                        'decimal_places' => 3,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $unitIds[$code] = $unitId;
                }

                DB::table('product_units')->insert([
                    'product_id' => $product->id,
                    'unit_id' => $unitIds[$code],
                    'conversion_factor' => '1.000000',
                    'is_base_unit' => true,
                    'is_purchase_unit' => true,
                    'is_sale_unit' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['feed_kind']);
            $table->dropIndex(['animal_type']);
            $table->dropColumn(['feed_kind', 'animal_type', 'protein_percentage', 'track_batches', 'track_expiry']);
        });

        Schema::dropIfExists('product_units');
        Schema::dropIfExists('units');
    }
};
