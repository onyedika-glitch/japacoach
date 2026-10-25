<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'google_id', 'avatar_url',
    'test_type', 'target_band', 'test_date', 'email_reminders',
])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mirror the database defaults so new, unsaved-then-saved users behave the same as loaded ones.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
        'test_type' => 'academic',
        'email_reminders' => true,
        'plan' => 'free',
        'bonus_speaking_mocks' => 0,
        'referral_rewarded' => false,
        'streak_days' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->referral_code)) {
                $user->referral_code = self::generateReferralCode();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'target_band' => 'float',
            'test_date' => 'date',
            'email_reminders' => 'boolean',
            'plan_expires_at' => 'datetime',
            'referral_rewarded' => 'boolean',
            'last_active_on' => 'date',
        ];
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    public function isPremium(): bool
    {
        return $this->plan !== 'free'
            && $this->plan_expires_at !== null
            && $this->plan_expires_at->isFuture();
    }

    /**
     * Extend the user's premium access, stacking on top of any time they still have.
     */
    public function grantPlan(string $plan, int $days): void
    {
        $base = $this->isPremium() ? $this->plan_expires_at : now();

        $this->plan = $plan;
        $this->plan_expires_at = $base->copy()->addDays($days);
        $this->save();
    }

    /**
     * Record activity for today and update the daily practice streak.
     */
    public function touchStreak(): void
    {
        $today = now()->startOfDay();

        if ($this->last_active_on?->isSameDay($today)) {
            return;
        }

        $this->streak_days = $this->last_active_on?->isSameDay($today->copy()->subDay())
            ? $this->streak_days + 1
            : 1;
        $this->last_active_on = $today;
        $this->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * @return HasMany<SpeakingAttempt, $this>
     */
    public function speakingAttempts(): HasMany
    {
        return $this->hasMany(SpeakingAttempt::class);
    }

    /**
     * @return HasMany<WritingAttempt, $this>
     */
    public function writingAttempts(): HasMany
    {
        return $this->hasMany(WritingAttempt::class);
    }

    /**
     * @return HasMany<PracticeAttempt, $this>
     */
    public function practiceAttempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class);
    }

    /**
     * @return HasMany<MockExam, $this>
     */
    public function mockExams(): HasMany
    {
        return $this->hasMany(MockExam::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Phones registered for push notifications.
     *
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Centres (classes) this user has joined as a student.
     *
     * @return BelongsToMany<Centre, $this>
     */
    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class)->withTimestamps();
    }

    /**
     * Centres this user runs as a tutor.
     *
     * @return HasMany<Centre, $this>
     */
    public function ownedCentres(): HasMany
    {
        return $this->hasMany(Centre::class, 'owner_id');
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return HasOne<StudyPlan, $this>
     */
    public function studyPlan(): HasOne
    {
        return $this->hasOne(StudyPlan::class);
    }
}
