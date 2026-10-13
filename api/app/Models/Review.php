<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's review of Japa Coach. Only reviews the user allowed us to publish, and an admin approved, appear on the site.
 */
#[Fillable(['rating', 'comment', 'display_name', 'location', 'module', 'band', 'can_publish'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    public const MODULES = ['speaking', 'writing', 'reading', 'listening'];

    public const STATUSES = ['pending', 'approved', 'hidden'];

    protected $attributes = [
        'status' => 'pending',
        'can_publish' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'band' => 'float',
            'can_publish' => 'boolean',
            'approved_at' => 'datetime',
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
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'display_name' => $this->display_name,
            'location' => $this->location,
            'module' => $this->module,
            'band' => $this->band,
        ];
    }
}
