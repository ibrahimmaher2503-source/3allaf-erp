<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $code = 'SKU-'.$this->faker->unique()->bothify('#####');

        return [
            'item_code' => $code,
            'category_id' => Category::factory(),
            'name_ar' => 'منتج '.$code,
            'name_en' => 'Product '.$code,
            'model_number' => 'MODEL-'.$this->faker->unique()->bothify('#####'),
            'product_type' => 'standard',
            'unit_of_measure' => 'piece',
            'status' => 'active',
            'barcode_mode' => 'single',
            'average_cost' => '10.00',
            'sale_price' => '12.00',
            'reorder_threshold' => '1.000',
            'fractional_quantity' => false,
            'lock_version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if ($product->product_type !== 'service' && ! $product->barcodes()->exists()) {
                Barcode::query()->create([
                    'product_id' => $product->id,
                    'barcode' => $this->faker->unique()->ean13(),
                    'source' => 'generated',
                    'status' => 'active',
                    'is_primary' => true,
                ]);
            }
        });
    }

    public function fractional(): static
    {
        return $this->state(['fractional_quantity' => true, 'unit_of_measure' => 'kg']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function feed(): static
    {
        return $this->state(['feed_kind' => 'feed', 'unit_of_measure' => 'kg', 'fractional_quantity' => true]);
    }

    public function rawMaterial(): static
    {
        return $this->state(['feed_kind' => 'raw_material', 'unit_of_measure' => 'kg', 'fractional_quantity' => true]);
    }

    public function additive(): static
    {
        return $this->state(['feed_kind' => 'additive', 'unit_of_measure' => 'kg', 'fractional_quantity' => true]);
    }

    public function withBatchTracking(): static
    {
        return $this->state(['track_batches' => true, 'track_expiry' => true]);
    }
}
