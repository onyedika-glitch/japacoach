<?php

namespace App\Models;

use Database\Factories\PracticeAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'practice_test_id', 'mock_exam_id', 'module', 'status', 'answers', 'started_at'])]
class PracticeAttempt extends Model
{
    /** @use HasFactory<PracticeAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'band' => 'float',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PracticeTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(PracticeTest::class, 'practice_test_id');
    }

    /**
     * @return BelongsTo<MockExam, $this>
     */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }
}
