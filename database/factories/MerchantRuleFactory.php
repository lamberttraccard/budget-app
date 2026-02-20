<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MerchantRule>
 */
class MerchantRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_pattern' => strtoupper(fake()->unique()->company()),
            'category_id' => Category::factory(),
        ];
    }
}
