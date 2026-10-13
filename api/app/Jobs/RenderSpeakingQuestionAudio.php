<?php

namespace App\Jobs;

use App\Models\SpeakingQuestion;
use App\Services\Speech\SpeechClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-records the examiner reading a speaking question so tests never wait on text-to-speech.
 */
class RenderSpeakingQuestionAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $questionId)
    {
        $this->onQueue('speech');
    }

    public function handle(SpeechClient $speech): void
    {
        $question = SpeakingQuestion::find($this->questionId);

        if (! $question) {
            return;
        }

        $text = $question->part === 2
            ? "Now I'm going to give you a topic, and I'd like you to talk about it for one to two minutes. You have one minute to think about what you're going to say. {$question->prompt} You should say: ".implode(', ', $question->cue_points ?? []).'.'
            : $question->prompt;

        $path = "speaking/question-{$question->id}.mp3";
        Storage::disk('public')->put($path, $speech->synthesize([['voice' => config('japa.voices.examiner'), 'text' => $text]], 0.95));

        $question->forceFill(['audio_path' => $path])->save();
    }
}
