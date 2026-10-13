<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WritingAttemptResource;
use App\Http\Resources\WritingTaskResource;
use App\Jobs\ScoreWritingAttempt;
use App\Models\MockExam;
use App\Models\User;
use App\Models\WritingAttempt;
use App\Models\WritingTask;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class WritingController extends Controller
{
    private const MIN_WORDS_TO_MARK = 50;

    public function tasks(Request $request): AnonymousResourceCollection
    {
        $request->validate(['task_number' => ['nullable', 'integer', 'in:1,2']]);

        $tasks = WritingTask::published()
            ->forTestType($request->user()->test_type)
            ->when($request->filled('task_number'), fn ($query) => $query->where('task_number', $request->integer('task_number')))
            ->orderBy('task_number')
            ->latest('id')
            ->get();

        return WritingTaskResource::collection($tasks);
    }

    public function task(WritingTask $task): JsonResponse
    {
        abort_unless($task->is_published, 404);

        return response()->json(['task' => new WritingTaskResource($task)]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return WritingAttemptResource::collection(
            $request->user()->writingAttempts()->with('task')->latest()->paginate(15)
        );
    }

    public function store(Request $request, UsageService $usage): JsonResponse
    {
        $validated = $request->validate([
            'writing_task_id' => ['required', 'integer', 'exists:writing_tasks,id'],
            'essay' => ['required', 'string', 'max:20000'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'mock_exam_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $task = WritingTask::published()->findOrFail($validated['writing_task_id']);
        $mockExam = $this->mockExamFor($user, $validated['mock_exam_id'] ?? null, $task);

        $wordCount = WritingAttempt::countWords($validated['essay']);
        if ($wordCount < self::MIN_WORDS_TO_MARK) {
            throw ValidationException::withMessages(['essay' => 'Write at least '.self::MIN_WORDS_TO_MARK.' words so the examiner can mark your response.']);
        }

        if (! $mockExam) {
            $usage->authorize($user, 'writing');
        }

        $attempt = WritingAttempt::create([
            'user_id' => $user->id,
            'writing_task_id' => $task->id,
            'mock_exam_id' => $mockExam?->id,
            'essay' => $validated['essay'],
            'word_count' => $wordCount,
            'time_spent_seconds' => $validated['time_spent_seconds'] ?? null,
            'status' => 'processing',
        ]);

        ScoreWritingAttempt::dispatch($attempt->id);
        $user->touchStreak();

        return response()->json(['attempt' => new WritingAttemptResource($attempt->load('task'))], 201);
    }

    public function show(Request $request, int $attempt): JsonResponse
    {
        return response()->json(['attempt' => new WritingAttemptResource($request->user()->writingAttempts()->with('task')->findOrFail($attempt))]);
    }

    private function mockExamFor(User $user, ?int $mockExamId, WritingTask $task): ?MockExam
    {
        if ($mockExamId === null) {
            return null;
        }

        $exam = $user->mockExams()->where('status', 'in_progress')->find($mockExamId);
        $belongs = $exam && in_array($task->id, [$exam->writing_task1_id, $exam->writing_task2_id], true);

        if (! $belongs || $exam->writingAttempts()->where('writing_task_id', $task->id)->exists()) {
            throw ValidationException::withMessages(['mock_exam_id' => 'This writing task cannot be submitted for that mock exam.']);
        }

        return $exam;
    }
}
