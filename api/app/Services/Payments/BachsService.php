<?php

namespace App\Services\Payments;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bachs checkout sessions: create a hosted checkout and verify its status server-side.
 */
class BachsService
{
    /**
     * Webhooks older than this many seconds are rejected to limit replay attacks.
     */
    private const SIGNATURE_TOLERANCE_SECONDS = 600;

    public function isConfigured(): bool
    {
        return filled(config('services.bachs.secret_key'));
    }

    /**
     * Create a hosted checkout session.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{checkout_url: string, checkout_id: string|null}
     */
    public function createCheckout(
        string $reference,
        float $amount,
        string $currency,
        string $customerName,
        string $customerEmail,
        string $successUrl,
        array $metadata = [],
    ): array {
        if (! $this->isConfigured()) {
            throw new ExternalServiceException('Bachs is not configured.');
        }

        try {
            $response = $this->request()->post('/v1/checkout-sessions', [
                'customer' => ['name' => $customerName, 'email' => $customerEmail],
                'pricing' => [
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => strtoupper($currency),
                ],
                'reference' => $reference,
                'success_url' => $successUrl,
                'metadata' => $metadata,
            ]);
        } catch (ConnectionException $exception) {
            throw new ExternalServiceException('Could not reach Bachs.', previous: $exception);
        }

        $checkoutUrl = $response->json('checkout_url');

        if (! $response->successful() || ! is_string($checkoutUrl)) {
            Log::error('Bachs checkout creation failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new ExternalServiceException('Bachs could not create a checkout session.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'checkout_id' => $response->json('checkout_id'),
        ];
    }

    /**
     * Ask Bachs for the real status of a checkout session.
     *
     * @return array{status: 'paid'|'pending'|'failed', reference: string|null}|null Null when Bachs could not be reached.
     */
    public function fetchCheckoutStatus(string $checkoutId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->request()->get('/v1/checkout-sessions/'.urlencode($checkoutId));
        } catch (ConnectionException $exception) {
            Log::warning('Bachs verification unreachable', ['checkout_id' => $checkoutId]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Bachs verification failed', ['checkout_id' => $checkoutId, 'status' => $response->status()]);

            return null;
        }

        $sessionStatus = strtolower((string) $response->json('status', ''));
        $paymentStatus = strtolower((string) $response->json('payment_status', ''));

        $status = match (true) {
            in_array($sessionStatus, ['completed', 'complete', 'succeeded', 'paid'], true),
            in_array($paymentStatus, ['succeeded', 'paid'], true) => 'paid',
            in_array($sessionStatus, ['open', 'pending', 'processing'], true) => 'pending',
            default => 'failed',
        };

        return ['status' => $status, 'reference' => $response->json('reference')];
    }

    /**
     * Verify a webhook's HMAC-SHA256 signature. Unsigned or stale webhooks are always rejected.
     */
    public static function verifySignature(string $rawBody, ?string $timestamp, ?string $signature, ?string $secret): bool
    {
        if (blank($signature) || blank($secret)) {
            return false;
        }

        if (filled($timestamp)) {
            if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
                return false;
            }

            return hash_equals(hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret), $signature)
                || hash_equals(hash_hmac('sha256', $timestamp.$rawBody, $secret), $signature);
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    private function request(): PendingRequest
    {
        $secretKey = (string) config('services.bachs.secret_key');
        $baseUrl = str_starts_with($secretKey, 'sk_sandbox_') ? 'https://sandbox-api.bachs.io' : 'https://api.bachs.io';

        return Http::withToken($secretKey)->baseUrl($baseUrl)->acceptJson()->timeout(30);
    }
}
