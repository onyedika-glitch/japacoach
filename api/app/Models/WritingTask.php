<?php

namespace App\Models;

use Database\Factories\WritingTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['task_number', 'test_type', 'title', 'prompt', 'visual', 'is_published'])]
class WritingTask extends Model
{
    /** @use HasFactory<WritingTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_number' => 'integer',
            'visual' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<WritingTask>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Tasks a candidate of the given test type can take (Task 2 essays are shared).
     *
     * @param  Builder<WritingTask>  $query
     */
    public function scopeForTestType(Builder $query, string $testType): void
    {
        $query->whereIn('test_type', [$testType, 'both']);
    }

    public function minWords(): int
    {
        return $this->task_number === 1 ? 150 : 250;
    }

    public function recommendedMinutes(): int
    {
        return $this->task_number === 1 ? 20 : 40;
    }

    /**
     * @return HasMany<WritingAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(WritingAttempt::class);
    }
}
