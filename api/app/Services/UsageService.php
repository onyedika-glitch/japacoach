<?php

namespace App\Services;

use App\Exceptions\UsageLimitReachedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enforces per-plan usage limits over a rolling 30-day window.
 */
class UsageService
{
    public const FEATURES = ['speaking_mocks', 'speaking_drills', 'writing', 'reading', 'listening'];

    private const WINDOW_DAYS = 30;

    /**
     * @return array<string, int|null>
     */
    public function limitsFor(User $user): array
    {
        $plan = $user->isPremium() ? $user->plan : 'free';

        return config("japa.limits.{$plan}", config('japa.limits.free'));
    }

    public function used(User $user, string $feature): int
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        return match ($feature) {
            'speaking_mocks' => $user->speakingAttempts()->where('mode', 'full')->where('status', '!=', 'failed')->where('created_at', '>=', $since)->count(),
            'speaking_drills' => $user->speakingAttempts()->where('mode', '!=', 'full')->where('status', '!=', 'failed')->where('created_at', '>=', $since)->count(),
            'writing' => $user->writingAttempts()->whereNull('mock_exam_id')->where('status', '!=', 'failed')->where('created_at', '>=', $since)->count(),
            'reading', 'listening' => $user->practiceAttempts()->whereNull('mock_exam_id')->where('module', $feature)->where('created_at', '>=', $since)->count(),
        };
    }

    /**
     * @return array<string, array{used: int, limit: int|null}>
     */
    public function summary(User $user): array
    {
        $limits = $this->limitsFor($user);
        $summary = [];

        foreach (self::FEATURES as $feature) {
            $limit = $limits[$feature] ?? null;
            if ($feature === 'speaking_mocks' && $limit !== null) {
                $limit += $user->bonus_speaking_mocks;
            }
            $summary[$feature] = ['used' => $this->used($user, $feature), 'limit' => $limit];
        }

        return $summary;
    }

    /**
     * Ensure the user may start one more of the given feature, consuming a referral bonus mock if needed.
     *
     * @throws UsageLimitReachedException
     */
    public function authorize(User $user, string $feature): void
    {
        $limit = $this->limitsFor($user)[$feature] ?? null;

        if ($limit === null || $this->used($user, $feature) < $limit) {
            return;
        }

        if ($feature === 'speaking_mocks') {
            $consumed = DB::table('users')
                ->where('id', $user->id)
                ->where('bonus_speaking_mocks', '>', 0)
                ->decrement('bonus_speaking_mocks');

            if ($consumed > 0) {
                $user->refresh();

                return;
            }
        }

        throw new UsageLimitReachedException($feature);
    }
}
