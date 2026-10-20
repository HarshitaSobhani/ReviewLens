<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['Headphones', 'Backpacks', 'Kettles']),
            'brand' => fake()->company(),
            'price' => fake()->randomFloat(2, 5, 500),
            'description' => fake()->sentence(),
        ];
    }
}
