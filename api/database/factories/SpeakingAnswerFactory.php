<?php

namespace Database\Factories;

use App\Models\SpeakingAnswer;
use App\Models\SpeakingAttempt;
use App\Models\SpeakingQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeakingAnswer>
 */
class SpeakingAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'speaking_attempt_id' => SpeakingAttempt::factory(),
            'speaking_question_id' => SpeakingQuestion::factory(),
            'part' => 1,
            'position' => 1,
            'status' => 'pending',
        ];
    }
}
