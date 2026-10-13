<?php

namespace App\Http\Resources;

use App\Models\WritingAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WritingAttempt
 */
class WritingAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task' => new WritingTaskResource($this->whenLoaded('task')),
            'mock_exam_id' => $this->mock_exam_id,
            'essay' => $this->essay,
            'word_count' => $this->word_count,
            'time_spent_seconds' => $this->time_spent_seconds,
            'status' => $this->status,
            'overall_band' => $this->overall_band,
            'scores' => $this->scores,
            'feedback' => $this->feedback,
            'created_at' => $this->created_at?->toIso8601String(),
            'scored_at' => $this->scored_at?->toIso8601String(),
        ];
    }
}
