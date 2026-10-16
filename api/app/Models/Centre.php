<?php

namespace App\Models;

use Database\Factories\CentreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A tutor's class or prep centre. Members' scores are visible to the owner.
 */
#[Fillable(['name', 'owner_id'])]
class Centre extends Model
{
    /** @use HasFactory<CentreFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Centre $centre): void {
            if (empty($centre->join_code)) {
                $centre->join_code = self::generateJoinCode($centre->name);
            }
        });
    }

    /**
     * A readable code like "TRACT-7K2M": the first letters of the name plus a random part.
     */
    public static function generateJoinCode(string $name): string
    {
        $prefix = substr(preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name))) ?: 'CLASS', 0, 5);

        do {
            $code = $prefix.'-'.self::randomChars(4);
        } while (self::where('join_code', $code)->exists());

        return $code;
    }

    /**
     * Random characters without look-alikes (no 0/O, 1/I/L) so codes are easy to read aloud and type.
     */
    public static function randomChars(int $length): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        return collect(range(1, $length))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
