<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'code' => $user->referral_code,
            'link' => rtrim(config('japa.frontend_url'), '/').'/signup?ref='.$user->referral_code,
            'invited_count' => $user->referrals()->count(),
            'rewarded_count' => $user->referrals()->where('referral_rewarded', true)->count(),
            'bonus_speaking_mocks' => $user->bonus_speaking_mocks,
        ]);
    }
}
