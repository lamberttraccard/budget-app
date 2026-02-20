<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Personal Checking', 'Main Account', 'Professional Account', 'Savings']),
            'bank' => fake()->randomElement(['Banque Populaire', 'Revolut', 'BNP Paribas', 'Crédit Agricole']),
            'type' => fake()->randomElement(['personal', 'professional']),
        ];
    }

    public function personal(): static
    {
        return $this->state(['type' => 'personal']);
    }

    public function professional(): static
    {
        return $this->state(['type' => 'professional']);
    }
}
