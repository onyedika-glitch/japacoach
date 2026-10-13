<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\BachsService;
use App\Services\Payments\PaymentFulfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bachs redirects and webhooks. A payment is only ever fulfilled after Bachs' own API confirms it.
 */
class PaymentController extends Controller
{
    public function __construct(
        private BachsService $bachs,
        private PaymentFulfiller $fulfiller,
    ) {}

    public function callback(Request $request): RedirectResponse
    {
        $payment = Payment::where('reference', (string) $request->query('reference'))->first();

        if (! $payment) {
            return $this->toBilling('failed');
        }

        return $this->toBilling($this->reconcile($payment));
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! BachsService::verifySignature(
            $payload,
            $request->header('X-Bachs-Timestamp'),
            $request->header('X-Bachs-Signature'),
            config('services.bachs.webhook_secret'),
        )) {
            Log::warning('Rejected Bachs webhook with missing or invalid signature');

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $reference = data_get($data, 'data.reference')
            ?? data_get($data, 'data.client_reference_id')
            ?? data_get($data, 'data.metadata.reference')
            ?? data_get($data, 'reference');

        $payment = is_string($reference) ? Payment::where('reference', $reference)->first() : null;

        if ($payment) {
            $this->reconcile($payment);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Sync the payment with Bachs and fulfil it if paid.
     *
     * @return 'success'|'pending'|'failed'
     */
    private function reconcile(Payment $payment): string
    {
        if ($payment->status === 'completed') {
            return 'success';
        }

        if (! $payment->checkout_id) {
            return 'pending';
        }

        $remote = $this->bachs->fetchCheckoutStatus($payment->checkout_id);

        if ($remote === null) {
            return 'pending';
        }

        if ($remote['status'] === 'paid' && ($remote['reference'] === null || $remote['reference'] === $payment->reference)) {
            $this->fulfiller->fulfill($payment);

            return 'success';
        }

        if ($remote['status'] === 'failed') {
            $payment->update(['status' => 'failed']);

            return 'failed';
        }

        return 'pending';
    }

    private function toBilling(string $status): RedirectResponse
    {
        return redirect()->away(rtrim(config('japa.frontend_url'), '/')."/billing?status={$status}");
    }
}
