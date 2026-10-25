<?php

namespace App\Services;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Signs in to Google APIs as a service account (JWT bearer grant) without an extra SDK.
 * Used for the Google Play Developer API and Firebase Cloud Messaging.
 */
class GoogleServiceAccount
{
    /**
     * @param  array{client_email: string, private_key: string, project_id?: string, token_uri?: string}  $key
     */
    private function __construct(private array $key) {}

    /**
     * From a base64-encoded service account JSON key (how keys are stored in .env). Null if missing or invalid.
     */
    public static function fromBase64(?string $encoded): ?self
    {
        if (blank($encoded)) {
            return null;
        }

        $key = json_decode((string) base64_decode((string) $encoded, true), true);

        return is_array($key) && isset($key['client_email'], $key['private_key']) ? new self($key) : null;
    }

    public function projectId(): ?string
    {
        return $this->key['project_id'] ?? null;
    }

    public function accessToken(string $scope): string
    {
        $cacheKey = 'google_sa_token:'.md5($this->key['client_email'].'|'.$scope);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($scope): string {
            $tokenUri = $this->key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $jwt = $this->signJwt([
                'iss' => $this->key['client_email'],
                'scope' => $scope,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            try {
                $response = Http::asForm()->timeout(15)->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
            } catch (ConnectionException $exception) {
                throw new ExternalServiceException('Could not reach Google sign-in.', previous: $exception);
            }

            $token = $response->json('access_token');
            if ($response->failed() || ! is_string($token)) {
                throw new ExternalServiceException('Google rejected the service account credentials.');
            }

            return $token;
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $claims): string
    {
        $encode = fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $input = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode($claims));

        if (! openssl_sign($input, $signature, $this->key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new ExternalServiceException('Could not sign the Google service account token.');
        }

        return $input.'.'.$encode($signature);
    }
}
