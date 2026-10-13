<?php

namespace App\Models;

use Database\Factories\PracticeQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['practice_section_id', 'number', 'type', 'prompt', 'options', 'answers', 'explanation'])]
class PracticeQuestion extends Model
{
    /** @use HasFactory<PracticeQuestionFactory> */
    use HasFactory;

    public const TYPES = ['mcq', 'tfng', 'ynng', 'gap', 'matching'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'options' => 'array',
            'answers' => 'array',
        ];
    }

    /**
     * Options shown to the candidate, including the fixed choices for TRUE/FALSE and YES/NO questions.
     *
     * @return list<array{key: string, text: string}>|null
     */
    public function displayOptions(): ?array
    {
        return match ($this->type) {
            'tfng' => [
                ['key' => 'TRUE', 'text' => 'TRUE'],
                ['key' => 'FALSE', 'text' => 'FALSE'],
                ['key' => 'NOT GIVEN', 'text' => 'NOT GIVEN'],
            ],
            'ynng' => [
                ['key' => 'YES', 'text' => 'YES'],
                ['key' => 'NO', 'text' => 'NO'],
                ['key' => 'NOT GIVEN', 'text' => 'NOT GIVEN'],
            ],
            default => $this->options,
        };
    }

    /**
     * @return BelongsTo<PracticeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(PracticeSection::class, 'practice_section_id');
    }
}
