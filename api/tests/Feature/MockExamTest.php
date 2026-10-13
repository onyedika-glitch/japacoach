<?php

namespace Tests\Feature;

use App\Models\MockExam;
use App\Models\PracticeAttempt;
use App\Models\PracticeTest;
use App\Models\SpeakingAttempt;
use App\Models\User;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use App\Services\MockExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('content:import')->assertSuccessful();
    }

    public function test_mock_exam_picks_content_matching_the_users_test_type(): void
    {
        $user = User::factory()->create(['test_type' => 'general']);

        $response = $this->actingAs($user)->postJson('/api/mock-exams')->assertCreated();

        $exam = MockExam::find($response->json('exam.id'));
        $this->assertSame('general', $exam->reading_test_id ? PracticeTest::find($exam->reading_test_id)->test_type : null);
        $this->assertContains(WritingTask::find($exam->writing_task1_id)->test_type, ['general', 'both']);
        $response->assertJsonPath('exam.parts.listening.status', 'not_started');
    }

    public function test_parts_started_for_the_exam_do_not_use_up_monthly_limits(): void
    {
        $user = User::factory()->create();
        $examId = $this->actingAs($user)->postJson('/api/mock-exams')->json('exam.id');
        $exam = MockExam::find($examId);

        $this->postJson("/api/practice/reading/tests/{$exam->reading_test_id}/attempts", ['mock_exam_id' => $examId])->assertCreated();
        $this->postJson("/api/practice/reading/tests/{$exam->reading_test_id}/attempts")->assertCreated();
    }

    public function test_a_part_cannot_be_started_twice_or_with_the_wrong_test(): void
    {
        $user = User::factory()->premium()->create();
        $examId = $this->actingAs($user)->postJson('/api/mock-exams')->json('exam.id');
        $exam = MockExam::find($examId);

        $this->postJson("/api/practice/listening/tests/{$exam->listening_test_id}/attempts", ['mock_exam_id' => $examId])->assertCreated();
        $this->postJson("/api/practice/listening/tests/{$exam->listening_test_id}/attempts", ['mock_exam_id' => $examId])
            ->assertJsonValidationErrors('mock_exam_id');
        $this->postJson('/api/speaking/attempts', ['mode' => 'part1', 'mock_exam_id' => $examId])
            ->assertJsonValidationErrors('mock_exam_id');
    }

    public function test_exam_completes_with_the_overall_band_once_all_modules_are_marked(): void
    {
        $user = User::factory()->create();
        $exam = MockExam::find($this->actingAs($user)->postJson('/api/mock-exams')->json('exam.id'));

        foreach (['listening' => [$exam->listening_test_id, 7.0], 'reading' => [$exam->reading_test_id, 6.5]] as $module => [$testId, $band]) {
            PracticeAttempt::factory()->for($user)->create([
                'mock_exam_id' => $exam->id, 'module' => $module, 'practice_test_id' => $testId,
                'status' => 'submitted', 'band' => $band, 'submitted_at' => now(),
            ]);
        }
        foreach ([$exam->writing_task1_id => 5.5, $exam->writing_task2_id => 6.5] as $taskId => $band) {
            WritingAttempt::factory()->for($user)->create(['mock_exam_id' => $exam->id, 'writing_task_id' => $taskId, 'status' => 'scored', 'overall_band' => $band]);
        }
        $speaking = SpeakingAttempt::factory()->for($user)->create(['mock_exam_id' => $exam->id, 'status' => 'scored']);
        $speaking->forceFill(['overall_band' => 6.0])->save();

        app(MockExamService::class)->refresh($exam);

        // Writing: (5.5 + 2 × 6.5) / 3 = 6.17 → 6.0; overall (7 + 6.5 + 6 + 6) / 4 = 6.375 → 6.5
        $this->getJson("/api/mock-exams/{$exam->id}")
            ->assertJsonPath('exam.status', 'completed')
            ->assertJsonPath('exam.parts.writing.band', 6)
            ->assertJsonPath('exam.overall_band', 6.5);
    }
}
