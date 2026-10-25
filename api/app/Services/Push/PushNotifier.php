<?php

namespace App\Services\Push;

use App\Exceptions\ExternalServiceException;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\GoogleServiceAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends push notifications to users' phones through Firebase Cloud Messaging (HTTP v1).
 * Off until a service account key is configured (FCM_SERVICE_ACCOUNT, base64 JSON).
 */
class PushNotifier
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function enabled(): bool
    {
        return $this->account()?->projectId() !== null;
    }

    /**
     * Push to every phone of the user. Tokens Firebase reports as dead are removed.
     *
     * @return int How many phones accepted the message.
     */
    public function send(User $user, PushMessage $message): int
    {
        $account = $this->account();
        if (! $account?->projectId()) {
            return 0;
        }

        $tokens = $user->deviceTokens()->pluck('token');
        if ($tokens->isEmpty()) {
            return 0;
        }

        try {
            $accessToken = $account->accessToken(self::SCOPE);
        } catch (ExternalServiceException $exception) {
            Log::warning('FCM sign-in failed: '.$exception->getMessage());

            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)->timeout(10)
                    ->post("https://fcm.googleapis.com/v1/projects/{$account->projectId()}/messages:send", ['message' => [
                        'token' => $token,
                        'notification' => ['title' => $message->title, 'body' => Str::limit($message->body, 180)],
                        'data' => array_filter(['route' => $message->route]),
                        'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'japa_default']],
                    ]]);
            } catch (ConnectionException $exception) {
                Log::warning('FCM unreachable: '.$exception->getMessage());

                break;
            }

            if ($response->successful()) {
                $sent++;
            } elseif (in_array($response->json('error.status'), ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                DeviceToken::where('token', $token)->delete();
            } else {
                Log::warning('FCM send failed', ['status' => $response->status(), 'error' => $response->json('error.message')]);
            }
        }

        return $sent;
    }

    /**
     * The Firebase project's service account. Falls back to the Google Play key, which works when Firebase is
     * added to the same Google Cloud project and that account has the "Firebase Cloud Messaging API Admin" role.
     */
    private function account(): ?GoogleServiceAccount
    {
        return GoogleServiceAccount::fromBase64(config('services.fcm.service_account'))
            ?? GoogleServiceAccount::fromBase64(config('services.google_play.service_account'));
    }
}
