<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'referral_code' => ['nullable', 'string', 'max:16'],
            'test_type' => ['nullable', Rule::in(['academic', 'general'])],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'test_type' => $validated['test_type'] ?? 'academic',
        ]);
        self::applyReferral($user, $validated['referral_code'] ?? null);
        $user->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => strtolower($credentials['email']), 'password' => $credentials['password']], $request->boolean('remember', true))) {
            throw ValidationException::withMessages(['email' => 'These details do not match our records.']);
        }

        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Attach a valid referral code to a new user and give them their welcome bonus.
     */
    public static function applyReferral(User $user, ?string $code): void
    {
        if (blank($code)) {
            return;
        }

        $referrer = User::where('referral_code', strtoupper(trim($code)))->first();

        if ($referrer && $referrer->email !== $user->email) {
            $user->referred_by = $referrer->id;
            $user->bonus_speaking_mocks = (int) config('japa.referrals.referee_bonus_mocks');
        }
    }
}
