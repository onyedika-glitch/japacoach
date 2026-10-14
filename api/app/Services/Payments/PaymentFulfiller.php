<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants the purchased plan exactly once per payment and rewards the referrer on a first purchase.
 */
class PaymentFulfiller
{
    public function fulfill(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'completed') {
                return;
            }

            $locked->forceFill(['status' => 'completed', 'paid_at' => now()])->save();

            // The account was deleted before the payment finished: keep the record, nothing to grant.
            if ($locked->user_id === null) {
                return;
            }

            $user = User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $days = (int) config("japa.plans.{$locked->plan}.days", 30);
            $user->grantPlan($locked->plan, $days);

            if ($user->referred_by && ! $user->referral_rewarded) {
                User::whereKey($user->referred_by)->increment('bonus_speaking_mocks', (int) config('japa.referrals.referrer_bonus_mocks'));
                $user->forceFill(['referral_rewarded' => true])->save();
            }
        });

        $payment->refresh();
    }
}
