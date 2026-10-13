<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rating' => 5,
            'comment' => fake()->sentence(12),
            'display_name' => fake()->firstName(),
            'location' => 'Lagos',
            'can_publish' => true,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'approved_at' => now()]);
    }
}
