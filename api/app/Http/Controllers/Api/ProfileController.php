<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\SpeakingAnswer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'test_type' => ['sometimes', Rule::in(['academic', 'general'])],
            'target_band' => ['sometimes', 'nullable', 'numeric', 'between:4,9', 'multiple_of:0.5'],
            'test_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'email_reminders' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update($validated);

        return response()->json(['user' => new UserResource($request->user()->refresh())]);
    }

    /**
     * Permanently delete the account, its attempts and recordings (required by Google Play for apps with sign-up).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'in:DELETE']]);

        /** @var User $user */
        $user = $request->user();

        SpeakingAnswer::whereHas('attempt', fn ($query) => $query->where('user_id', $user->id))
            ->whereNotNull('audio_path')
            ->pluck('audio_path')
            ->each(fn (string $path) => Storage::disk('local')->delete($path));

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Your account has been deleted.']);
    }
}
