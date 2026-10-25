<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $daysLeft) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', PushChannel::class];
    }

    public function toPush(User $notifiable): PushMessage
    {
        $when = $this->daysLeft <= 1 ? 'tomorrow' : "in {$this->daysLeft} days";

        return new PushMessage(
            "Your Japa Coach access ends {$when}",
            'Renew to keep practising. Any days you have left are added on top.',
            '/plans',
        );
    }

    public function toMail(User $notifiable): MailMessage
    {
        $when = $this->daysLeft <= 1 ? 'tomorrow' : "in {$this->daysLeft} days";

        return (new MailMessage)
            ->subject("Your Japa Coach access ends {$when}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your premium access ends {$when}. Renew now so your practice is not interrupted before your test.")
            ->action('Renew my plan', rtrim(config('japa.frontend_url'), '/').'/billing')
            ->line('Any days you have left are added on top when you renew.');
    }
}
