<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * Approved reviews for the landing page.
     */
    public function index(): JsonResponse
    {
        $reviews = Review::where('status', 'approved')
            ->where('can_publish', true)
            ->latest('approved_at')
            ->limit(12)
            ->get();

        return response()->json(['data' => $reviews->map->toPublicArray()])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function show(Request $request): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)->first();

        return response()->json(['review' => $review ? $this->ownArray($review) : null]);
    }

    /**
     * Create or edit the user's review. Any edit goes back to the approval queue.
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:600'],
            'display_name' => ['required', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:40'],
            'module' => ['nullable', Rule::in(Review::MODULES)],
            'can_publish' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $validated['comment'] = trim(strip_tags($validated['comment']));
        $validated['display_name'] = trim(strip_tags($validated['display_name']));
        $validated['location'] = filled($validated['location'] ?? null) ? trim(strip_tags($validated['location'])) : null;
        $validated['band'] = $this->latestBand($user, $validated['module'] ?? null);

        $review = $user->review()->firstOrNew();
        $review->fill($validated);
        $review->forceFill(['status' => 'pending', 'approved_at' => null])->save();

        return response()->json(['review' => $this->ownArray($review)], $review->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * The user's most recent estimated band in the module they are reviewing, so testimonials show real results.
     */
    private function latestBand(User $user, ?string $module): ?float
    {
        $band = match ($module) {
            'speaking' => $user->speakingAttempts()->where('status', 'scored')->latest('scored_at')->value('overall_band'),
            'writing' => $user->writingAttempts()->where('status', 'scored')->latest('scored_at')->value('overall_band'),
            'reading', 'listening' => $user->practiceAttempts()->where('module', $module)->where('status', 'submitted')->latest('submitted_at')->value('band'),
            default => null,
        };

        return $band === null ? null : (float) $band;
    }

    /**
     * @return array<string, mixed>
     */
    private function ownArray(Review $review): array
    {
        return $review->toPublicArray() + [
            'can_publish' => $review->can_publish,
            'status' => $review->status,
        ];
    }
}
