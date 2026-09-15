<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBatch> */
final class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        return [
            'product_id' => ProductFactory::new()->feed()->withBatchTracking(),
            'batch_number' => 'LOT-'.$this->faker->unique()->numerify('######'),
            'production_date' => today()->subDays(10),
            'expiry_date' => today()->addMonths(6),
            'status' => 'active',
        ];
    }
}
