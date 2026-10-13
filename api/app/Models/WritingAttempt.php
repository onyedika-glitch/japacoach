<?php

namespace App\Models;

use Database\Factories\WritingAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'writing_task_id', 'mock_exam_id', 'essay', 'word_count', 'time_spent_seconds', 'status'])]
class WritingAttempt extends Model
{
    /** @use HasFactory<WritingAttemptFactory> */
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
            'scored_at' => 'datetime',
        ];
    }

    public static function countWords(string $text): int
    {
        return preg_match_all("/[\p{L}\p{N}'’-]+/u", $text);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WritingTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(WritingTask::class, 'writing_task_id');
    }

    /**
     * @return BelongsTo<MockExam, $this>
     */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }
}
