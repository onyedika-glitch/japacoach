<?php

namespace Tests\Unit;

use App\Support\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function test_dashes_become_commas_and_ranges_become_words(): void
    {
        $this->assertSame('Good answer, but develop it further.', Text::withoutDashes('Good answer — but develop it further.'));
        $this->assertSame('Speak for 1 to 2 minutes.', Text::withoutDashes('Speak for 1–2 minutes.'));
        $this->assertSame('Well done.', Text::withoutDashes('Well done —.'));
        $this->assertSame('well-known hyphens stay', Text::withoutDashes('well-known hyphens stay'));
    }

    public function test_nested_feedback_is_cleaned(): void
    {
        $cleaned = Text::withoutDashesDeep(['summary' => 'Clear — mostly', 'bands' => [6.5], 'tips' => ['Use 2–3 linkers']]);

        $this->assertSame(['summary' => 'Clear, mostly', 'bands' => [6.5], 'tips' => ['Use 2 to 3 linkers']], $cleaned);
    }
}
