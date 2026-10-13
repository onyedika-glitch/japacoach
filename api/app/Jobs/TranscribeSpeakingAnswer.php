<?php

namespace App\Jobs;

use App\Models\SpeakingAnswer;
use App\Services\Scoring\SpeakingMetrics;
use App\Services\Speech\SpeechClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transcribes one recorded answer on the speech queue, then triggers scoring once the whole attempt is ready.
 */
class TranscribeSpeakingAnswer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public int $answerId)
    {
        $this->onQueue('speech');
    }

    public function handle(SpeechClient $speech): void
    {
        $answer = SpeakingAnswer::find($this->answerId);

        if (! $answer || $answer->status !== 'uploaded' || ! $answer->audio_path) {
            return;
        }

        $result = $speech->transcribe(Storage::disk('local')->path($answer->audio_path));

        $answer->forceFill([
            'status' => 'transcribed',
            'transcript' => $result['text'],
            'words' => $result['words'],
            'duration_seconds' => $answer->duration_seconds ?: $result['duration'],
            'metrics' => SpeakingMetrics::fromWords($result['words'], $result['duration']),
        ])->save();

        ScoreSpeakingAttempt::dispatchIfReady($answer->speaking_attempt_id);
    }

    public function failed(?Throwable $exception): void
    {
        $answer = SpeakingAnswer::find($this->answerId);

        if ($answer) {
            $answer->forceFill(['status' => 'failed'])->save();
            ScoreSpeakingAttempt::dispatchIfReady($answer->speaking_attempt_id);
        }
    }
}
