<?php

namespace App\Http\Resources;

use App\Models\SpeakingAnswer;
use App\Models\SpeakingAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpeakingAttempt
 */
class SpeakingAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'status' => $this->status,
            'mock_exam_id' => $this->mock_exam_id,
            'overall_band' => $this->overall_band,
            'scores' => $this->scores,
            'feedback' => $this->feedback,
            'created_at' => $this->created_at?->toIso8601String(),
            'scored_at' => $this->scored_at?->toIso8601String(),
            'questions' => $this->whenLoaded('answers', fn () => $this->answers->map(fn (SpeakingAnswer $answer): array => [
                'id' => $answer->question->id,
                'part' => $answer->part,
                'position' => $answer->position,
                'prompt' => $answer->question->prompt,
                'cue_points' => $answer->question->cue_points,
                'audio_url' => $answer->question->audioUrl(),
                'answer' => $answer->status === 'pending' ? null : [
                    'id' => $answer->id,
                    'status' => $answer->status,
                    'duration_seconds' => $answer->duration_seconds,
                    'transcript' => $answer->transcript,
                    'audio_url' => $answer->audio_path ? route('speaking.answers.audio', $answer, false) : null,
                ],
            ])->values()),
        ];
    }
}
