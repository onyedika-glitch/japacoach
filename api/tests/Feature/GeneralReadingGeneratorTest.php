<?php

namespace Tests\Feature;

use App\Models\PracticeTest;
use App\Services\Content\ContentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeneralReadingGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_training_reading_has_two_short_texts_per_section_and_one_long_text(): void
    {
        config(['services.deepseek.key' => 'test-key']);
        $prompts = [];

        // Answer each text request with the number of questions it asked for, all of one type.
        Http::fake(['api.deepseek.com/*' => function (Request $request) use (&$prompts) {
            $prompt = (string) collect($request['messages'])->last()['content'];
            $prompts[] = $prompt;
            preg_match('/exactly (\d+) questions/', $prompt, $m);
            $questions = array_fill(0, (int) $m[1], ['type' => 'tfng', 'prompt' => 'A statement.', 'options' => null, 'answers' => ['TRUE'], 'explanation' => 'Line 1.']);

            return Http::response(['choices' => [['message' => ['content' => json_encode([
                'title' => 'City Pool', 'instructions' => 'Answer the questions.', 'body' => "Para one.\n\nPara two.", 'questions' => $questions,
            ])]]]]);
        }]);

        $test = app(ContentGenerator::class)->reading('general');

        $this->assertSame('general', $test->test_type);
        $sections = $test->sections()->orderBy('position')->with('questions')->get();
        $this->assertSame(
            ['Section 1 · Text A', 'Section 1 · Text B', 'Section 2 · Text A', 'Section 2 · Text B', 'Section 3'],
            $sections->map(fn ($s) => explode(':', $s->title)[0])->all(),
        );
        $this->assertSame([7, 7, 7, 6, 13], $sections->map(fn ($s) => $s->questions->count())->all());
        $this->assertSame(range(1, 40), $sections->flatMap(fn ($s) => $s->questions->pluck('number'))->all());

        // Short texts ask for one question type; the long text for three.
        $this->assertStringContainsString('ONLY this question type', $prompts[0]);
        $this->assertStringContainsString('numbered 1-7', $prompts[0]);
        $this->assertStringContainsString('three question types', $prompts[4]);
        $this->assertStringContainsString('numbered 28-40', $prompts[4]);
        $this->assertSame(1, PracticeTest::count());
    }
}
