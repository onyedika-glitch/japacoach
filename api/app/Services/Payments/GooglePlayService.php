<?php

namespace App\Services\Payments;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Play Developer API for one-time in-app products: look up a purchase and consume it.
 * Authenticates as a service account (JWT bearer grant), so no extra SDK is needed.
 */
class GooglePlayService
{
    private const API = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications';

    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public function isConfigured(): bool
    {
        return filled(config('services.google_play.package_name')) && $this->credentials() !== null;
    }

    /**
     * @return array<string, mixed> The ProductPurchase resource (purchaseState, consumptionState, orderId, ...).
     */
    public function productPurchase(string $productId, string $purchaseToken): array
    {
        $response = $this->call(fn () => Http::withToken($this->accessToken())
            ->timeout(15)
            ->get($this->purchaseUrl($productId, $purchaseToken)));

        return $response->json() ?? [];
    }

    /**
     * Consume the purchase so the user can buy the same pass again. Consuming also acknowledges it,
     * which Google requires within 3 days or the purchase is refunded.
     */
    public function consume(string $productId, string $purchaseToken): void
    {
        $this->call(fn () => Http::withToken($this->accessToken())
            ->timeout(15)
            ->post($this->purchaseUrl($productId, $purchaseToken).':consume'));
    }

    private function purchaseUrl(string $productId, string $purchaseToken): string
    {
        return sprintf('%s/%s/purchases/products/%s/tokens/%s', self::API,
            rawurlencode((string) config('services.google_play.package_name')), rawurlencode($productId), rawurlencode($purchaseToken));
    }

    /**
     * @param  callable(): Response  $request
     */
    private function call(callable $request): Response
    {
        if (! $this->isConfigured()) {
            throw new ExternalServiceException('Google Play billing is not configured.');
        }

        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new ExternalServiceException('Could not reach Google Play.', previous: $exception);
        }

        if ($response->failed()) {
            Log::warning('Google Play API error', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new ExternalServiceException('Google Play rejected the request ('.$response->status().').');
        }

        return $response;
    }

    private function accessToken(): string
    {
        return Cache::remember('google_play_access_token', now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
            $now = time();
            $jwt = $this->signJwt([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            try {
                $response = Http::asForm()->timeout(15)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
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
    private function signJwt(array $claims, string $privateKey): string
    {
        $encode = fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $input = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode($claims));

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new ExternalServiceException('Could not sign the Google service account token.');
        }

        return $input.'.'.$encode($signature);
    }

    /**
     * The service account JSON key, stored base64-encoded in GOOGLE_PLAY_SERVICE_ACCOUNT.
     *
     * @return array{client_email: string, private_key: string, token_uri?: string}|null
     */
    private function credentials(): ?array
    {
        $encoded = config('services.google_play.service_account');
        if (blank($encoded)) {
            return null;
        }

        $json = json_decode((string) base64_decode((string) $encoded, true), true);

        return is_array($json) && isset($json['client_email'], $json['private_key']) ? $json : null;
    }
}
