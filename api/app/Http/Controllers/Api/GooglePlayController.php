<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExternalServiceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\GooglePlayService;
use App\Services\Payments\PaymentFulfiller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * In-app purchases from the Android app. The app sends the purchase token; we verify it with Google,
 * grant the plan once, then consume the purchase so the same pass can be bought again.
 */
class GooglePlayController extends Controller
{
    public function __construct(private GooglePlayService $play, private PaymentFulfiller $fulfiller) {}

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', Rule::in(array_keys(config('japa.google_play_products')))],
            'purchase_token' => ['required', 'string', 'max:4096'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $productId = $validated['product_id'];
        $token = $validated['purchase_token'];
        $tokenKey = 'gp:'.hash('sha256', $token);

        $existing = Payment::where('checkout_id', $tokenKey)->first();
        if ($existing && $existing->user_id !== $user->id) {
            return response()->json(['message' => 'This purchase belongs to another account.'], 409);
        }
        if ($existing?->status === 'completed') {
            return $this->consumeAndRespond($user, $productId, $token);
        }

        try {
            $purchase = $this->play->productPurchase($productId, $token);
        } catch (ExternalServiceException) {
            return response()->json(['message' => 'We could not confirm your purchase with Google Play. Please try again in a moment.'], 503);
        }

        // obfuscatedExternalAccountId is set by the app to a hash of the user id when buying.
        $owner = $purchase['obfuscatedExternalAccountId'] ?? null;
        if ($owner !== null && $owner !== self::accountId($user)) {
            return response()->json(['message' => 'This purchase belongs to another account.'], 409);
        }

        $state = (int) ($purchase['purchaseState'] ?? -1);
        if ($state === 2) {
            return response()->json(['status' => 'pending', 'message' => 'Your payment is pending. Your plan starts as soon as Google Play confirms it.'], 202);
        }
        if ($state !== 0) {
            return response()->json(['message' => 'This purchase was cancelled or refunded.'], 422);
        }
        if ((int) ($purchase['consumptionState'] ?? 0) === 1) {
            return response()->json(['message' => 'This purchase has already been used.'], 409);
        }

        $plan = config("japa.google_play_products.{$productId}");
        try {
            $payment = $existing ?? Payment::create([
                'user_id' => $user->id,
                'reference' => 'GP_'.(filled($purchase['orderId'] ?? null) ? $purchase['orderId'] : substr($tokenKey, 3, 40)),
                'checkout_id' => $tokenKey,
                'plan' => $plan,
                'amount' => config("japa.plans.{$plan}.price"),
                'currency' => config('japa.currency'),
                'status' => 'pending',
                'metadata' => [
                    'provider' => 'google_play',
                    'product_id' => $productId,
                    'order_id' => $purchase['orderId'] ?? null,
                    'region' => $purchase['regionCode'] ?? null,
                    // purchaseType 0 = a license tester's test purchase (no money taken).
                    'test' => ($purchase['purchaseType'] ?? null) === 0,
                ],
            ]);
        } catch (UniqueConstraintViolationException) {
            // A parallel request for the same purchase got here first.
            $payment = Payment::where('checkout_id', $tokenKey)->firstOrFail();
        }

        $this->fulfiller->fulfill($payment);

        return $this->consumeAndRespond($user, $productId, $token);
    }

    /**
     * The id the app attaches to a purchase (obfuscatedAccountId) before starting it.
     */
    public function account(Request $request): JsonResponse
    {
        return response()->json(['account_id' => self::accountId($request->user())]);
    }

    /**
     * The value the app passes as obfuscatedAccountId, so a purchase can only be claimed by the buyer.
     */
    public static function accountId(User $user): string
    {
        return hash('sha256', 'japa-user-'.$user->id);
    }

    private function consumeAndRespond(User $user, string $productId, string $token): JsonResponse
    {
        try {
            $this->play->consume($productId, $token);
        } catch (ExternalServiceException $exception) {
            // The plan is already granted. The app retries so the purchase is acknowledged before Google's 3-day refund window.
            Log::warning('Google Play consume failed', ['user_id' => $user->id, 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Your plan is active. We are still confirming with Google Play.', 'user' => new UserResource($user->refresh()), 'retry' => true], 200);
        }

        return response()->json(['message' => 'Your plan is active.', 'user' => new UserResource($user->refresh()), 'retry' => false]);
    }
}
