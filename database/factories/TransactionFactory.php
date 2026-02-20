<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount_cents' => fake()->randomElement([-1, 1]) * fake()->numberBetween(100, 100000),
            'date' => fake()->dateTimeBetween('first day of January this year', 'now')->format('Y-m-d'),
            'description' => fake()->company() . ' ' . strtoupper(fake()->lexify('????')),
            'dedup_hash' => hash('sha256', fake()->unique()->uuid()),
            'category_id' => Category::factory(),
            'account_id' => Account::factory(),
            'import_id' => null,
        ];
    }

    public function expense(): static
    {
        return $this->state(['amount_cents' => -1 * fake()->numberBetween(100, 100000)]);
    }

    public function income(): static
    {
        return $this->state(['amount_cents' => fake()->numberBetween(100, 100000)]);
    }
}
