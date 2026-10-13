<?php

namespace Database\Factories;

use App\Models\PracticeSection;
use App\Models\PracticeTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeSection>
 */
class PracticeSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practice_test_id' => PracticeTest::factory(),
            'position' => 1,
            'title' => fake()->sentence(3),
            'body' => fake()->paragraphs(3, true),
        ];
    }
}
