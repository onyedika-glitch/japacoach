<?php

namespace Tests\Feature;

use App\Jobs\GenerateContent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_stats_report_completed_revenue_only(): void
    {
        Payment::factory()->create(['status' => 'completed', 'amount' => 4000, 'paid_at' => now()]);
        Payment::factory()->create(['status' => 'pending', 'amount' => 10000]);

        $this->actingAs(User::factory()->admin()->create())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('revenue_total', 4000)
            ->assertJsonPath('revenue_30d', 4000);
    }

    public function test_admin_can_grant_a_plan_and_find_users(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'chioma@example.com']);

        $this->actingAs($admin)->patchJson("/api/admin/users/{$user->id}", ['plan' => 'monthly', 'plan_expires_at' => now()->addDays(30)->toIso8601String()])->assertOk();
        $this->assertTrue($user->refresh()->isPremium());

        $this->getJson('/api/admin/users?search=CHIOMA')->assertJsonCount(1, 'data')->assertJsonPath('data.0.plan', 'monthly');
    }

    public function test_admin_cannot_remove_their_own_admin_access(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patchJson("/api/admin/users/{$admin->id}", ['is_admin' => false])->assertUnprocessable();
    }

    public function test_generation_queues_one_job_per_batch(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/admin/content/generate', ['type' => 'reading', 'count' => 3, 'test_type' => 'general'])
            ->assertStatus(202);

        Queue::assertPushed(GenerateContent::class, 3);
        Queue::assertPushed(GenerateContent::class, fn (GenerateContent $job) => $job->testType === 'general');
    }
}
