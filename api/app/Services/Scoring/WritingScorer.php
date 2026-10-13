<?php

namespace App\Services\Scoring;

use App\Models\WritingAttempt;
use App\Services\Ai\DeepSeekClient;
use App\Support\Text;

/**
 * Scores a Task 1 or Task 2 response with the AI examiner.
 */
class WritingScorer
{
    public function __construct(private DeepSeekClient $ai) {}

    /**
     * @return list<string>
     */
    public static function criteriaFor(int $taskNumber): array
    {
        return [
            $taskNumber === 1 ? 'task_achievement' : 'task_response',
            'coherence_cohesion',
            'lexical_resource',
            'grammatical_range_accuracy',
        ];
    }

    public function score(WritingAttempt $attempt): WritingAttempt
    {
        $task = $attempt->task;
        $criteria = self::criteriaFor($task->task_number);

        $result = Text::withoutDashesDeep($this->ai->json($this->systemPrompt($task->task_number, $task->test_type), $this->userPrompt($attempt), 0.2, 6000));

        $scores = [];
        foreach ($criteria as $criterion) {
            $scores[$criterion] = [
                'band' => BandCalculator::normalize($result['scores'][$criterion]['band'] ?? 0),
                'comment' => (string) ($result['scores'][$criterion]['comment'] ?? ''),
            ];
        }

        $overall = BandCalculator::fromCriteria(...array_column($scores, 'band'));

        // IELTS penalises under-length responses; cap the band so the AI cannot ignore it.
        if ($attempt->word_count < $task->minWords() * 0.6) {
            $overall = min($overall, 4.5);
        }

        $attempt->forceFill([
            'status' => 'scored',
            'scores' => $scores,
            'overall_band' => $overall,
            'feedback' => [
                'summary' => (string) ($result['summary'] ?? ''),
                'strengths' => array_values(array_filter((array) ($result['strengths'] ?? []), 'is_string')),
                'improvements' => array_values(array_filter((array) ($result['improvements'] ?? []), 'is_string')),
                'corrections' => array_values(array_filter(array_map(
                    fn (mixed $row): ?array => is_array($row) && filled($row['original'] ?? null) ? [
                        'original' => (string) $row['original'],
                        'corrected' => (string) ($row['corrected'] ?? ''),
                        'explanation' => (string) ($row['explanation'] ?? ''),
                    ] : null,
                    (array) ($result['corrections'] ?? []),
                ))),
                'model_answer' => (string) ($result['model_answer'] ?? ''),
            ],
            'scored_at' => now(),
        ])->save();

        return $attempt;
    }

    private function systemPrompt(int $taskNumber, string $testType): string
    {
        $descriptors = BandDescriptors::WRITING;
        $noDashes = Text::NO_DASHES;
        $taskName = $taskNumber === 1
            ? ($testType === 'general' ? 'General Training Task 1 (letter, minimum 150 words)' : 'Academic Task 1 (describe visual information, minimum 150 words)')
            : 'Task 2 (essay, minimum 250 words)';
        $firstCriterion = $taskNumber === 1 ? 'task_achievement' : 'task_response';

        return <<<PROMPT
You are a strict but encouraging certified IELTS Writing examiner marking {$taskName}.
Mark against these band descriptors and be realistic: do not inflate scores. Responses under the minimum word count lose marks for task achievement/response.
{$noDashes}

{$descriptors}

Reply with JSON only, in this exact shape:
{
  "scores": {
    "{$firstCriterion}": {"band": 6.0, "comment": "2-3 sentences referring to the actual response"},
    "coherence_cohesion": {"band": 6.0, "comment": "..."},
    "lexical_resource": {"band": 6.0, "comment": "..."},
    "grammatical_range_accuracy": {"band": 6.0, "comment": "..."}
  },
  "summary": "3-4 sentence overall assessment addressed to the candidate as 'you'",
  "strengths": ["..."],
  "improvements": ["specific, actionable advice"],
  "corrections": [{"original": "exact text copied from the response", "corrected": "corrected version", "explanation": "short reason"}],
  "model_answer": "a complete band 8-9 response to the same task"
}
Bands use half steps from 0 to 9. Give 3-6 strengths, 3-6 improvements and up to 12 corrections ordered by importance.
PROMPT;
    }

    private function userPrompt(WritingAttempt $attempt): string
    {
        $task = $attempt->task;
        $visual = $task->visual ? "\nVISUAL DATA (as JSON): ".json_encode($task->visual) : '';

        return "TASK\n{$task->prompt}{$visual}\n\nCANDIDATE RESPONSE ({$attempt->word_count} words)\n{$attempt->essay}";
    }
}
