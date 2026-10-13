<?php

namespace Database\Factories;

use App\Models\PracticeQuestion;
use App\Models\PracticeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeQuestion>
 */
class PracticeQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practice_section_id' => PracticeSection::factory(),
            'number' => 1,
            'type' => 'tfng',
            'prompt' => fake()->sentence(),
            'options' => null,
            'answers' => ['TRUE'],
            'explanation' => fake()->sentence(),
        ];
    }
}
