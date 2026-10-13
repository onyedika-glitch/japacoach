<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Requests from the SPA origin get a session, exactly as in the browser.
        $this->withHeader('Referer', 'http://localhost:5173/');
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Obi',
            'email' => 'Ada@Example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.plan', 'free');
        $this->assertAuthenticated();
        $this->assertNotEmpty(User::first()->referral_code);
    }

    public function test_registering_with_a_referral_code_links_the_referrer_and_grants_a_bonus_mock(): void
    {
        $referrer = User::factory()->create();

        $this->postJson('/api/auth/register', [
            'name' => 'Tunde',
            'email' => 'tunde@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'referral_code' => strtolower($referrer->referral_code),
        ])->assertCreated();

        $user = User::where('email', 'tunde@example.com')->first();
        $this->assertSame($referrer->id, $user->referred_by);
        $this->assertSame(1, $user->bonus_speaking_mocks);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_guests_cannot_reach_the_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_profile_update_validates_target_band(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/me', ['target_band' => 6.3])->assertJsonValidationErrors('target_band');
        $this->actingAs($user)->patchJson('/api/me', ['target_band' => 7.5, 'test_type' => 'general'])
            ->assertOk()
            ->assertJsonPath('user.target_band', 7.5)
            ->assertJsonPath('user.test_type', 'general');
    }
}
