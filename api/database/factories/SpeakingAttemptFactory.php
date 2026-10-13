<?php

namespace Database\Factories;

use App\Models\SpeakingAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeakingAttempt>
 */
class SpeakingAttemptFactory extends Factory
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
            'mode' => 'full',
            'status' => 'in_progress',
        ];
    }
}
