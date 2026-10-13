<?php

namespace App\Models;

use Database\Factories\SpeakingQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['part', 'topic', 'prompt', 'cue_points', 'audio_path', 'is_published'])]
class SpeakingQuestion extends Model
{
    /** @use HasFactory<SpeakingQuestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'part' => 'integer',
            'cue_points' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<SpeakingQuestion>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk('public')->url($this->audio_path) : null;
    }
}
