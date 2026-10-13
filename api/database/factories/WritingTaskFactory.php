<?php

namespace Database\Factories;

use App\Models\WritingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WritingTask>
 */
class WritingTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_number' => 2,
            'test_type' => 'both',
            'title' => fake()->sentence(4),
            'prompt' => fake()->paragraph().' Write at least 250 words.',
            'visual' => null,
            'is_published' => true,
        ];
    }
}
