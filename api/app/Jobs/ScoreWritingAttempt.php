<?php

namespace App\Jobs;

use App\Models\WritingAttempt;
use App\Notifications\ResultReady;
use App\Services\MockExamService;
use App\Services\Scoring\WritingScorer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks the AI examiner to mark a writing attempt.
 */
class ScoreWritingAttempt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public int $attemptId) {}

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(WritingScorer $scorer, MockExamService $mockExams): void
    {
        $attempt = WritingAttempt::with(['task', 'user'])->find($this->attemptId);

        if (! $attempt || $attempt->status !== 'processing') {
            return;
        }

        $scorer->score($attempt);

        if ($attempt->mockExam) {
            $mockExams->refresh($attempt->mockExam);
        }

        $attempt->user->notify(new ResultReady('writing', $attempt->id, $attempt->overall_band));
    }

    public function failed(?Throwable $exception): void
    {
        WritingAttempt::whereKey($this->attemptId)->where('status', 'processing')->update(['status' => 'failed']);
    }
}
