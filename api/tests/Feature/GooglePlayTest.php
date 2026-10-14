<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GooglePlayController;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GooglePlayTest extends TestCase
{
    use RefreshDatabase;

    private const PURCHASE_URL = 'androidpublisher.googleapis.com/androidpublisher/v3/applications/com.japacoach.app/purchases/products/pass_monthly/tokens/tok123';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config([
            'services.google_play.package_name' => 'com.japacoach.app',
            'services.google_play.service_account' => base64_encode((string) json_encode([
                'client_email' => 'play@japa.iam.gserviceaccount.com',
                'private_key' => $pem,
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $purchase
     */
    private function fakeGoogle(array $purchase, int $consumeStatus = 204): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            self::PURCHASE_URL.':consume' => Http::response('', $consumeStatus),
            self::PURCHASE_URL => Http::response($purchase),
        ]);
    }

    private function verify(User $user): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/billing/google-play/verify', ['product_id' => 'pass_monthly', 'purchase_token' => 'tok123']);
    }

    public function test_a_verified_purchase_grants_the_plan_once_and_is_consumed(): void
    {
        $user = User::factory()->create();
        $this->fakeGoogle(['purchaseState' => 0, 'consumptionState' => 0, 'orderId' => 'GPA.1234', 'obfuscatedExternalAccountId' => GooglePlayController::accountId($user)]);

        $this->verify($user)->assertOk()->assertJsonPath('user.plan', 'monthly')->assertJsonPath('retry', false);
        $this->verify($user)->assertOk();

        $this->assertSame(1, Payment::count());
        $payment = Payment::first();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('GP_GPA.1234', $payment->reference);
        $this->assertTrue($user->refresh()->isPremium());
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $user->plan_expires_at->timestamp, 5);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), ':consume'));
    }

    public function test_pending_and_cancelled_purchases_grant_nothing(): void
    {
        $user = User::factory()->create();

        $this->fakeGoogle(['purchaseState' => 2]);
        $this->verify($user)->assertStatus(202)->assertJsonPath('status', 'pending');

        $this->assertSame(0, Payment::count());
        $this->assertFalse($user->refresh()->isPremium());
    }

    public function test_purchase_made_for_another_account_is_rejected(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $this->fakeGoogle(['purchaseState' => 0, 'consumptionState' => 0, 'orderId' => 'GPA.9', 'obfuscatedExternalAccountId' => GooglePlayController::accountId($buyer)]);

        $this->verify($other)->assertStatus(409);
        $this->assertFalse($other->refresh()->isPremium());
    }

    public function test_plan_stays_granted_when_consume_fails_and_retry_is_requested(): void
    {
        $user = User::factory()->create();
        $this->fakeGoogle(['purchaseState' => 0, 'consumptionState' => 0, 'orderId' => 'GPA.77'], consumeStatus: 500);

        $this->verify($user)->assertOk()->assertJsonPath('retry', true);
        $this->assertTrue($user->refresh()->isPremium());
    }

    public function test_unknown_products_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/billing/google-play/verify', ['product_id' => 'pass_lifetime', 'purchase_token' => 'x'])
            ->assertJsonValidationErrors('product_id');
    }
}
