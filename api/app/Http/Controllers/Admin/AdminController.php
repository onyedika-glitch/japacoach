<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\GenerateContent;
use App\Jobs\RenderSectionAudio;
use App\Jobs\RenderSpeakingQuestionAudio;
use App\Models\Payment;
use App\Models\PracticeAttempt;
use App\Models\PracticeSection;
use App\Models\PracticeTest;
use App\Models\Review;
use App\Models\SpeakingAttempt;
use App\Models\SpeakingQuestion;
use App\Models\User;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $weekAgo = now()->subDays(7);
        $completed = Payment::where('status', 'completed');

        return response()->json([
            'users_total' => User::count(),
            'users_today' => User::where('created_at', '>=', now()->startOfDay())->count(),
            'premium_active' => User::where('plan', '!=', 'free')->where('plan_expires_at', '>', now())->count(),
            'revenue_total' => (float) (clone $completed)->sum('amount'),
            'revenue_30d' => (float) (clone $completed)->where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'currency' => config('japa.currency'),
            'reviews_pending' => Review::where('status', 'pending')->count(),
            'attempts_7d' => [
                'speaking' => SpeakingAttempt::where('created_at', '>=', $weekAgo)->count(),
                'writing' => WritingAttempt::where('created_at', '>=', $weekAgo)->count(),
                'reading' => PracticeAttempt::where('module', 'reading')->where('created_at', '>=', $weekAgo)->count(),
                'listening' => PracticeAttempt::where('module', 'listening')->where('created_at', '>=', $weekAgo)->count(),
            ],
            'content' => [
                'speaking_questions' => SpeakingQuestion::count(),
                'writing_tasks' => WritingTask::count(),
                'reading_tests' => PracticeTest::where('module', 'reading')->count(),
                'listening_tests' => PracticeTest::where('module', 'listening')->count(),
                'listening_missing_audio' => PracticeSection::whereNotNull('script')->whereNull('audio_path')->count(),
                'speaking_missing_audio' => SpeakingQuestion::whereNull('audio_path')->count(),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('email', 'ilike', "%{$search}%")
                ->orWhere('name', 'ilike', "%{$search}%")))
            ->withCount(['speakingAttempts', 'writingAttempts', 'practiceAttempts'])
            ->latest()
            ->paginate(25);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->isPremium() ? $user->plan : 'free',
                'plan_expires_at' => $user->plan_expires_at?->toIso8601String(),
                'is_admin' => $user->is_admin,
                'bonus_speaking_mocks' => $user->bonus_speaking_mocks,
                'created_at' => $user->created_at?->toIso8601String(),
                'attempts_count' => $user->speaking_attempts_count + $user->writing_attempts_count + $user->practice_attempts_count,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['sometimes', Rule::in(array_merge(['free'], array_keys(config('japa.plans'))))],
            'plan_expires_at' => ['sometimes', 'nullable', 'date'],
            'is_admin' => ['sometimes', 'boolean'],
            'bonus_speaking_mocks' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        if ($user->is($request->user()) && array_key_exists('is_admin', $validated) && ! $validated['is_admin']) {
            return response()->json(['message' => 'You cannot remove your own admin access.'], 422);
        }

        $user->forceFill($validated)->save();

        return response()->json(['message' => 'User updated.']);
    }

    public function payments(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'completed', 'failed'])]]);

        return PaymentResource::collection(
            Payment::with('user')
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->latest()
                ->paginate(25)
        );
    }

    public function reviews(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(Review::STATUSES)]]);

        $reviews = Review::with('user:id,name,email')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('updated_at')
            ->paginate(25);

        return response()->json([
            'data' => collect($reviews->items())->map(fn (Review $review): array => $review->toPublicArray() + [
                'status' => $review->status,
                'can_publish' => $review->can_publish,
                'user' => $review->user?->only(['id', 'name', 'email']),
                'updated_at' => $review->updated_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function updateReview(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(Review::STATUSES)]]);

        if ($validated['status'] === 'approved' && ! $review->can_publish) {
            return response()->json(['message' => 'This user did not allow their review to be published.'], 422);
        }

        $review->forceFill([
            'status' => $validated['status'],
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ])->save();

        return response()->json(['message' => 'Review updated.']);
    }

    public function content(): JsonResponse
    {
        return response()->json([
            'speaking_questions' => SpeakingQuestion::orderBy('part')->orderBy('topic')->get()->map(fn (SpeakingQuestion $q): array => [
                'id' => $q->id, 'part' => $q->part, 'topic' => $q->topic, 'prompt' => $q->prompt,
                'is_published' => $q->is_published, 'has_audio' => $q->audio_path !== null,
            ]),
            'writing_tasks' => WritingTask::orderBy('task_number')->latest('id')->get()->map(fn (WritingTask $t): array => [
                'id' => $t->id, 'task_number' => $t->task_number, 'test_type' => $t->test_type,
                'title' => $t->title, 'is_published' => $t->is_published,
            ]),
            'practice_tests' => PracticeTest::withCount('questions')
                ->withCount(['sections as missing_audio' => fn ($q) => $q->whereNotNull('script')->whereNull('audio_path')])
                ->latest('id')->get()->map(fn (PracticeTest $t): array => [
                    'id' => $t->id, 'module' => $t->module, 'test_type' => $t->test_type, 'title' => $t->title,
                    'is_published' => $t->is_published, 'question_count' => $t->questions_count, 'missing_audio' => $t->missing_audio,
                ]),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['speaking', 'writing', 'reading', 'listening', 'audio'])],
            'count' => ['nullable', 'integer', 'min:1', 'max:5'],
            'test_type' => ['nullable', Rule::in(['academic', 'general'])],
        ]);

        if ($validated['type'] === 'audio') {
            PracticeSection::whereNotNull('script')->whereNull('audio_path')->pluck('id')
                ->each(fn (int $id) => RenderSectionAudio::dispatch($id));
            SpeakingQuestion::whereNull('audio_path')->pluck('id')
                ->each(fn (int $id) => RenderSpeakingQuestionAudio::dispatch($id));

            return response()->json(['message' => 'Missing audio queued for rendering.'], 202);
        }

        for ($i = 0; $i < ($validated['count'] ?? 1); $i++) {
            GenerateContent::dispatch($validated['type'], $validated['test_type'] ?? 'academic');
        }

        return response()->json(['message' => 'Content generation queued. New items appear in a few minutes.'], 202);
    }

    public function updateContent(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate(['is_published' => ['required', 'boolean']]);

        $model = match ($type) {
            'speaking' => SpeakingQuestion::findOrFail($id),
            'writing' => WritingTask::findOrFail($id),
            'practice' => PracticeTest::findOrFail($id),
            default => abort(404),
        };

        $model->forceFill($validated)->save();

        return response()->json(['message' => 'Content updated.']);
    }
}
