<?php

namespace App\Models;

use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prepaid plan code. Redeeming it grants the plan's days and joins the voucher's centre, if any.
 */
#[Fillable(['code', 'plan', 'days', 'centre_id', 'batch', 'created_by'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * A code like "JC-7K2M-Q9PX".
     */
    public static function generateCode(): string
    {
        do {
            $code = 'JC-'.Centre::randomChars(4).'-'.Centre::randomChars(4);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Normalise what a user typed: case, spaces and missing dashes.
     */
    public static function normalise(string $input): string
    {
        $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';

        return strlen($raw) === 10 && str_starts_with($raw, 'JC')
            ? 'JC-'.substr($raw, 2, 4).'-'.substr($raw, 6, 4)
            : strtoupper(trim($input));
    }

    public function isRedeemed(): bool
    {
        return $this->redeemed_at !== null;
    }

    /**
     * @return BelongsTo<Centre, $this>
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }
}
