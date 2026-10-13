<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WritingAttempt>
 */
class WritingAttemptFactory extends Factory
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
            'writing_task_id' => WritingTask::factory(),
            'essay' => fake()->paragraphs(4, true),
            'word_count' => 260,
            'status' => 'processing',
        ];
    }
}
