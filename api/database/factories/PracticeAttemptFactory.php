<?php

namespace Database\Factories;

use App\Models\PracticeAttempt;
use App\Models\PracticeTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeAttempt>
 */
class PracticeAttemptFactory extends Factory
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
            'practice_test_id' => PracticeTest::factory(),
            'module' => 'reading',
            'status' => 'in_progress',
            'answers' => [],
            'started_at' => now(),
        ];
    }
}
