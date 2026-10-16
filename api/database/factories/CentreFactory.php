<?php

namespace Database\Factories;

use App\Models\Centre;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Centre>
 */
class CentreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' IELTS',
            'owner_id' => User::factory(),
        ];
    }
}
