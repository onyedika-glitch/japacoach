<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Token sign-in for the mobile app. The website keeps using cookie sessions (AuthController).
 */
class MobileAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'referral_code' => ['nullable', 'string', 'max:16'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
        ]);
        AuthController::applyReferral($user, $validated['referral_code'] ?? null);
        $user->save();

        return $this->tokenResponse($user, $validated['device_name'] ?? null, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($validated['email']))->first();

        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These details do not match our records.']);
        }

        return $this->tokenResponse($user, $validated['device_name'] ?? null);
    }

    /**
     * Sign in with a Google ID token obtained by the app's native Google sign-in.
     */
    public function google(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'referral_code' => ['nullable', 'string', 'max:16'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $claims = Http::timeout(10)
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $validated['id_token']])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            Log::warning('Mobile Google sign-in failed', ['error' => $exception->getMessage()]);
            $claims = null;
        }

        $audiences = array_filter([config('services.google.client_id'), ...config('japa.mobile.google_client_ids')]);
        $valid = is_array($claims)
            && in_array($claims['aud'] ?? null, $audiences, true)
            && in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filled($claims['sub'] ?? null)
            && filled($claims['email'] ?? null);

        if (! $valid) {
            throw ValidationException::withMessages(['id_token' => 'Google sign-in failed. Please try again.']);
        }

        $user = GoogleAuthController::findOrCreateUser(
            (string) $claims['sub'],
            (string) $claims['email'],
            $claims['name'] ?? null,
            $claims['picture'] ?? null,
            $validated['referral_code'] ?? null,
        );

        return $this->tokenResponse($user, $validated['device_name'] ?? null);
    }

    public function logout(Request $request): JsonResponse
    {
        // Stop pushes to this phone once nobody is signed in on it.
        if ($request->filled('device_token')) {
            $request->user()->deviceTokens()->where('token', (string) $request->input('device_token'))->delete();
        }
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'mobile', ['*'])->plainTextToken;

        return response()->json(['token' => $token, 'user' => new UserResource($user)], $status);
    }
}
