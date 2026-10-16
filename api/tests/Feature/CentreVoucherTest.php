<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WritingAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentreVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_redeeming_a_voucher_grants_days_once_and_joins_its_centre(): void
    {
        $centre = Centre::factory()->create(['name' => 'Tractrain']);
        $voucher = Voucher::factory()->create(['centre_id' => $centre->id, 'plan' => 'monthly', 'days' => 30]);
        $student = User::factory()->create();

        $typed = strtolower(str_replace('-', ' ', $voucher->code)); // e.g. "jc 7k2m q9px"
        $this->actingAs($student)->postJson('/api/vouchers/redeem', ['code' => $typed])
            ->assertOk()
            ->assertJsonPath('user.plan', 'monthly')
            ->assertJsonPath('message', 'Monthly plan added: 30 days of full access. You joined Tractrain.');

        $this->assertTrue($student->refresh()->centres->contains($centre));
        $this->assertSame($student->id, $voucher->refresh()->redeemed_by);

        $this->actingAs(User::factory()->create())->postJson('/api/vouchers/redeem', ['code' => $voucher->code])
            ->assertJsonValidationErrors(['code' => 'already been used']);
    }

    public function test_voucher_days_stack_on_an_active_plan(): void
    {
        $student = User::factory()->premium('monthly')->create(['plan_expires_at' => now()->addDays(10)]);
        $voucher = Voucher::factory()->create(['plan' => 'monthly', 'days' => 30]);

        $this->actingAs($student)->postJson('/api/vouchers/redeem', ['code' => $voucher->code])->assertOk();

        $this->assertEqualsWithDelta(now()->addDays(40)->timestamp, $student->refresh()->plan_expires_at->timestamp, 5);
    }

    public function test_invalid_codes_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/vouchers/redeem', ['code' => 'JC-NOPE-NOPE'])
            ->assertJsonValidationErrors('code');
    }

    public function test_students_join_and_leave_a_class_with_its_code(): void
    {
        $centre = Centre::factory()->create(['name' => 'English Den']);
        $student = User::factory()->create();

        $this->actingAs($student)->postJson('/api/centres/join', ['code' => strtolower($centre->join_code)])->assertOk();
        $this->getJson('/api/me/centres')->assertJsonPath('data.0.name', 'English Den');

        $this->deleteJson("/api/centres/{$centre->id}/leave")->assertOk();
        $this->getJson('/api/me/centres')->assertJsonCount(0, 'data');

        $this->actingAs($centre->owner)->postJson('/api/centres/join', ['code' => $centre->join_code])->assertJsonValidationErrors('code');
    }

    public function test_tutors_see_only_their_own_students_with_bands(): void
    {
        $tutor = User::factory()->create();
        $centre = Centre::factory()->for($tutor, 'owner')->create();
        $student = User::factory()->create(['name' => 'Chioma']);
        $centre->students()->attach($student);
        WritingAttempt::factory()->for($student)->create(['status' => 'scored', 'overall_band' => 6.5, 'scored_at' => now()]);
        $outsider = User::factory()->create();
        Centre::factory()->create()->students()->attach($outsider);

        $this->actingAs($tutor)->getJson('/api/tutor')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.students')
            ->assertJsonPath('data.0.students.0.name', 'Chioma')
            ->assertJsonPath('data.0.students.0.latest.writing', 6.5);

        $this->getJson('/api/me')->assertJsonPath('user.is_tutor', true);
        $this->actingAs($student)->getJson('/api/tutor')->assertForbidden();
    }

    public function test_admin_creates_centres_and_voucher_batches(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'owner@tractrain.com']);

        $this->actingAs($admin)->postJson('/api/admin/centres', ['name' => 'Tractrain Lagos', 'owner_email' => 'Owner@Tractrain.com'])
            ->assertCreated()
            ->assertJsonPath('centre.join_code', fn (string $code) => str_starts_with($code, 'TRACT-'));
        $this->postJson('/api/admin/centres', ['name' => 'X', 'owner_email' => 'nobody@example.com'])->assertJsonValidationErrors('owner_email');

        $centre = Centre::first();
        $this->postJson('/api/admin/vouchers', ['plan' => 'weekly', 'quantity' => 5, 'centre_id' => $centre->id])
            ->assertCreated()
            ->assertJsonCount(5, 'codes');

        $this->assertSame(5, $centre->vouchers()->where('days', 7)->count());
        $this->getJson('/api/admin/centres')->assertJsonPath('data.0.vouchers_count', 5);

        $redeemed = Voucher::first();
        $redeemed->forceFill(['redeemed_by' => $admin->id, 'redeemed_at' => now()])->save();
        $this->deleteJson("/api/admin/vouchers/{$redeemed->id}")->assertUnprocessable();

        $this->actingAs(User::factory()->create())->postJson('/api/admin/vouchers', ['plan' => 'weekly', 'quantity' => 1])->assertForbidden();
    }
}
