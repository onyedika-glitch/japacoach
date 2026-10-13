<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExternalServiceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Payments\BachsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = collect(config('japa.plans'))->map(fn (array $plan, string $id): array => [
            'id' => $id,
            'name' => $plan['name'],
            'price' => $plan['price'],
            'days' => $plan['days'],
            'features' => $plan['features'],
            'highlight' => $plan['highlight'],
        ])->values();

        return response()->json([
            'currency' => config('japa.currency'),
            'plans' => $plans,
            'free_limits' => config('japa.limits.free'),
        ]);
    }

    public function checkout(Request $request, BachsService $bachs): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('japa.plans')))],
        ]);

        $user = $request->user();
        $plan = config("japa.plans.{$validated['plan']}");
        $reference = 'JC_'.strtoupper(Str::random(32));

        $payment = Payment::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'plan' => $validated['plan'],
            'amount' => $plan['price'],
            'currency' => config('japa.currency'),
            'status' => 'pending',
        ]);

        try {
            $checkout = $bachs->createCheckout(
                reference: $reference,
                amount: (float) $plan['price'],
                currency: config('japa.currency'),
                customerName: $user->name,
                customerEmail: $user->email,
                successUrl: route('payment.callback', ['reference' => $reference]),
                metadata: ['user_id' => $user->id, 'plan' => $validated['plan']],
            );
        } catch (ExternalServiceException $exception) {
            $payment->update(['status' => 'failed']);

            return response()->json(['message' => 'We could not start the payment. Please try again in a moment.'], 503);
        }

        $payment->update(['checkout_id' => $checkout['checkout_id']]);

        return response()->json(['checkout_url' => $checkout['checkout_url']]);
    }

    public function payments(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($request->user()->payments()->latest()->limit(50)->get());
    }
}
