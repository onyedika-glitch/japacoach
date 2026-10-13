<?php

namespace Tests\Feature;

use App\Exceptions\ExternalServiceException;
use App\Jobs\ScoreWritingAttempt;
use App\Models\User;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WritingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.deepseek.key' => 'test-key']);
        Notification::fake();
    }

    public function test_tasks_are_filtered_by_the_users_test_type(): void
    {
        WritingTask::factory()->create(['task_number' => 1, 'test_type' => 'academic', 'title' => 'Chart']);
        WritingTask::factory()->create(['task_number' => 1, 'test_type' => 'general', 'title' => 'Letter']);
        WritingTask::factory()->create(['task_number' => 2, 'test_type' => 'both', 'title' => 'Essay']);
        $user = User::factory()->create(['test_type' => 'general']);

        $titles = collect($this->actingAs($user)->getJson('/api/writing/tasks')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Letter', 'Essay'], $titles);
    }

    public function test_essay_is_scored_by_the_ai_examiner(): void
    {
        $this->fakeExaminer(['task_response' => 7, 'coherence_cohesion' => 6.5, 'lexical_resource' => 6.5, 'grammatical_range_accuracy' => 6]);
        $task = WritingTask::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/writing/attempts', [
            'writing_task_id' => $task->id,
            'essay' => str_repeat('Governments should invest more in public transport. ', 30),
            'time_spent_seconds' => 2100,
        ])->assertCreated();

        $attempt = WritingAttempt::find($response->json('attempt.id'));
        $this->assertSame('scored', $attempt->status);
        $this->assertSame(210, $attempt->word_count);
        // (7 + 6.5 + 6.5 + 6) / 4 = 6.5
        $this->assertSame(6.5, $attempt->overall_band);
        $this->assertSame('Model answer text.', $attempt->feedback['model_answer']);
    }

    public function test_very_short_responses_are_capped_regardless_of_ai_score(): void
    {
        $this->fakeExaminer(['task_response' => 8, 'coherence_cohesion' => 8, 'lexical_resource' => 8, 'grammatical_range_accuracy' => 8]);
        $task = WritingTask::factory()->create();
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson('/api/writing/attempts', [
            'writing_task_id' => $task->id,
            'essay' => str_repeat('word ', 100),
        ])->json('attempt.id');

        $this->assertSame(4.5, WritingAttempt::find($id)->overall_band);
    }

    public function test_responses_under_fifty_words_are_not_sent_for_marking(): void
    {
        $task = WritingTask::factory()->create();

        $this->actingAs(User::factory()->create())->postJson('/api/writing/attempts', [
            'writing_task_id' => $task->id,
            'essay' => 'Too short to mark.',
        ])->assertJsonValidationErrors('essay');
    }

    public function test_failed_ai_marking_marks_the_attempt_failed(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response('Server error', 500)]);
        $attempt = WritingAttempt::factory()->create(['status' => 'processing']);

        try {
            dispatch_sync(new ScoreWritingAttempt($attempt->id));
            $this->fail('The scoring job should have thrown.');
        } catch (ExternalServiceException) {
            // The queue retries; once retries are exhausted the job's failed() hook runs.
        }
        (new ScoreWritingAttempt($attempt->id))->failed(null);

        $this->assertSame('failed', $attempt->refresh()->status);
    }

    /**
     * @param  array<string, float|int>  $bands
     */
    private function fakeExaminer(array $bands): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'scores' => collect($bands)->map(fn ($band) => ['band' => $band, 'comment' => 'Comment.'])->all(),
            'summary' => 'Summary.',
            'strengths' => ['Clear position'],
            'improvements' => ['Develop examples'],
            'corrections' => [],
            'model_answer' => 'Model answer text.',
        ])]]]])]);
    }
}
