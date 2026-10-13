<?php

namespace App\Services\Scoring;

use App\Models\SpeakingAnswer;
use App\Models\SpeakingAttempt;
use App\Services\Ai\DeepSeekClient;

/**
 * Scores a speaking attempt from its transcripts and fluency metrics using the AI examiner.
 */
class SpeakingScorer
{
    public const CRITERIA = ['fluency_coherence', 'lexical_resource', 'grammatical_range_accuracy', 'pronunciation'];

    public function __construct(private DeepSeekClient $ai) {}

    public function score(SpeakingAttempt $attempt): SpeakingAttempt
    {
        $answers = $attempt->answers()->with('question')->where('status', 'transcribed')->get();
        $metrics = SpeakingMetrics::combine($answers->pluck('metrics')->filter()->values()->all());

        $result = $this->ai->json($this->systemPrompt(), $this->userPrompt($answers->all(), $metrics), 0.2, 5000);

        $scores = [];
        foreach (self::CRITERIA as $criterion) {
            $scores[$criterion] = [
                'band' => BandCalculator::normalize($result['scores'][$criterion]['band'] ?? 0),
                'comment' => (string) ($result['scores'][$criterion]['comment'] ?? ''),
            ];
        }

        $attempt->forceFill([
            'status' => 'scored',
            'scores' => $scores,
            'overall_band' => BandCalculator::fromCriteria(...array_column($scores, 'band')),
            'feedback' => [
                'summary' => (string) ($result['summary'] ?? ''),
                'strengths' => $this->stringList($result['strengths'] ?? []),
                'improvements' => $this->stringList($result['improvements'] ?? []),
                'corrections' => $this->pairs($result['corrections'] ?? [], ['original', 'corrected', 'explanation']),
                'better_answers' => $this->pairs($result['better_answers'] ?? [], ['question', 'answer']),
                'pronunciation_tips' => $this->pairs($result['pronunciation_tips'] ?? [], ['word', 'tip']),
                'metrics' => $metrics,
            ],
            'scored_at' => now(),
        ])->save();

        return $attempt;
    }

    private function systemPrompt(): string
    {
        $descriptors = BandDescriptors::SPEAKING;

        return <<<PROMPT
You are a strict but encouraging certified IELTS Speaking examiner. You assess Nigerian candidates preparing to study or work abroad.
You receive automatic speech-to-text transcripts of the candidate's answers plus measured fluency metrics. Transcripts may contain recognition errors; do not penalise obvious transcription glitches, but treat words flagged as unclear as possible pronunciation problems.
Mark against these band descriptors and be realistic — do not inflate scores. Very short or off-topic answers must score low.

{$descriptors}

Pronunciation must be estimated from the unclear-word list, speaking rate and pauses, since you cannot hear the audio; say so briefly in its comment.
Nigerian English features that do not impede understanding should not be heavily penalised.

Reply with JSON only, in this exact shape:
{
  "scores": {
    "fluency_coherence": {"band": 6.5, "comment": "2-3 sentences referring to the candidate's actual answers"},
    "lexical_resource": {"band": 6.0, "comment": "..."},
    "grammatical_range_accuracy": {"band": 6.0, "comment": "..."},
    "pronunciation": {"band": 6.0, "comment": "..."}
  },
  "summary": "3-4 sentence overall assessment addressed to the candidate as 'you'",
  "strengths": ["...", "..."],
  "improvements": ["specific, actionable advice", "..."],
  "corrections": [{"original": "exact phrase the candidate said", "corrected": "natural correct version", "explanation": "short reason"}],
  "better_answers": [{"question": "the question", "answer": "a band 7.5-8 model answer in the candidate's own situation, 60-120 words"}],
  "pronunciation_tips": [{"word": "word", "tip": "how to say it, e.g. stress and simple respelling"}]
}
Bands use half steps from 0 to 9. Give 3-6 strengths, 3-6 improvements, up to 8 corrections, a better answer for up to 3 of the weakest answers, and up to 6 pronunciation tips.
PROMPT;
    }

    /**
     * @param  list<SpeakingAnswer>  $answers
     * @param  array<string, mixed>  $metrics
     */
    private function userPrompt(array $answers, array $metrics): string
    {
        $lines = [];
        foreach ($answers as $answer) {
            $question = $answer->question;
            $prompt = $question->prompt;
            if ($question->cue_points) {
                $prompt .= ' (You should say: '.implode('; ', $question->cue_points).')';
            }
            $lines[] = "PART {$answer->part} — Examiner: {$prompt}\nCandidate ({$answer->duration_seconds}s): ".($answer->transcript ?: '[no speech detected]');
        }

        $unclear = implode(', ', $metrics['unclear_words']) ?: 'none';

        return "TRANSCRIPTS\n".implode("\n\n", $lines)
            ."\n\nMEASURED METRICS\nSpeaking rate: {$metrics['words_per_minute']} words per minute (natural fluent speech is about 120-160)"
            ."\nPauses longer than 1.5s: {$metrics['long_pauses']}"
            ."\nFiller words detected: {$metrics['filler_words']}"
            ."\nTotal speaking time: {$metrics['total_speaking_seconds']}s"
            ."\nWords the recogniser found unclear (possible pronunciation issues): {$unclear}";
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $items): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_string($item) ? trim($item) : '',
            is_array($items) ? $items : [],
        )));
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, string>>
     */
    private function pairs(mixed $items, array $keys): array
    {
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($keys as $key) {
                $row[$key] = trim((string) ($item[$key] ?? ''));
            }
            if ($row[$keys[0]] !== '') {
                $result[] = $row;
            }
        }

        return $result;
    }
}
