<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_a_token_that_authenticates_api_calls(): void
    {
        $token = $this->postJson('/api/mobile/register', [
            'name' => 'Ada Obi',
            'email' => 'Ada@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'Pixel 8',
        ])->assertCreated()->assertJsonPath('user.email', 'ada@example.com')->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.name', 'Ada Obi');
    }

    public function test_register_saves_the_chosen_test_type(): void
    {
        $this->postJson('/api/mobile/register', [
            'name' => 'Bola', 'email' => 'bola@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
            'test_type' => 'general',
        ])->assertCreated()->assertJsonPath('user.test_type', 'general');

        $this->postJson('/api/mobile/register', [
            'name' => 'Ada', 'email' => 'ada2@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
            'test_type' => 'medical',
        ])->assertJsonValidationErrors('test_type');
    }

    public function test_login_rejects_wrong_password_and_accepts_right_one(): void
    {
        User::factory()->create(['email' => 'tunde@example.com', 'password' => 'secret-pass']);

        $this->postJson('/api/mobile/login', ['email' => 'tunde@example.com', 'password' => 'nope'])->assertJsonValidationErrors('email');
        $this->postJson('/api/mobile/login', ['email' => 'tunde@example.com', 'password' => 'secret-pass'])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_google_sign_in_creates_a_user_from_a_verified_token(): void
    {
        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'web-client.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => 'google-123',
            'email' => 'chioma@gmail.com',
            'email_verified' => 'true',
            'name' => 'Chioma N',
        ])]);

        $this->postJson('/api/mobile/google', ['id_token' => 'abc'])->assertOk()->assertJsonPath('user.email', 'chioma@gmail.com');

        $this->assertDatabaseHas('users', ['email' => 'chioma@gmail.com', 'google_id' => 'google-123']);
    }

    public function test_google_sign_in_rejects_tokens_for_other_apps(): void
    {
        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'someone-else.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'sub' => 'google-123',
            'email' => 'chioma@gmail.com',
            'email_verified' => 'true',
        ])]);

        $this->postJson('/api/mobile/google', ['id_token' => 'abc'])->assertJsonValidationErrors('id_token');
        $this->assertDatabaseMissing('users', ['email' => 'chioma@gmail.com']);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user)->create(['status' => 'completed']);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/me')->assertJsonValidationErrors('confirm');
        $this->withToken($token)->deleteJson('/api/me', ['confirm' => 'DELETE'])->assertOk();

        $this->assertModelMissing($user);
        $this->assertNull($payment->refresh()->user_id, 'Payment records are kept, unlinked.');
    }
}
