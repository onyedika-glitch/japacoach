<?php

namespace App\Models;

use Database\Factories\PracticeTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['module', 'test_type', 'title', 'duration_minutes', 'is_published'])]
class PracticeTest extends Model
{
    /** @use HasFactory<PracticeTestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PracticeTest>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Reading tests are split by Academic/General; listening tests are shared.
     *
     * @param  Builder<PracticeTest>  $query
     */
    public function scopeForTestType(Builder $query, string $testType): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('test_type')->orWhere('test_type', $testType));
    }

    /**
     * @return HasMany<PracticeSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PracticeSection::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<PracticeQuestion, PracticeSection, $this>
     */
    public function questions(): HasManyThrough
    {
        return $this->hasManyThrough(PracticeQuestion::class, PracticeSection::class);
    }

    /**
     * @return HasMany<PracticeAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class);
    }
}
