<?php

namespace Tests\Unit;

use App\Services\Scoring\BandCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BandCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{float, float}>
     */
    public static function overallRounding(): array
    {
        return [
            'below quarter rounds down' => [6.125, 6.0],
            'quarter rounds up to half' => [6.25, 6.5],
            'between quarter and three quarters is half' => [6.625, 6.5],
            'three quarters rounds up to whole' => [6.75, 7.0],
            'whole stays whole' => [7.0, 7.0],
        ];
    }

    #[DataProvider('overallRounding')]
    public function test_overall_band_rounding(float $average, float $expected): void
    {
        $this->assertSame($expected, BandCalculator::roundOverall($average));
    }

    public function test_overall_band_averages_four_modules(): void
    {
        // (6.5 + 6.5 + 5.0 + 7.0) / 4 = 6.25 → 6.5
        $this->assertSame(6.5, BandCalculator::overall(6.5, 6.5, 5.0, 7.0));
    }

    public function test_criteria_average_rounds_down_to_half_band(): void
    {
        $this->assertSame(6.5, BandCalculator::fromCriteria(7.0, 6.5, 6.5, 6.5)); // 6.625
        $this->assertSame(6.0, BandCalculator::fromCriteria(6.0, 6.0, 6.5, 6.0)); // 6.125
        $this->assertSame(7.0, BandCalculator::fromCriteria(7.0, 7.0, 7.0, 7.0));
    }

    public function test_task_two_counts_double_in_writing(): void
    {
        // (5.0 + 2 × 7.0) / 3 = 6.33 → 6.5
        $this->assertSame(6.5, BandCalculator::writingModule(5.0, 7.0));
    }

    public function test_raw_scores_convert_with_module_specific_tables(): void
    {
        $this->assertSame(7.0, BandCalculator::fromRawScore('listening', null, 30, 40));
        $this->assertSame(7.0, BandCalculator::fromRawScore('reading', 'academic', 30, 40));
        $this->assertSame(6.0, BandCalculator::fromRawScore('reading', 'general', 30, 40));
        $this->assertSame(0.0, BandCalculator::fromRawScore('listening', null, 0, 40));
    }

    public function test_short_tests_are_scaled_to_forty_questions(): void
    {
        // 10/13 → 31/40 → listening band 7.0
        $this->assertSame(7.0, BandCalculator::fromRawScore('listening', null, 10, 13));
    }

    public function test_ai_bands_are_clamped_to_half_steps(): void
    {
        $this->assertSame(6.5, BandCalculator::normalize('6.6'));
        $this->assertSame(9.0, BandCalculator::normalize(11));
        $this->assertSame(0.0, BandCalculator::normalize('n/a'));
    }
}
