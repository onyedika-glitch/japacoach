<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudyReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $daysToTest) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->email_reminders ? ['mail', PushChannel::class] : [];
    }

    public function toPush(User $notifiable): PushMessage
    {
        return new PushMessage(
            $this->daysToTest !== null ? "{$this->daysToTest} days to your IELTS" : 'Time for a quick practice',
            $notifiable->streak_days > 1
                ? "You're on a {$notifiable->streak_days}-day streak. A 10-minute speaking drill keeps it going."
                : 'A 10-minute speaking drill today keeps your fluency sharp.',
            '/speaking',
        );
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->daysToTest !== null ? "{$this->daysToTest} days to your IELTS. Practise today!" : 'Keep your IELTS practice going')
            ->greeting("Hi {$notifiable->name},");

        if ($notifiable->streak_days > 1) {
            $message->line("You were on a {$notifiable->streak_days}-day streak. Don't break it now!");
        }

        return $message
            ->line('A 10-minute speaking drill today keeps your fluency sharp.')
            ->action('Practise now', rtrim(config('japa.frontend_url'), '/').'/dashboard')
            ->line('You can turn these reminders off in your settings.');
    }
}
