<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bachs.secret_key' => 'sk_sandbox_test',
            'services.bachs.webhook_secret' => 'whsec_test',
            'japa.frontend_url' => 'https://app.test',
        ]);
    }

    public function test_checkout_creates_a_pending_payment_at_the_configured_price(): void
    {
        Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions' => Http::response([
            'checkout_url' => 'https://pay.bachs.io/c/abc', 'checkout_id' => 'cs_123',
        ])]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/billing/checkout', ['plan' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://pay.bachs.io/c/abc');

        $payment = Payment::first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('cs_123', $payment->checkout_id);
        $this->assertEquals(4000, $payment->amount);
        Http::assertSent(fn ($request) => $request['pricing']['amount'] === '4000.00' && $request['pricing']['currency'] === 'NGN');
    }

    public function test_callback_grants_plan_only_when_bachs_confirms_payment(): void
    {
        $payment = Payment::factory()->create();
        $this->fakeBachsStatus('completed', $payment->reference);

        $this->get("/payment/callback?reference={$payment->reference}")
            ->assertRedirect('https://app.test/billing?status=success');

        $this->assertSame('completed', $payment->refresh()->status);
        $this->assertTrue($payment->user->refresh()->isPremium());
        $this->assertSame('monthly', $payment->user->plan);
    }

    public function test_callback_ignores_a_forged_success_status_in_the_url(): void
    {
        $payment = Payment::factory()->create();
        $this->fakeBachsStatus('open', $payment->reference);

        $this->get("/payment/callback?reference={$payment->reference}&status=success")
            ->assertRedirect('https://app.test/billing?status=pending');

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertFalse($payment->user->refresh()->isPremium());
    }

    public function test_callback_does_not_log_the_visitor_in(): void
    {
        $payment = Payment::factory()->create();
        $this->fakeBachsStatus('completed', $payment->reference);

        $this->get("/payment/callback?reference={$payment->reference}");

        $this->assertGuest();
    }

    public function test_unsigned_webhooks_are_rejected(): void
    {
        $payment = Payment::factory()->create();

        $this->postJson('/api/webhooks/bachs', ['event' => 'charge.success', 'data' => ['reference' => $payment->reference, 'status' => 'success']])
            ->assertStatus(400);

        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_signed_webhook_still_requires_bachs_confirmation(): void
    {
        $payment = Payment::factory()->create();
        $this->fakeBachsStatus('open', $payment->reference);

        $this->sendSignedWebhook(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]])->assertOk();

        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_signed_webhook_fulfils_once_and_rewards_the_referrer(): void
    {
        $referrer = User::factory()->create();
        $buyer = User::factory()->create(['referred_by' => $referrer->id]);
        $payment = Payment::factory()->for($buyer)->create(['plan' => 'weekly']);
        $this->fakeBachsStatus('completed', $payment->reference);

        $this->sendSignedWebhook(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]])->assertOk();
        $this->sendSignedWebhook(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]])->assertOk();

        $buyer->refresh();
        $this->assertSame('weekly', $buyer->plan);
        $this->assertTrue($buyer->plan_expires_at->between(now()->addDays(6), now()->addDays(7)->addMinute()));
        $this->assertSame(2, $referrer->refresh()->bonus_speaking_mocks);
    }

    public function test_renewing_stacks_on_remaining_days(): void
    {
        $user = User::factory()->premium()->create(['plan_expires_at' => now()->addDays(10)]);
        $payment = Payment::factory()->for($user)->create(['plan' => 'monthly']);
        $this->fakeBachsStatus('completed', $payment->reference);

        $this->get("/payment/callback?reference={$payment->reference}");

        $this->assertTrue($user->refresh()->plan_expires_at->between(now()->addDays(39), now()->addDays(40)->addMinute()));
    }

    private function fakeBachsStatus(string $status, string $reference): void
    {
        Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/*' => Http::response([
            'status' => $status, 'reference' => $reference,
        ])]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendSignedWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $timestamp = (string) time();

        return $this->call('POST', '/api/webhooks/bachs', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BACHS_TIMESTAMP' => $timestamp,
            'HTTP_X_BACHS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
        ], $body);
    }
}
