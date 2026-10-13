<?php

namespace App\Models;

use Database\Factories\MockExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'test_type', 'status', 'listening_test_id', 'reading_test_id',
    'writing_task1_id', 'writing_task2_id',
])]
class MockExam extends Model
{
    /** @use HasFactory<MockExamFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bands' => 'array',
            'overall_band' => 'float',
            'completed_at' => 'datetime',
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
     * @return HasMany<PracticeAttempt, $this>
     */
    public function practiceAttempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class);
    }

    /**
     * @return HasMany<WritingAttempt, $this>
     */
    public function writingAttempts(): HasMany
    {
        return $this->hasMany(WritingAttempt::class);
    }

    /**
     * @return HasMany<SpeakingAttempt, $this>
     */
    public function speakingAttempts(): HasMany
    {
        return $this->hasMany(SpeakingAttempt::class);
    }
}
