<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $code = 'CAT-'.$this->faker->unique()->bothify('###');

        return ['code' => $code, 'name_ar' => 'فئة '.$code, 'name_en' => 'Category '.$code, 'status' => 'active', 'sort_order' => 0];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
