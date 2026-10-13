<?php

namespace Database\Factories;

use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyPlan>
 */
class StudyPlanFactory extends Factory
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
            'plan' => ['summary' => 'Plan', 'weeks' => [], 'tips' => []],
        ];
    }
}
