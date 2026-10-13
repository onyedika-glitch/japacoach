<?php

namespace Tests\Feature;

use App\Models\PracticeQuestion;
use App\Models\PracticeSection;
use App\Models\PracticeTest as PracticeTestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeTest extends TestCase
{
    use RefreshDatabase;

    private PracticeTestModel $test;

    /**
     * @var list<PracticeQuestion>
     */
    private array $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->test = PracticeTestModel::factory()->create(['module' => 'reading', 'test_type' => 'academic']);
        $section = PracticeSection::factory()->for($this->test, 'test')->create();
        $this->questions = [
            PracticeQuestion::factory()->for($section, 'section')->create(['number' => 1, 'type' => 'tfng', 'answers' => ['TRUE']]),
            PracticeQuestion::factory()->for($section, 'section')->create(['number' => 2, 'type' => 'gap', 'answers' => ['solar panels', 'solar panel']]),
            PracticeQuestion::factory()->for($section, 'section')->create([
                'number' => 3, 'type' => 'mcq', 'answers' => ['B'],
                'options' => [['key' => 'A', 'text' => 'One'], ['key' => 'B', 'text' => 'Two']],
            ]),
        ];
    }

    public function test_answer_key_is_hidden_until_submission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")->assertCreated();

        $response->assertJsonPath('attempt.review', null)
            ->assertJsonPath('attempt.test.sections.0.questions.0.options.0.key', 'TRUE')
            ->assertJsonMissingPath('attempt.test.sections.0.questions.0.answers');
    }

    public function test_submission_marks_answers_leniently_and_converts_to_a_band(): void
    {
        $user = User::factory()->create();
        $attemptId = $this->actingAs($user)->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")->json('attempt.id');

        $this->patchJson("/api/practice/attempts/{$attemptId}", ['answers' => [$this->questions[0]->id => 'TRUE']])->assertOk();

        $response = $this->postJson("/api/practice/attempts/{$attemptId}/submit", ['answers' => [
            $this->questions[1]->id => ' The Solar Panels. ',
            $this->questions[2]->id => 'A',
        ]])->assertOk();

        $response->assertJsonPath('attempt.status', 'submitted')
            ->assertJsonPath('attempt.correct_count', 2)
            ->assertJsonPath('attempt.total_count', 3)
            ->assertJsonPath('attempt.review.1.is_correct', true)
            ->assertJsonPath('attempt.review.2.is_correct', false);
        // 2/3 → 27/40 → Academic band 6.5
        $this->assertEquals(6.5, $response->json('attempt.band'));
    }

    public function test_submitted_attempts_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $attemptId = $this->actingAs($user)->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")->json('attempt.id');
        $this->postJson("/api/practice/attempts/{$attemptId}/submit", ['answers' => []])->assertOk();

        $this->patchJson("/api/practice/attempts/{$attemptId}", ['answers' => [$this->questions[0]->id => 'TRUE']])
            ->assertUnprocessable();
    }

    public function test_free_users_hit_the_monthly_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")->assertCreated();
        $this->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")
            ->assertStatus(402)
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('feature', 'reading');
    }

    public function test_premium_users_have_unlimited_reading(): void
    {
        $user = User::factory()->premium()->create();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")->assertCreated();
        }
    }

    public function test_users_cannot_see_other_users_attempts(): void
    {
        $owner = User::factory()->create();
        $attemptId = $this->actingAs($owner)->postJson("/api/practice/reading/tests/{$this->test->id}/attempts")->json('attempt.id');

        $this->actingAs(User::factory()->create())->getJson("/api/practice/attempts/{$attemptId}")->assertNotFound();
    }
}
