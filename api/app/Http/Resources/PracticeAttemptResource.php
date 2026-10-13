<?php

namespace App\Http\Resources;

use App\Models\PracticeAttempt;
use App\Models\PracticeQuestion;
use App\Models\PracticeSection;
use App\Services\Scoring\PracticeMarker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Answer keys, explanations and listening transcripts are only included after submission.
 *
 * @mixin PracticeAttempt
 */
class PracticeAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $submitted = $this->isSubmitted();
        $answers = $this->answers ?? [];

        return [
            'id' => $this->id,
            'module' => $this->module,
            'status' => $this->status,
            'mock_exam_id' => $this->mock_exam_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'answers' => (object) $answers,
            'correct_count' => $this->correct_count,
            'total_count' => $this->total_count,
            'band' => $this->band,
            'test' => [
                'id' => $this->test->id,
                'title' => $this->test->title,
                'module' => $this->test->module,
                'test_type' => $this->test->test_type,
                'duration_minutes' => $this->test->duration_minutes,
                'sections' => $this->test->sections->map(fn (PracticeSection $section): array => [
                    'id' => $section->id,
                    'position' => $section->position,
                    'title' => $section->title,
                    'instructions' => $section->instructions,
                    'body' => $section->body,
                    'audio_url' => $section->audioUrl(),
                    'transcript' => $submitted ? $section->script : null,
                    'questions' => $section->questions->map(fn (PracticeQuestion $question): array => [
                        'id' => $question->id,
                        'number' => $question->number,
                        'type' => $question->type,
                        'prompt' => $question->prompt,
                        'options' => $question->displayOptions(),
                    ])->values(),
                ])->values(),
            ],
            'review' => $submitted ? $this->review($answers) : null,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return list<array<string, mixed>>
     */
    private function review(array $answers): array
    {
        $marker = app(PracticeMarker::class);
        $review = [];

        foreach ($this->test->sections as $section) {
            foreach ($section->questions as $question) {
                $given = $answers[$question->id] ?? null;
                $review[] = [
                    'question_id' => $question->id,
                    'number' => $question->number,
                    'your_answer' => $given,
                    'correct_answers' => $question->answers,
                    'is_correct' => $marker->isCorrect($question, $given),
                    'explanation' => $question->explanation,
                ];
            }
        }

        return $review;
    }
}
