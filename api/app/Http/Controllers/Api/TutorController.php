<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Centre;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a tutor sees: their classes, each student's latest bands and activity, and their voucher codes.
 */
class TutorController extends Controller
{
    public function __invoke(Request $request, ProgressService $progress): JsonResponse
    {
        $centres = $request->user()->ownedCentres()->with(['students', 'vouchers.redeemer:id,name'])->orderBy('name')->get();

        abort_if($centres->isEmpty(), 403, 'You do not have a class yet.');

        $since = now()->subDays(30);

        return response()->json(['data' => $centres->map(fn (Centre $centre): array => [
            'id' => $centre->id,
            'name' => $centre->name,
            'join_code' => $centre->join_code,
            'students' => $centre->students->sortBy('name')->values()->map(function (User $student) use ($progress, $since): array {
                $latest = $progress->latestBands($student);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'joined_at' => $student->pivot->created_at?->toIso8601String(),
                    'plan' => $student->isPremium() ? $student->plan : 'free',
                    'plan_expires_at' => $student->isPremium() ? $student->plan_expires_at?->toIso8601String() : null,
                    'target_band' => $student->target_band,
                    'test_date' => $student->test_date?->toDateString(),
                    'last_active_on' => $student->last_active_on?->toDateString(),
                    'latest' => $latest + ['overall' => $progress->overall($latest)],
                    'attempts_30d' => $student->speakingAttempts()->where('created_at', '>=', $since)->count()
                        + $student->writingAttempts()->where('created_at', '>=', $since)->count()
                        + $student->practiceAttempts()->where('created_at', '>=', $since)->count(),
                ];
            }),
            'vouchers' => $centre->vouchers->sortBy('id')->values()->map(fn (Voucher $v): array => [
                'code' => $v->code,
                'plan' => $v->plan,
                'days' => $v->days,
                'redeemed_by' => $v->redeemer?->name,
                'redeemed_at' => $v->redeemed_at?->toIso8601String(),
            ]),
        ])]);
    }
}
