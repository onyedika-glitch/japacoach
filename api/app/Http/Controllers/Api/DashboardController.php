<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ProgressService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProgressService $progress, UsageService $usage): JsonResponse
    {
        $user = $request->user();
        $latest = $progress->latestBands($user);

        return response()->json([
            'user' => new UserResource($user),
            'usage' => $usage->summary($user),
            'latest' => $latest + ['overall' => $progress->overall($latest)],
            'history' => $progress->history($user),
            'recent_attempts' => $progress->recent($user),
            'study_plan' => $user->studyPlan?->plan,
            'days_to_test' => $user->test_date ? max(0, (int) now()->startOfDay()->diffInDays($user->test_date, false)) : null,
        ]);
    }
}
