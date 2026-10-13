<?php

namespace App\Http\Resources;

use App\Models\WritingTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WritingTask
 */
class WritingTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'test_type' => $this->test_type,
            'title' => $this->title,
            'prompt' => $this->prompt,
            'visual' => $this->visual,
            'min_words' => $this->minWords(),
            'recommended_minutes' => $this->recommendedMinutes(),
        ];
    }
}
