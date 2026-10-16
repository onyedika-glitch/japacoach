<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'is_admin' => $this->is_admin,
            'is_tutor' => $this->ownedCentres()->exists(),
            'test_type' => $this->test_type,
            'target_band' => $this->target_band,
            'test_date' => $this->test_date?->toDateString(),
            'email_reminders' => $this->email_reminders,
            'plan' => $this->isPremium() ? $this->plan : 'free',
            'plan_expires_at' => $this->isPremium() ? $this->plan_expires_at?->toIso8601String() : null,
            'is_premium' => $this->isPremium(),
            'referral_code' => $this->referral_code,
            'streak_days' => $this->streak_days,
        ];
    }
}
