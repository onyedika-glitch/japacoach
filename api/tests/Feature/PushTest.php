<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\ResultReady;
use App\Notifications\StudyReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'fcm.googleapis.com/v1/projects/japa-push/messages:send';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.fcm.service_account' => base64_encode((string) json_encode([
            'client_email' => 'push@japa-push.iam.gserviceaccount.com',
            'private_key' => $pem,
            'project_id' => 'japa-push',
        ]))]);
    }

    public function test_phones_register_and_move_between_accounts(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson('/api/me/devices', ['token' => 'phone-1'])->assertOk();
        $this->actingAs($second)->postJson('/api/me/devices', ['token' => 'phone-1'])->assertOk();

        $this->assertSame(1, DeviceToken::count());
        $this->assertSame($second->id, DeviceToken::first()->user_id);

        $this->deleteJson('/api/me/devices', ['token' => 'phone-1'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_mobile_logout_unregisters_the_phone(): void
    {
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'phone-1']);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/mobile/logout', ['device_token' => 'phone-1'])->assertOk();

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_result_ready_is_pushed_with_the_report_route_and_dead_tokens_are_removed(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test']),
            self::SEND_URL => Http::sequence()
                ->push(['name' => 'projects/japa-push/messages/1'])
                ->push(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);
        $user = User::factory()->create(['email_reminders' => false]);
        $user->deviceTokens()->createMany([['token' => 'live'], ['token' => 'dead']]);

        $user->notifyNow(new ResultReady('speaking', 42, 6.5));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request['message']['data']['route'] === '/speaking/report/42'
            && str_contains($request['message']['notification']['body'], '6.5'));
        $this->assertSame(['live'], $user->deviceTokens()->pluck('token')->all());
    }

    public function test_reminders_respect_the_reminders_setting(): void
    {
        Http::fake();
        $user = User::factory()->create(['email_reminders' => false]);
        $user->deviceTokens()->create(['token' => 'phone-1']);

        $user->notifyNow(new StudyReminder(30));

        Http::assertNothingSent();
    }

    public function test_admin_test_push_explains_missing_phone(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/admin/push/test')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'No phone registered'));
    }
}
