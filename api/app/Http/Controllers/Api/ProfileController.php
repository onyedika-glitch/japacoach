<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
