<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PlanExpiringSoon;
use App\Notifications\StudyReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:send')]
#[Description('Email study reminders to inactive learners and warn users whose plan is about to end')]
class SendReminders extends Command
{
    public function handle(): int
    {
        $study = 0;
        $expiry = 0;

        // Learners with an upcoming test who have not practised for two days (but were active this month).
        User::where('email_reminders', true)
            ->whereDate('last_active_on', '<=', now()->subDays(2))
            ->whereDate('last_active_on', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->whereNull('test_date')->orWhereDate('test_date', '>=', today()))
            ->chunkById(200, function ($users) use (&$study): void {
                foreach ($users as $user) {
                    $daysToTest = $user->test_date ? (int) today()->diffInDays($user->test_date) : null;
                    $user->notify(new StudyReminder($daysToTest));
                    $study++;
                }
            });

        foreach ([3, 1] as $days) {
            User::where('plan', '!=', 'free')
                ->whereBetween('plan_expires_at', [now()->addDays($days - 1)->endOfDay(), now()->addDays($days)->endOfDay()])
                ->chunkById(200, function ($users) use ($days, &$expiry): void {
                    foreach ($users as $user) {
                        $user->notify(new PlanExpiringSoon($days));
                        $expiry++;
                    }
                });
        }

        $this->info("Sent {$study} study reminders and {$expiry} plan expiry warnings.");

        return self::SUCCESS;
    }
}
