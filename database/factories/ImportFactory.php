<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Import>
 */
class ImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'file_name' => fake()->randomElement(['export', 'releve', 'transactions']).'_'.fake()->date('Y_m').'.csv',
            'bank' => fake()->randomElement(['Banque Populaire', 'Revolut']),
            'status' => fake()->randomElement(['pending', 'completed']),
            'transaction_count' => fake()->numberBetween(0, 200),
            'imported_at' => fake()->optional(0.8)->dateTimeBetween('first day of January this year', 'now'),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'imported_at' => fake()->dateTimeBetween('first day of January this year', 'now'),
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'imported_at' => null,
        ]);
    }
}
