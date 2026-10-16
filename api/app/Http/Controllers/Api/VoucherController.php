<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Redeeming prepaid plan codes. Offered on the website only: Google Play does not allow unlocking
 * paid features in the Android app with codes bought outside Play.
 */
class VoucherController extends Controller
{
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);

        /** @var User $user */
        $user = $request->user();
        $code = Voucher::normalise($validated['code']);

        $voucher = DB::transaction(function () use ($code, $user): Voucher {
            $voucher = Voucher::where('code', $code)->lockForUpdate()->first();

            if (! $voucher) {
                throw ValidationException::withMessages(['code' => 'That code is not valid. Check it and try again.']);
            }
            if ($voucher->isRedeemed()) {
                throw ValidationException::withMessages(['code' => 'That code has already been used.']);
            }

            $voucher->forceFill(['redeemed_by' => $user->id, 'redeemed_at' => now()])->save();

            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->grantPlan($voucher->plan, $voucher->days);

            if ($voucher->centre_id) {
                $locked->centres()->syncWithoutDetaching([$voucher->centre_id]);
            }

            return $voucher;
        });

        $voucher->load('centre');
        $planName = config("japa.plans.{$voucher->plan}.name", ucfirst($voucher->plan));
        $message = "{$planName} plan added: {$voucher->days} days of full access.".($voucher->centre ? " You joined {$voucher->centre->name}." : '');

        return response()->json(['message' => $message, 'user' => new UserResource($user->refresh())]);
    }
}
