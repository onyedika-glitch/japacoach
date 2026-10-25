<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushNotifier;
use Illuminate\Notifications\Notification;

/**
 * Notification channel for phone push. A notification opts in by listing this class in via()
 * and implementing toPush(User): PushMessage.
 */
class PushChannel
{
    public function __construct(private PushNotifier $push) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush') || ! $notifiable->deviceTokens()->exists()) {
            return;
        }

        /** @var PushMessage $message */
        $message = $notification->toPush($notifiable);
        $this->push->send($notifiable, $message);
    }
}
