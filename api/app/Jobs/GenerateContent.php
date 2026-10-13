<?php

namespace App\Jobs;

use App\Models\SpeakingQuestion;
use App\Services\Content\ContentGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates one batch of practice content in the background (triggered from the admin panel or CLI).
 */
class GenerateContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public string $type,
        public string $testType = 'academic',
    ) {}

    public function handle(ContentGenerator $generator): void
    {
        match ($this->type) {
            'speaking' => $generator->speaking(),
            'writing' => $generator->writing(),
            'reading' => $generator->reading($this->testType),
            'listening' => $generator->listening()->sections->each(
                fn ($section) => RenderSectionAudio::dispatch($section->id)
            ),
        };

        if ($this->type === 'speaking') {
            SpeakingQuestion::whereNull('audio_path')->pluck('id')
                ->each(fn (int $id) => RenderSpeakingQuestionAudio::dispatch($id));
        }

        Log::info('Generated practice content', ['type' => $this->type, 'test_type' => $this->testType]);
    }
}
