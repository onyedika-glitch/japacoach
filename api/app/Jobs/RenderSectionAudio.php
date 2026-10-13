<?php

namespace App\Jobs;

use App\Models\PracticeSection;
use App\Services\Speech\SpeechClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a listening section's script to MP3 with a distinct Kokoro voice per speaker.
 */
class RenderSectionAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public int $sectionId)
    {
        $this->onQueue('speech');
    }

    public function handle(SpeechClient $speech): void
    {
        $section = PracticeSection::find($this->sectionId);

        if (! $section || ! $section->script) {
            return;
        }

        $voices = config('japa.voices.listening');
        // Rotate the voice list per section so recordings do not all sound the same.
        $offset = $section->id % count($voices);
        $assigned = [];
        $lines = [];

        foreach ($section->script as $line) {
            $speaker = $line['speaker'];
            if (! isset($assigned[$speaker])) {
                $assigned[$speaker] = $this->voiceFor($speaker, $voices, $offset + count($assigned), array_values($assigned));
            }
            $lines[] = ['voice' => $assigned[$speaker], 'text' => $line['text']];
        }

        $path = "listening/{$section->practice_test_id}/section-{$section->position}-{$section->id}.mp3";
        Storage::disk('public')->put($path, $speech->synthesize($lines));

        $section->forceFill(['audio_path' => $path])->save();
    }

    /**
     * Match obviously gendered speaker labels to a voice of that gender, avoiding voices already in use.
     *
     * @param  list<string>  $voices
     * @param  list<string>  $taken
     */
    private function voiceFor(string $speaker, array $voices, int $index, array $taken): string
    {
        $label = strtolower($speaker);
        $wantsFemale = (bool) preg_match('/\b(woman|girl|female|mrs|ms|miss|she|lady)\b/', $label);
        $wantsMale = (bool) preg_match('/\b(man|boy|male|mr|he|gentleman)\b/', $label);

        $candidates = array_values(array_filter($voices, function (string $voice) use ($wantsFemale, $wantsMale, $taken): bool {
            $female = str_starts_with($voice, 'bf_') || str_starts_with($voice, 'af_');

            return ! in_array($voice, $taken, true)
                && (! $wantsFemale || $female)
                && (! $wantsMale || ! $female);
        }));

        $pool = $candidates ?: $voices;

        return $pool[$index % count($pool)];
    }
}
