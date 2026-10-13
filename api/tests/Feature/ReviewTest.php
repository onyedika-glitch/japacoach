<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\WritingAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'rating' => 5,
            'comment' => 'The speaking feedback showed me exactly what to fix.',
            'display_name' => 'Chioma',
            'location' => 'Enugu',
            'can_publish' => true,
        ], $overrides);
    }

    public function test_user_can_leave_a_review_with_their_latest_band(): void
    {
        $user = User::factory()->create();
        WritingAttempt::factory()->for($user)->create(['status' => 'scored', 'overall_band' => 6.5, 'scored_at' => now()]);

        $this->actingAs($user)->putJson('/api/me/review', $this->payload(['module' => 'writing', 'band' => 9]))
            ->assertCreated()
            ->assertJsonPath('review.status', 'pending')
            ->assertJsonPath('review.band', 6.5);

        $this->getJson('/api/me/review')->assertJsonPath('review.display_name', 'Chioma');
    }

    public function test_editing_a_review_sends_it_back_for_approval(): void
    {
        $review = Review::factory()->approved()->create();

        $this->actingAs($review->user)->putJson('/api/me/review', $this->payload(['rating' => 4]))->assertOk();

        $this->assertSame('pending', $review->refresh()->status);
        $this->assertSame(1, Review::count());
    }

    public function test_only_approved_publishable_reviews_are_public(): void
    {
        Review::factory()->approved()->create(['display_name' => 'Tunde']);
        Review::factory()->create(['display_name' => 'Pending']);
        Review::factory()->approved()->create(['display_name' => 'Private', 'can_publish' => false]);

        $this->getJson('/api/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', 'Tunde')
            ->assertJsonMissingPath('data.0.user_id');
    }

    public function test_admin_approves_reviews_but_not_private_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $public = Review::factory()->create();
        $private = Review::factory()->create(['can_publish' => false]);

        $this->actingAs($admin)->patchJson("/api/admin/reviews/{$public->id}", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/admin/reviews/{$private->id}", ['status' => 'approved'])->assertUnprocessable();

        $this->assertSame('approved', $public->refresh()->status);
        $this->assertNotNull($public->approved_at);
        $this->getJson('/api/admin/reviews?status=pending')->assertJsonCount(1, 'data');
    }

    public function test_review_validation_and_access(): void
    {
        $this->putJson('/api/me/review', $this->payload())->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->putJson('/api/me/review', $this->payload(['rating' => 6, 'comment' => 'ok']))
            ->assertJsonValidationErrors(['rating', 'comment']);

        $this->getJson('/api/admin/reviews')->assertForbidden();
    }
}
