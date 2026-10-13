<?php

namespace App\Models;

use Database\Factories\SpeakingAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'speaking_attempt_id', 'speaking_question_id', 'part', 'position',
    'audio_path', 'duration_seconds', 'status', 'transcript', 'words', 'metrics',
])]
#[Hidden(['words'])]
class SpeakingAnswer extends Model
{
    /** @use HasFactory<SpeakingAnswerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'part' => 'integer',
            'position' => 'integer',
            'duration_seconds' => 'float',
            'words' => 'array',
            'metrics' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SpeakingAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(SpeakingAttempt::class, 'speaking_attempt_id');
    }

    /**
     * @return BelongsTo<SpeakingQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SpeakingQuestion::class, 'speaking_question_id');
    }
}
