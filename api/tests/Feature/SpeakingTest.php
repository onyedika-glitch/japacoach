<?php

namespace Tests\Feature;

use App\Jobs\ScoreSpeakingAttempt;
use App\Jobs\TranscribeSpeakingAnswer;
use App\Models\SpeakingAttempt;
use App\Models\SpeakingQuestion;
use App\Models\User;
use App\Notifications\ResultReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SpeakingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (range(1, 4) as $i) {
            SpeakingQuestion::factory()->create(['part' => 1, 'topic' => 'Hometown', 'prompt' => "Part 1 question {$i}?"]);
        }
        SpeakingQuestion::factory()->create(['part' => 2, 'topic' => 'A helpful person', 'prompt' => 'Describe a person who helped you.', 'cue_points' => ['who', 'when', 'how', 'and explain why']]);
        foreach (range(1, 3) as $i) {
            SpeakingQuestion::factory()->create(['part' => 3, 'topic' => 'A helpful person', 'prompt' => "Part 3 question {$i}?"]);
        }

        Storage::fake('local');
        config(['services.deepseek.key' => 'test-key', 'services.speech.token' => 'token']);
    }

    public function test_full_test_asks_part_one_two_and_linked_part_three_questions_in_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/speaking/attempts', ['mode' => 'full'])->assertCreated();

        $this->assertSame([1, 1, 1, 1, 2, 3, 3, 3], array_column($response->json('attempt.questions'), 'part'));
        $this->assertSame(['who', 'when', 'how', 'and explain why'], $response->json('attempt.questions.4.cue_points'));
    }

    public function test_uploaded_answer_is_queued_for_transcription(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $attempt = $this->actingAs($user)->postJson('/api/speaking/attempts', ['mode' => 'part1'])->json('attempt');

        $this->postJson("/api/speaking/attempts/{$attempt['id']}/answers", [
            'question_id' => $attempt['questions'][0]['id'],
            'audio' => UploadedFile::fake()->create('answer.webm', 200, 'audio/webm'),
            'duration_seconds' => 32.4,
        ])->assertCreated()->assertJsonPath('answer.status', 'uploaded');

        Queue::assertPushedOn('speech', TranscribeSpeakingAnswer::class);
    }

    public function test_questions_outside_the_attempt_are_rejected(): void
    {
        $user = User::factory()->create();
        $attempt = $this->actingAs($user)->postJson('/api/speaking/attempts', ['mode' => 'part2'])->json('attempt');
        $otherQuestion = SpeakingQuestion::where('part', 1)->first();

        $this->postJson("/api/speaking/attempts/{$attempt['id']}/answers", [
            'question_id' => $otherQuestion->id,
            'audio' => UploadedFile::fake()->create('answer.webm', 100, 'audio/webm'),
            'duration_seconds' => 10,
        ])->assertJsonValidationErrors('question_id');
    }

    public function test_submitting_without_answers_is_rejected(): void
    {
        $user = User::factory()->create();
        $attemptId = $this->actingAs($user)->postJson('/api/speaking/attempts', ['mode' => 'part1'])->json('attempt.id');

        $this->postJson("/api/speaking/attempts/{$attemptId}/submit")->assertJsonValidationErrors('attempt');
    }

    public function test_answers_are_transcribed_scored_and_emailed(): void
    {
        Notification::fake();
        Http::fake([
            '*/transcribe' => Http::response([
                'text' => 'I come from Lagos which is a very busy city.',
                'duration' => 6.0,
                'words' => [
                    ['word' => 'I', 'start' => 0.0, 'end' => 0.2, 'probability' => 0.99],
                    ['word' => 'come', 'start' => 0.3, 'end' => 0.6, 'probability' => 0.98],
                    ['word' => 'from', 'start' => 2.5, 'end' => 2.8, 'probability' => 0.97],
                    ['word' => 'Lagos', 'start' => 2.9, 'end' => 3.4, 'probability' => 0.4],
                ],
            ]),
            'api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'scores' => [
                    'fluency_coherence' => ['band' => 6.5, 'comment' => 'Good flow.'],
                    'lexical_resource' => ['band' => 6, 'comment' => 'Adequate.'],
                    'grammatical_range_accuracy' => ['band' => 6.5, 'comment' => 'Mostly accurate.'],
                    'pronunciation' => ['band' => 7, 'comment' => 'Clear.'],
                ],
                'summary' => 'Solid performance.',
                'strengths' => ['Clear ideas'],
                'improvements' => ['Use more linking words'],
                'corrections' => [['original' => 'very busy city', 'corrected' => 'bustling city', 'explanation' => 'More precise.']],
                'better_answers' => [],
                'pronunciation_tips' => [['word' => 'Lagos', 'tip' => 'LAY-goss']],
            ])]]]]),
        ]);

        $user = User::factory()->create();
        $attempt = $this->actingAs($user)->postJson('/api/speaking/attempts', ['mode' => 'part1'])->json('attempt');

        $this->postJson("/api/speaking/attempts/{$attempt['id']}/answers", [
            'question_id' => $attempt['questions'][0]['id'],
            'audio' => UploadedFile::fake()->create('answer.webm', 100, 'audio/webm'),
            'duration_seconds' => 6,
        ])->assertCreated();

        $this->postJson("/api/speaking/attempts/{$attempt['id']}/submit")->assertOk();

        $scored = SpeakingAttempt::find($attempt['id']);
        $this->assertSame('scored', $scored->status);
        // (6.5 + 6 + 6.5 + 7) / 4 = 6.5
        $this->assertSame(6.5, $scored->overall_band);
        $this->assertSame(1, $scored->feedback['metrics']['long_pauses']);
        $this->assertContains('lagos', $scored->feedback['metrics']['unclear_words']);
        Notification::assertSentTo($user, ResultReady::class);
    }

    public function test_scoring_waits_until_every_uploaded_answer_is_transcribed(): void
    {
        Queue::fake();
        $attempt = SpeakingAttempt::factory()->create(['status' => 'processing']);
        $attempt->answers()->create(['speaking_question_id' => SpeakingQuestion::first()->id, 'part' => 1, 'position' => 1, 'status' => 'uploaded']);

        ScoreSpeakingAttempt::dispatchIfReady($attempt->id);
        Queue::assertNotPushed(ScoreSpeakingAttempt::class);

        $attempt->answers()->update(['status' => 'transcribed']);
        ScoreSpeakingAttempt::dispatchIfReady($attempt->id);
        Queue::assertPushed(ScoreSpeakingAttempt::class);
    }

    public function test_free_user_gets_one_full_mock_and_referral_bonus_unlocks_another(): void
    {
        $user = User::factory()->create(['bonus_speaking_mocks' => 1]);

        $this->actingAs($user)->postJson('/api/speaking/attempts', ['mode' => 'full'])->assertCreated();
        $this->postJson('/api/speaking/attempts', ['mode' => 'full'])->assertCreated();
        $this->postJson('/api/speaking/attempts', ['mode' => 'full'])->assertStatus(402);

        $this->assertSame(0, $user->refresh()->bonus_speaking_mocks);
    }

    public function test_answer_audio_is_private_to_its_owner(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $attempt = $this->actingAs($owner)->postJson('/api/speaking/attempts', ['mode' => 'part1'])->json('attempt');
        $this->postJson("/api/speaking/attempts/{$attempt['id']}/answers", [
            'question_id' => $attempt['questions'][0]['id'],
            'audio' => UploadedFile::fake()->create('answer.webm', 100, 'audio/webm'),
            'duration_seconds' => 5,
        ]);
        $answerId = SpeakingAttempt::find($attempt['id'])->answers()->first()->id;

        $this->get("/api/speaking/answers/{$answerId}/audio")->assertOk();
        $this->actingAs(User::factory()->create())->getJson("/api/speaking/answers/{$answerId}/audio")->assertNotFound();
    }
}
