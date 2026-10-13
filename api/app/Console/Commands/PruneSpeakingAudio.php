<?php

namespace App\Console\Commands;

use App\Models\SpeakingAnswer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('speaking:prune-audio')]
#[Description('Delete recorded answers older than the retention period (transcripts are kept)')]
class PruneSpeakingAudio extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('japa.speaking.audio_retention_days'));
        $deleted = 0;

        SpeakingAnswer::whereNotNull('audio_path')
            ->where('updated_at', '<', $cutoff)
            ->whereIn('status', ['transcribed', 'failed'])
            ->chunkById(200, function ($answers) use (&$deleted): void {
                foreach ($answers as $answer) {
                    Storage::disk('local')->delete($answer->audio_path);
                    $answer->forceFill(['audio_path' => null])->save();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} recordings.");

        return self::SUCCESS;
    }
}
