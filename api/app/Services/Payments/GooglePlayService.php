<?php

namespace App\Services\Payments;

use App\Exceptions\ExternalServiceException;
use App\Services\GoogleServiceAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Play Developer API for one-time in-app products: look up a purchase and consume it.
 */
class GooglePlayService
{
    private const API = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications';

    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public function isConfigured(): bool
    {
        return filled(config('services.google_play.package_name')) && $this->account() !== null;
    }

    /**
     * @return array<string, mixed> The ProductPurchase resource (purchaseState, consumptionState, orderId, ...).
     */
    public function productPurchase(string $productId, string $purchaseToken): array
    {
        $response = $this->call(fn () => Http::withToken($this->account()->accessToken(self::SCOPE))
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
        $this->call(fn () => Http::withToken($this->account()->accessToken(self::SCOPE))
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

    private function account(): ?GoogleServiceAccount
    {
        return GoogleServiceAccount::fromBase64(config('services.google_play.service_account'));
    }
}
