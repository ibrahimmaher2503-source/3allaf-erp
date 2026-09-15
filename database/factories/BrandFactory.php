<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $code = 'BRD-'.$this->faker->unique()->bothify('###');

        return ['code' => $code, 'name_ar' => 'علامة '.$code, 'name_en' => 'Brand '.$code, 'status' => 'active'];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
