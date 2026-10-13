<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $module,
        public int $attemptId,
        public ?float $band,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->email_reminders ? ['mail'] : [];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = rtrim(config('japa.frontend_url'), '/')."/{$this->module}/report/{$this->attemptId}";
        $module = ucfirst($this->module);
        $band = $this->band !== null ? number_format($this->band, 1) : '—';

        return (new MailMessage)
            ->subject("Your {$module} result: estimated band {$band}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your examiner has finished marking your {$this->module} practice. Your estimated band is {$band}.")
            ->line('Open your report to see exactly what to fix before your next attempt.')
            ->action('View my feedback', $url)
            ->line('Keep going — small daily practice adds up.');
    }
}
