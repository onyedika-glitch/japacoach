<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PracticeAttemptResource;
use App\Models\MockExam;
use App\Models\PracticeAttempt;
use App\Models\PracticeTest;
use App\Models\User;
use App\Services\MockExamService;
use App\Services\Scoring\PracticeMarker;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PracticeController extends Controller
{
    public function tests(Request $request, string $module): JsonResponse
    {
        $user = $request->user();

        $tests = PracticeTest::published()
            ->where('module', $module)
            ->forTestType($user->test_type)
            ->withCount('questions')
            ->withCount(['attempts' => fn ($query) => $query->where('user_id', $user->id)])
            ->withMax(['attempts as best_band' => fn ($query) => $query->where('user_id', $user->id)->where('status', 'submitted')], 'band')
            ->latest('id')
            ->get();

        return response()->json(['data' => $tests->map(fn (PracticeTest $test): array => [
            'id' => $test->id,
            'title' => $test->title,
            'test_type' => $test->test_type,
            'question_count' => $test->questions_count,
            'duration_minutes' => $test->duration_minutes,
            'best_band' => $test->best_band !== null ? (float) $test->best_band : null,
            'attempts_count' => $test->attempts_count,
        ])]);
    }

    public function start(Request $request, string $module, PracticeTest $test, UsageService $usage): JsonResponse
    {
        abort_unless($test->is_published && $test->module === $module, 404);

        $validated = $request->validate(['mock_exam_id' => ['nullable', 'integer']]);
        $user = $request->user();
        $mockExam = $this->mockExamFor($user, $validated['mock_exam_id'] ?? null, $test);

        if (! $mockExam) {
            $usage->authorize($user, $module);
        }

        $attempt = PracticeAttempt::create([
            'user_id' => $user->id,
            'practice_test_id' => $test->id,
            'mock_exam_id' => $mockExam?->id,
            'module' => $module,
            'status' => 'in_progress',
            'answers' => [],
            'started_at' => now(),
        ]);

        $user->touchStreak();

        return response()->json(['attempt' => new PracticeAttemptResource($this->loadAttempt($attempt))], 201);
    }

    public function show(Request $request, int $attempt): JsonResponse
    {
        return response()->json(['attempt' => new PracticeAttemptResource($this->loadAttempt($this->findAttempt($request->user(), $attempt)))]);
    }

    public function save(Request $request, int $attempt): JsonResponse
    {
        $attempt = $this->findAttempt($request->user(), $attempt);
        $this->mergeAnswers($request, $attempt);

        return response()->json(['attempt' => new PracticeAttemptResource($this->loadAttempt($attempt))]);
    }

    public function submit(Request $request, int $attempt, PracticeMarker $marker, MockExamService $mockExams): JsonResponse
    {
        $attempt = $this->findAttempt($request->user(), $attempt);

        if (! $attempt->isSubmitted()) {
            $this->mergeAnswers($request, $attempt);
            $marker->mark($attempt);

            if ($attempt->mockExam) {
                $mockExams->refresh($attempt->mockExam);
            }
        }

        return response()->json(['attempt' => new PracticeAttemptResource($this->loadAttempt($attempt))]);
    }

    private function mergeAnswers(Request $request, PracticeAttempt $attempt): void
    {
        $validated = $request->validate([
            'answers' => ['sometimes', 'array'],
            'answers.*' => ['nullable'],
        ]);

        if ($attempt->isSubmitted()) {
            throw ValidationException::withMessages(['attempt' => 'This test has already been submitted.']);
        }

        $validIds = $attempt->test->questions()->pluck('practice_questions.id')->map(fn ($id) => (string) $id)->all();
        $answers = $attempt->answers ?? [];

        foreach ($validated['answers'] ?? [] as $questionId => $value) {
            if (! in_array((string) $questionId, $validIds, true)) {
                continue;
            }
            $answers[$questionId] = is_array($value)
                ? array_values(array_map(fn ($item): string => mb_substr((string) $item, 0, 200), $value))
                : mb_substr((string) $value, 0, 200);
        }

        $attempt->answers = $answers;
        $attempt->save();
    }

    private function findAttempt(User $user, int $id): PracticeAttempt
    {
        return $user->practiceAttempts()->findOrFail($id);
    }

    private function loadAttempt(PracticeAttempt $attempt): PracticeAttempt
    {
        return $attempt->load('test.sections.questions');
    }

    private function mockExamFor(User $user, ?int $mockExamId, PracticeTest $test): ?MockExam
    {
        if ($mockExamId === null) {
            return null;
        }

        $exam = $user->mockExams()->where('status', 'in_progress')->find($mockExamId);
        $expectedTestId = $test->module === 'listening' ? $exam?->listening_test_id : $exam?->reading_test_id;

        if (! $exam || $expectedTestId !== $test->id || $exam->practiceAttempts()->where('module', $test->module)->exists()) {
            throw ValidationException::withMessages(['mock_exam_id' => 'This test cannot be started for that mock exam.']);
        }

        return $exam;
    }
}
