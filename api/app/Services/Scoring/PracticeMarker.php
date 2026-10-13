<?php

namespace App\Services\Scoring;

use App\Models\PracticeAttempt;
use App\Models\PracticeQuestion;

/**
 * Marks reading and listening attempts against the answer key.
 */
class PracticeMarker
{
    /**
     * Mark the attempt's saved answers and store the result.
     */
    public function mark(PracticeAttempt $attempt): PracticeAttempt
    {
        $questions = $attempt->test->questions()->get();
        $answers = $attempt->answers ?? [];

        $correct = $questions->filter(
            fn (PracticeQuestion $question): bool => $this->isCorrect($question, $answers[$question->id] ?? null)
        )->count();

        $attempt->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
            'correct_count' => $correct,
            'total_count' => $questions->count(),
            'band' => BandCalculator::fromRawScore($attempt->module, $attempt->test->test_type, $correct, $questions->count()),
        ])->save();

        return $attempt;
    }

    /**
     * @param  string|list<string>|null  $given
     */
    public function isCorrect(PracticeQuestion $question, string|array|null $given): bool
    {
        if (is_array($given)) {
            $given = implode(' ', $given);
        }

        if (blank($given)) {
            return false;
        }

        $normalizedGiven = $this->normalize($given, $question->type);

        foreach ($question->answers as $accepted) {
            if ($this->normalize((string) $accepted, $question->type) === $normalizedGiven) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value, string $type): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if ($type === 'gap') {
            $value = trim($value, " .,;:!?\"'");
            $value = preg_replace('/^(a|an|the) /u', '', $value) ?? $value;
        }

        return $value;
    }
}
