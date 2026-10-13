<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\PracticeAttempt;
use App\Models\PracticeTest;
use App\Models\User;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use App\Services\Scoring\BandCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Assembles full mock exams and computes the overall band once every part is marked.
 */
class MockExamService
{
    public function __construct(private UsageService $usage) {}

    public function create(User $user): MockExam
    {
        $this->usage->authorize($user, 'speaking_mocks');

        $testType = $user->test_type;
        $listening = $this->pickUnseen(PracticeTest::published()->where('module', 'listening')->forTestType($testType), $user);
        $reading = $this->pickUnseen(PracticeTest::published()->where('module', 'reading')->forTestType($testType), $user);
        $task1 = WritingTask::published()->where('task_number', 1)->forTestType($testType)->inRandomOrder()->first();
        $task2 = WritingTask::published()->where('task_number', 2)->forTestType($testType)->inRandomOrder()->first();

        if (! $listening || ! $reading || ! $task1 || ! $task2) {
            throw ValidationException::withMessages(['exam' => 'Not enough practice content is available for a full mock exam yet.']);
        }

        return MockExam::create([
            'user_id' => $user->id,
            'test_type' => $testType,
            'status' => 'in_progress',
            'listening_test_id' => $listening->id,
            'reading_test_id' => $reading->id,
            'writing_task1_id' => $task1->id,
            'writing_task2_id' => $task2->id,
        ]);
    }

    /**
     * Recalculate part bands and complete the exam when all four modules have a band.
     */
    public function refresh(MockExam $exam): MockExam
    {
        $parts = $this->parts($exam);
        $bands = [
            'listening' => $parts['listening']['band'],
            'reading' => $parts['reading']['band'],
            'writing' => $parts['writing']['band'],
            'speaking' => $parts['speaking']['band'],
        ];

        $exam->bands = $bands;

        if (! in_array(null, $bands, true) && $exam->status !== 'completed') {
            $exam->overall_band = BandCalculator::overall($bands['listening'], $bands['reading'], $bands['writing'], $bands['speaking']);
            $exam->status = 'completed';
            $exam->completed_at = now();
        }

        $exam->save();

        return $exam;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function parts(MockExam $exam): array
    {
        $practice = $exam->practiceAttempts()->latest('id')->get()->keyBy('module');
        $writing = $exam->writingAttempts()->latest('id')->get()->keyBy('writing_task_id');
        $speaking = $exam->speakingAttempts()->latest('id')->first();

        $task1 = $writing->get($exam->writing_task1_id);
        $task2 = $writing->get($exam->writing_task2_id);
        $writingBand = ($task1?->overall_band !== null && $task2?->overall_band !== null)
            ? BandCalculator::writingModule($task1->overall_band, $task2->overall_band)
            : null;

        return [
            'listening' => $this->practicePart($exam->listening_test_id, $practice->get('listening')),
            'reading' => $this->practicePart($exam->reading_test_id, $practice->get('reading')),
            'writing' => [
                'task1_id' => $exam->writing_task1_id,
                'task2_id' => $exam->writing_task2_id,
                'task1_attempt_id' => $task1?->id,
                'task2_attempt_id' => $task2?->id,
                'status' => $this->writingStatus($task1, $task2),
                'band' => $writingBand,
            ],
            'speaking' => [
                'attempt_id' => $speaking?->id,
                'status' => match ($speaking?->status) {
                    null => 'not_started',
                    'in_progress' => 'in_progress',
                    'scored' => 'done',
                    default => 'processing',
                },
                'band' => $speaking?->overall_band,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function practicePart(?int $testId, ?PracticeAttempt $attempt): array
    {
        return [
            'test_id' => $testId,
            'attempt_id' => $attempt?->id,
            'status' => match (true) {
                $attempt === null => 'not_started',
                $attempt->isSubmitted() => 'done',
                default => 'in_progress',
            },
            'band' => $attempt?->band,
        ];
    }

    private function writingStatus(?WritingAttempt $task1, ?WritingAttempt $task2): string
    {
        if (! $task1 && ! $task2) {
            return 'not_started';
        }

        if ($task1?->status === 'scored' && $task2?->status === 'scored') {
            return 'done';
        }

        return ($task1 && $task2) ? 'processing' : 'in_progress';
    }

    /**
     * Prefer a test the user has not attempted yet.
     *
     * @param  Builder<PracticeTest>  $query
     */
    private function pickUnseen(Builder $query, User $user): ?PracticeTest
    {
        $unseen = (clone $query)
            ->whereDoesntHave('attempts', fn (Builder $q) => $q->where('user_id', $user->id))
            ->inRandomOrder()
            ->first();

        return $unseen ?? $query->inRandomOrder()->first();
    }
}
