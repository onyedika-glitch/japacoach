<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->filled('ref')) {
            $request->session()->put('referral_code', (string) $request->query('ref'));
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontend = rtrim(config('japa.frontend_url'), '/');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google sign-in failed', ['error' => $exception->getMessage()]);

            return redirect()->away("{$frontend}/login?error=google");
        }

        $user = self::findOrCreateUser(
            (string) $googleUser->getId(),
            (string) $googleUser->getEmail(),
            $googleUser->getName(),
            $googleUser->getAvatar(),
            $request->session()->pull('referral_code'),
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->away("{$frontend}/dashboard");
    }

    /**
     * Find the user for a Google account (by Google ID, then email) or create one, and link the Google ID.
     */
    public static function findOrCreateUser(string $googleId, string $email, ?string $name, ?string $avatarUrl, ?string $referralCode = null): User
    {
        $email = strtolower($email);
        $user = User::where('google_id', $googleId)->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            $user = new User([
                'name' => $name ?: explode('@', $email)[0],
                'email' => $email,
            ]);
            AuthController::applyReferral($user, $referralCode);
            $user->email_verified_at = now();
        }

        $user->google_id = $googleId;
        $user->avatar_url = $avatarUrl ?? $user->avatar_url;
        $user->save();

        return $user;
    }
}
