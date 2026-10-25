<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\GenerateContent;
use App\Jobs\RenderSectionAudio;
use App\Jobs\RenderSpeakingQuestionAudio;
use App\Models\Centre;
use App\Models\Payment;
use App\Models\PracticeAttempt;
use App\Models\PracticeSection;
use App\Models\PracticeTest;
use App\Models\Review;
use App\Models\SpeakingAttempt;
use App\Models\SpeakingQuestion;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use App\Services\Push\PushMessage;
use App\Services\Push\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $weekAgo = now()->subDays(7);
        // Google Play test purchases (license testers) move no money.
        $completed = Payment::where('status', 'completed')
            ->where(fn ($query) => $query->whereNull('metadata->test')->orWhere('metadata->test', false));

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

    public function centres(): JsonResponse
    {
        $centres = Centre::with('owner:id,name,email')
            ->withCount(['students', 'vouchers', 'vouchers as vouchers_redeemed_count' => fn ($q) => $q->whereNotNull('redeemed_at')])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $centres->map(fn (Centre $c): array => [
            'id' => $c->id,
            'name' => $c->name,
            'join_code' => $c->join_code,
            'owner' => $c->owner?->only(['id', 'name', 'email']),
            'students_count' => $c->students_count,
            'vouchers_count' => $c->vouchers_count,
            'vouchers_redeemed_count' => $c->vouchers_redeemed_count,
            'created_at' => $c->created_at?->toIso8601String(),
        ])]);
    }

    public function storeCentre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email'],
        ]);

        $owner = User::where('email', strtolower($validated['owner_email']))->first();
        if (! $owner) {
            throw ValidationException::withMessages(['owner_email' => 'No account uses that email. Ask the tutor to sign up first.']);
        }

        $centre = Centre::create(['name' => $validated['name'], 'owner_id' => $owner->id]);

        return response()->json(['message' => "Centre created. Join code: {$centre->join_code}", 'centre' => ['id' => $centre->id, 'join_code' => $centre->join_code]], 201);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $request->validate([
            'centre_id' => ['nullable', 'integer'],
            'batch' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['unused', 'redeemed'])],
        ]);

        $vouchers = Voucher::with(['centre:id,name', 'redeemer:id,name,email'])
            ->when($request->filled('centre_id'), fn ($q) => $q->where('centre_id', $request->integer('centre_id')))
            ->when($request->filled('batch'), fn ($q) => $q->where('batch', $request->query('batch')))
            ->when($request->query('status') === 'unused', fn ($q) => $q->whereNull('redeemed_at'))
            ->when($request->query('status') === 'redeemed', fn ($q) => $q->whereNotNull('redeemed_at'))
            ->latest('id')
            ->paginate(100);

        return response()->json([
            'data' => collect($vouchers->items())->map(fn (Voucher $v): array => [
                'id' => $v->id,
                'code' => $v->code,
                'plan' => $v->plan,
                'days' => $v->days,
                'batch' => $v->batch,
                'centre' => $v->centre?->name,
                'redeemed_by' => $v->redeemer?->only(['name', 'email']),
                'redeemed_at' => $v->redeemed_at?->toIso8601String(),
                'created_at' => $v->created_at?->toIso8601String(),
            ]),
            'batches' => Voucher::query()->select('batch')->distinct()->orderBy('batch')->pluck('batch'),
            'meta' => ['current_page' => $vouchers->currentPage(), 'last_page' => $vouchers->lastPage(), 'total' => $vouchers->total()],
        ]);
    }

    public function generateVouchers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('japa.plans')))],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'centre_id' => ['nullable', 'integer', 'exists:centres,id'],
            'batch' => ['nullable', 'string', 'max:80'],
        ]);

        $centre = isset($validated['centre_id']) ? Centre::find($validated['centre_id']) : null;
        $batch = filled($validated['batch'] ?? null)
            ? $validated['batch']
            : Str::slug(($centre?->name ?? 'general').'-'.$validated['plan'].'-'.now()->format('Y-m-d-Hi'));

        $codes = collect(range(1, $validated['quantity']))->map(fn () => Voucher::create([
            'code' => Voucher::generateCode(),
            'plan' => $validated['plan'],
            'days' => (int) config("japa.plans.{$validated['plan']}.days"),
            'centre_id' => $centre?->id,
            'batch' => $batch,
            'created_by' => $request->user()->id,
        ])->code);

        return response()->json(['message' => "{$codes->count()} codes created.", 'batch' => $batch, 'codes' => $codes], 201);
    }

    public function destroyVoucher(Voucher $voucher): JsonResponse
    {
        if ($voucher->isRedeemed()) {
            return response()->json(['message' => 'Redeemed codes cannot be deleted.'], 422);
        }

        $voucher->delete();

        return response()->json(['message' => 'Code deleted.']);
    }

    /**
     * Send a test push to the admin's own phones, to check the Firebase setup end to end.
     */
    public function testPush(Request $request, PushNotifier $push): JsonResponse
    {
        if (! $push->enabled()) {
            return response()->json(['message' => 'Push is not configured on the server yet (FCM_SERVICE_ACCOUNT).'], 422);
        }

        $devices = $request->user()->deviceTokens()->count();
        if ($devices === 0) {
            return response()->json(['message' => 'No phone registered. Sign in to the Japa Coach app with this account and allow notifications first.'], 422);
        }

        $sent = $push->send($request->user(), new PushMessage('Test notification', 'Push notifications are working. 🎉', '/home'));

        return response()->json(['message' => "Sent to {$sent} of {$devices} phone(s)."]);
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
