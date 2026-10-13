<?php

namespace App\Models;

use Database\Factories\SpeakingAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'mock_exam_id', 'mode', 'status'])]
class SpeakingAttempt extends Model
{
    /** @use HasFactory<SpeakingAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'feedback' => 'array',
            'overall_band' => 'float',
            'submitted_at' => 'datetime',
            'scored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MockExam, $this>
     */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }

    /**
     * One row per question asked, created when the attempt starts.
     *
     * @return HasMany<SpeakingAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SpeakingAnswer::class)->orderBy('position');
    }
}
