<?php

namespace Database\Factories;

use App\Models\SpeakingQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeakingQuestion>
 */
class SpeakingQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'part' => 1,
            'topic' => fake()->unique()->words(2, true),
            'prompt' => rtrim(fake()->sentence(), '.').'?',
            'cue_points' => null,
            'is_published' => true,
        ];
    }
}
