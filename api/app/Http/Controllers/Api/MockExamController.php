<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Services\MockExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockExamController extends Controller
{
    public function __construct(private MockExamService $mockExams) {}

    public function index(Request $request): JsonResponse
    {
        $exams = $request->user()->mockExams()->latest()->limit(20)->get();

        return response()->json(['data' => $exams->map(fn (MockExam $exam): array => $this->present($exam))]);
    }

    public function store(Request $request): JsonResponse
    {
        $exam = $this->mockExams->create($request->user());

        return response()->json(['exam' => $this->present($exam)], 201);
    }

    public function show(Request $request, int $exam): JsonResponse
    {
        $exam = $request->user()->mockExams()->findOrFail($exam);

        if ($exam->status !== 'completed') {
            $this->mockExams->refresh($exam);
        }

        return response()->json(['exam' => $this->present($exam)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MockExam $exam): array
    {
        return [
            'id' => $exam->id,
            'status' => $exam->status,
            'test_type' => $exam->test_type,
            'overall_band' => $exam->overall_band,
            'created_at' => $exam->created_at?->toIso8601String(),
            'completed_at' => $exam->completed_at?->toIso8601String(),
            'parts' => $this->mockExams->parts($exam),
        ];
    }
}
