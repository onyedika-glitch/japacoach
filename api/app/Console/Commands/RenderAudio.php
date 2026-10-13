<?php

namespace App\Console\Commands;

use App\Jobs\RenderSectionAudio;
use App\Jobs\RenderSpeakingQuestionAudio;
use App\Models\PracticeSection;
use App\Models\SpeakingQuestion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audio:render {--force : Re-render audio that already exists}')]
#[Description('Queue Kokoro rendering for listening sections and examiner questions without audio')]
class RenderAudio extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $sections = PracticeSection::whereNotNull('script')->when(! $force, fn ($q) => $q->whereNull('audio_path'))->pluck('id');
        $questions = SpeakingQuestion::query()->when(! $force, fn ($q) => $q->whereNull('audio_path'))->pluck('id');

        $sections->each(fn (int $id) => RenderSectionAudio::dispatch($id));
        $questions->each(fn (int $id) => RenderSpeakingQuestionAudio::dispatch($id));

        $this->info("Queued {$sections->count()} listening sections and {$questions->count()} examiner questions on the speech queue.");

        return self::SUCCESS;
    }
}
