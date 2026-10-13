<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudyPlanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudyPlanController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['plan' => $request->user()->studyPlan?->plan]);
    }

    public function generate(Request $request, StudyPlanGenerator $generator): JsonResponse
    {
        $user = $request->user();

        if (! $user->target_band || ! $user->test_date || $user->test_date->isPast()) {
            throw ValidationException::withMessages(['profile' => 'Set your target band and an upcoming test date in Settings first.']);
        }

        return response()->json(['plan' => $generator->generate($user)->plan]);
    }
}
