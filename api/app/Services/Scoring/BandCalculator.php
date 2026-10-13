<?php

namespace App\Services\Scoring;

/**
 * IELTS band arithmetic: criteria averaging, overall rounding and raw-score conversion.
 */
class BandCalculator
{
    /**
     * Raw score (out of 40) thresholds, highest first: [minimum correct, band].
     *
     * @var array<string, list<array{int, float}>>
     */
    private const RAW_SCORE_TABLES = [
        'listening' => [
            [39, 9.0], [37, 8.5], [35, 8.0], [32, 7.5], [30, 7.0], [26, 6.5], [23, 6.0],
            [18, 5.5], [16, 5.0], [13, 4.5], [10, 4.0], [8, 3.5], [6, 3.0], [4, 2.5], [2, 2.0], [1, 1.0],
        ],
        'reading_academic' => [
            [39, 9.0], [37, 8.5], [35, 8.0], [33, 7.5], [30, 7.0], [27, 6.5], [23, 6.0],
            [19, 5.5], [15, 5.0], [13, 4.5], [10, 4.0], [8, 3.5], [6, 3.0], [4, 2.5], [2, 2.0], [1, 1.0],
        ],
        'reading_general' => [
            [40, 9.0], [39, 8.5], [37, 8.0], [36, 7.5], [34, 7.0], [32, 6.5], [30, 6.0],
            [27, 5.5], [23, 5.0], [19, 4.5], [15, 4.0], [12, 3.5], [9, 3.0], [6, 2.5], [3, 2.0], [1, 1.0],
        ],
    ];

    /**
     * Speaking and Writing: the average of the four criteria, rounded down to the nearest half band.
     */
    public static function fromCriteria(float ...$criteriaBands): float
    {
        if ($criteriaBands === []) {
            return 0.0;
        }

        $average = array_sum($criteriaBands) / count($criteriaBands);

        return floor($average * 2 + 1e-9) / 2;
    }

    /**
     * Overall band rounding: .25 rounds up to .5 and .75 rounds up to the next whole band.
     */
    public static function roundOverall(float $average): float
    {
        $whole = floor($average);
        $fraction = $average - $whole;

        return match (true) {
            $fraction < 0.25 - 1e-9 => $whole,
            $fraction < 0.75 - 1e-9 => $whole + 0.5,
            default => $whole + 1.0,
        };
    }

    /**
     * Overall band across the four modules.
     */
    public static function overall(float $listening, float $reading, float $writing, float $speaking): float
    {
        return self::roundOverall(($listening + $reading + $writing + $speaking) / 4);
    }

    /**
     * Writing module band: Task 2 carries twice the weight of Task 1.
     */
    public static function writingModule(float $task1, float $task2): float
    {
        return self::roundOverall(($task1 + 2 * $task2) / 3);
    }

    /**
     * Convert a raw score to a band. Tests shorter than 40 questions are scaled to 40 first.
     */
    public static function fromRawScore(string $module, ?string $testType, int $correct, int $total): float
    {
        if ($total <= 0 || $correct <= 0) {
            return 0.0;
        }

        $scaled = (int) round(min($correct, $total) * 40 / $total);
        $table = self::RAW_SCORE_TABLES[$module === 'reading' ? 'reading_'.($testType === 'general' ? 'general' : 'academic') : 'listening'];

        foreach ($table as [$minimum, $band]) {
            if ($scaled >= $minimum) {
                return $band;
            }
        }

        return 0.0;
    }

    /**
     * Clamp an AI-provided band to 0–9 in half-band steps.
     */
    public static function normalize(mixed $band): float
    {
        $value = is_numeric($band) ? (float) $band : 0.0;

        return max(0.0, min(9.0, round($value * 2) / 2));
    }
}
