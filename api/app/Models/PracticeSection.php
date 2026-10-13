<?php

namespace App\Models;

use Database\Factories\PracticeSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['practice_test_id', 'position', 'title', 'instructions', 'body', 'script', 'audio_path'])]
class PracticeSection extends Model
{
    /** @use HasFactory<PracticeSectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'script' => 'array',
        ];
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk('public')->url($this->audio_path) : null;
    }

    /**
     * @return BelongsTo<PracticeTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(PracticeTest::class, 'practice_test_id');
    }

    /**
     * @return HasMany<PracticeQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PracticeQuestion::class)->orderBy('number');
    }
}
