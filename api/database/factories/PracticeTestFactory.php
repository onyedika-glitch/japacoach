<?php

namespace Database\Factories;

use App\Models\PracticeTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeTest>
 */
class PracticeTestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module' => 'reading',
            'test_type' => 'academic',
            'title' => fake()->sentence(3),
            'duration_minutes' => 60,
            'is_published' => true,
        ];
    }
}
