<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpeakingAttemptResource;
use App\Jobs\ScoreSpeakingAttempt;
use App\Jobs\TranscribeSpeakingAnswer;
use App\Models\MockExam;
use App\Models\SpeakingAnswer;
use App\Models\SpeakingAttempt;
use App\Models\SpeakingQuestion;
use App\Models\User;
use App\Services\MockExamService;
use App\Services\UsageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpeakingAttemptController extends Controller
{
    private const AUDIO_MIME_TYPES = [
        'audio/webm', 'video/webm', 'audio/ogg', 'application/ogg', 'audio/mp4', 'video/mp4',
        'audio/x-m4a', 'audio/m4a', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/aac',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return SpeakingAttemptResource::collection(
            $request->user()->speakingAttempts()->latest()->paginate(15)
        );
    }

    public function store(Request $request, UsageService $usage): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['full', 'part1', 'part2', 'part3'])],
            'mock_exam_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $mockExam = $this->mockExamFor($user, $validated['mock_exam_id'] ?? null, $validated['mode']);

        if (! $mockExam) {
            $usage->authorize($user, $validated['mode'] === 'full' ? 'speaking_mocks' : 'speaking_drills');
        }

        $questions = $this->pickQuestions($user, $validated['mode']);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['mode' => 'No speaking questions are available yet.']);
        }

        $attempt = DB::transaction(function () use ($user, $validated, $mockExam, $questions): SpeakingAttempt {
            $attempt = SpeakingAttempt::create([
                'user_id' => $user->id,
                'mock_exam_id' => $mockExam?->id,
                'mode' => $validated['mode'],
                'status' => 'in_progress',
            ]);

            $questions->values()->each(fn (SpeakingQuestion $question, int $index) => $attempt->answers()->create([
                'speaking_question_id' => $question->id,
                'part' => $question->part,
                'position' => $index + 1,
                'status' => 'pending',
            ]));

            return $attempt;
        });

        $user->touchStreak();

        return response()->json(['attempt' => new SpeakingAttemptResource($attempt->load('answers.question'))], 201);
    }

    public function show(Request $request, int $attempt): JsonResponse
    {
        return response()->json(['attempt' => new SpeakingAttemptResource($this->findAttempt($request->user(), $attempt)->load('answers.question'))]);
    }

    public function uploadAnswer(Request $request, int $attempt): JsonResponse
    {
        $attempt = $this->findAttempt($request->user(), $attempt);

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'audio' => ['required', 'file', 'max:'.config('japa.speaking.max_upload_kb'), 'mimetypes:'.implode(',', self::AUDIO_MIME_TYPES)],
            'duration_seconds' => ['required', 'numeric', 'min:0', 'max:'.config('japa.speaking.max_answer_seconds')],
        ]);

        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages(['attempt' => 'This test has already been submitted.']);
        }

        /** @var SpeakingAnswer|null $answer */
        $answer = $attempt->answers()->where('speaking_question_id', $validated['question_id'])->first();

        if (! $answer) {
            throw ValidationException::withMessages(['question_id' => 'That question is not part of this test.']);
        }

        if ($answer->status === 'transcribed') {
            throw ValidationException::withMessages(['question_id' => 'This answer has already been processed.']);
        }

        if ($answer->audio_path) {
            Storage::disk('local')->delete($answer->audio_path);
        }

        $extension = $request->file('audio')->guessExtension() ?: 'webm';
        $path = $request->file('audio')->storeAs(
            "speaking/{$attempt->user_id}/{$attempt->id}",
            "answer-{$answer->id}.{$extension}",
            'local',
        );

        $answer->forceFill([
            'audio_path' => $path,
            'duration_seconds' => round((float) $validated['duration_seconds'], 1),
            'status' => 'uploaded',
            'transcript' => null,
            'words' => null,
            'metrics' => null,
        ])->save();

        TranscribeSpeakingAnswer::dispatch($answer->id);

        return response()->json(['answer' => [
            'id' => $answer->id,
            'question_id' => $answer->speaking_question_id,
            'status' => $answer->status,
            'duration_seconds' => $answer->duration_seconds,
        ]], 201);
    }

    public function submit(Request $request, int $attempt, MockExamService $mockExams): JsonResponse
    {
        $attempt = $this->findAttempt($request->user(), $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json(['attempt' => new SpeakingAttemptResource($attempt->load('answers.question'))]);
        }

        if (! $attempt->answers()->whereIn('status', ['uploaded', 'transcribed'])->exists()) {
            throw ValidationException::withMessages(['attempt' => 'Record at least one answer before submitting.']);
        }

        $attempt->forceFill(['status' => 'processing', 'submitted_at' => now()])->save();
        ScoreSpeakingAttempt::dispatchIfReady($attempt->id);

        if ($attempt->mockExam) {
            $mockExams->refresh($attempt->mockExam);
        }

        return response()->json(['attempt' => new SpeakingAttemptResource($attempt->refresh()->load('answers.question'))]);
    }

    public function audio(Request $request, SpeakingAnswer $answer): StreamedResponse
    {
        abort_unless($answer->attempt->user_id === $request->user()->id && $answer->audio_path, 404);
        abort_unless(Storage::disk('local')->exists($answer->audio_path), 404);

        return Storage::disk('local')->response($answer->audio_path);
    }

    private function findAttempt(User $user, int $id): SpeakingAttempt
    {
        return $user->speakingAttempts()->findOrFail($id);
    }

    private function mockExamFor(User $user, ?int $mockExamId, string $mode): ?MockExam
    {
        if ($mockExamId === null) {
            return null;
        }

        $exam = $user->mockExams()->where('status', 'in_progress')->find($mockExamId);

        if (! $exam || $mode !== 'full' || $exam->speakingAttempts()->exists()) {
            throw ValidationException::withMessages(['mock_exam_id' => 'This mock exam speaking test cannot be started.']);
        }

        return $exam;
    }

    /**
     * Choose questions for the mode, preferring topics the user has not practised yet.
     *
     * @return Collection<int, SpeakingQuestion>
     */
    private function pickQuestions(User $user, string $mode): Collection
    {
        $usedTopics = SpeakingQuestion::query()
            ->whereIn('id', SpeakingAnswer::query()
                ->whereIn('speaking_attempt_id', $user->speakingAttempts()->select('id'))
                ->select('speaking_question_id'))
            ->distinct()
            ->pluck('topic')
            ->all();

        $part1 = fn (): Collection => $this->topicSet(1, $usedTopics, 4);
        $part2 = fn (): Collection => $this->topicSet(2, $usedTopics, 1);

        return match ($mode) {
            'part1' => $part1(),
            'part2' => $part2(),
            'part3' => $this->topicSet(3, $usedTopics, 3),
            'full' => (function () use ($part1, $part2): Collection {
                $card = $part2();
                $followUps = $card->isNotEmpty()
                    ? SpeakingQuestion::published()->where('part', 3)->where('topic', $card->first()->topic)->inRandomOrder()->limit(3)->get()
                    : new Collection;

                if ($followUps->isEmpty()) {
                    $followUps = $this->topicSet(3, [], 3);
                }

                return $part1()->concat($card)->concat($followUps);
            })(),
        };
    }

    /**
     * @param  list<string>  $usedTopics
     * @return Collection<int, SpeakingQuestion>
     */
    private function topicSet(int $part, array $usedTopics, int $limit): Collection
    {
        $topics = SpeakingQuestion::published()->where('part', $part)->distinct()->pluck('topic');
        $fresh = $topics->diff($usedTopics);
        $topic = ($fresh->isNotEmpty() ? $fresh : $topics)->shuffle()->first();

        if ($topic === null) {
            return new Collection;
        }

        return SpeakingQuestion::published()->where('part', $part)->where('topic', $topic)->orderBy('id')->limit($limit)->get();
    }
}
