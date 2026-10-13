<?php

namespace App\Jobs;

use App\Models\SpeakingAttempt;
use App\Notifications\ResultReady;
use App\Services\MockExamService;
use App\Services\Scoring\SpeakingScorer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks the AI examiner to mark a submitted speaking attempt.
 */
class ScoreSpeakingAttempt implements ShouldBeUnique, ShouldQueue
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

    /**
     * Dispatch scoring when the attempt is submitted and no answer is still waiting for transcription.
     */
    public static function dispatchIfReady(int $attemptId): void
    {
        $attempt = SpeakingAttempt::find($attemptId);

        if (! $attempt || $attempt->status !== 'processing') {
            return;
        }

        $waiting = $attempt->answers()->whereIn('status', ['uploaded'])->exists();

        if (! $waiting) {
            self::dispatch($attemptId);
        }
    }

    public function handle(SpeakingScorer $scorer, MockExamService $mockExams): void
    {
        $attempt = SpeakingAttempt::with('user')->find($this->attemptId);

        if (! $attempt || $attempt->status !== 'processing') {
            return;
        }

        if (! $attempt->answers()->where('status', 'transcribed')->exists()) {
            $attempt->forceFill(['status' => 'failed'])->save();

            return;
        }

        $scorer->score($attempt);

        if ($attempt->mockExam) {
            $mockExams->refresh($attempt->mockExam);
        }

        $attempt->user->notify(new ResultReady('speaking', $attempt->id, $attempt->overall_band));
    }

    public function failed(?Throwable $exception): void
    {
        SpeakingAttempt::whereKey($this->attemptId)->where('status', 'processing')->update(['status' => 'failed']);
    }
}
