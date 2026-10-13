<?php

namespace App\Services\Scoring;

/**
 * Objective fluency and clarity signals computed from word-level timestamps.
 */
class SpeakingMetrics
{
    private const LONG_PAUSE_SECONDS = 1.5;

    private const LOW_CONFIDENCE = 0.55;

    private const FILLERS = ['um', 'uh', 'er', 'erm', 'ah', 'hmm', 'mm', 'uhm'];

    /**
     * @param  list<array{word: string, start: float, end: float, probability: float}>  $words
     * @return array{word_count: int, speaking_seconds: float, words_per_minute: int, long_pauses: int, filler_words: int, unclear_words: list<string>}
     */
    public static function fromWords(array $words, float $durationSeconds): array
    {
        $longPauses = 0;
        $fillers = 0;
        $unclear = [];
        $previousEnd = null;
        $spokenWords = 0;

        foreach ($words as $word) {
            $clean = mb_strtolower(trim((string) $word['word'], " \t\n.,!?;:\"'"));

            if ($clean === '') {
                continue;
            }

            if (in_array($clean, self::FILLERS, true)) {
                $fillers++;
            } else {
                $spokenWords++;
            }

            if ($previousEnd !== null && ((float) $word['start'] - $previousEnd) >= self::LONG_PAUSE_SECONDS) {
                $longPauses++;
            }
            $previousEnd = (float) $word['end'];

            if ((float) ($word['probability'] ?? 1) < self::LOW_CONFIDENCE && mb_strlen($clean) >= 3) {
                $unclear[$clean] = true;
            }
        }

        $firstStart = (float) ($words[0]['start'] ?? 0);
        $speakingSeconds = $previousEnd !== null ? max($previousEnd - $firstStart, 1.0) : max($durationSeconds, 1.0);

        return [
            'word_count' => $spokenWords,
            'speaking_seconds' => round($speakingSeconds, 1),
            'words_per_minute' => (int) round($spokenWords / ($speakingSeconds / 60)),
            'long_pauses' => $longPauses,
            'filler_words' => $fillers,
            'unclear_words' => array_slice(array_keys($unclear), 0, 20),
        ];
    }

    /**
     * Combine per-answer metrics into attempt-level metrics.
     *
     * @param  list<array<string, mixed>>  $answerMetrics
     * @return array{words_per_minute: int, long_pauses: int, filler_words: int, total_speaking_seconds: float, unclear_words: list<string>}
     */
    public static function combine(array $answerMetrics): array
    {
        $words = array_sum(array_column($answerMetrics, 'word_count'));
        $seconds = array_sum(array_column($answerMetrics, 'speaking_seconds'));
        $unclear = array_values(array_unique(array_merge(...array_map(
            fn (array $metrics): array => $metrics['unclear_words'] ?? [],
            $answerMetrics ?: [[]],
        ))));

        return [
            'words_per_minute' => $seconds > 0 ? (int) round($words / ($seconds / 60)) : 0,
            'long_pauses' => array_sum(array_column($answerMetrics, 'long_pauses')),
            'filler_words' => array_sum(array_column($answerMetrics, 'filler_words')),
            'total_speaking_seconds' => round($seconds, 1),
            'unclear_words' => array_slice($unclear, 0, 30),
        ];
    }
}
