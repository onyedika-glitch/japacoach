<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Centre;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Students joining and leaving a tutor's class (centre).
 */
class CentreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->centres()->with('owner:id,name')->get()->map(fn (Centre $c): array => self::present($c))]);
    }

    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);

        /** @var User $user */
        $user = $request->user();
        $centre = Centre::where('join_code', strtoupper(trim($validated['code'])))->first();

        if (! $centre) {
            throw ValidationException::withMessages(['code' => 'We could not find a class with that code. Check it with your tutor.']);
        }
        if ($centre->owner_id === $user->id) {
            throw ValidationException::withMessages(['code' => 'This is your own class. Share the code with your students.']);
        }

        $user->centres()->syncWithoutDetaching([$centre->id]);

        return response()->json(['message' => "You joined {$centre->name}.", 'centre' => self::present($centre->load('owner:id,name'))]);
    }

    public function leave(Request $request, Centre $centre): JsonResponse
    {
        $request->user()->centres()->detach($centre->id);

        return response()->json(['message' => "You left {$centre->name}."]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Centre $centre): array
    {
        return ['id' => $centre->id, 'name' => $centre->name, 'tutor' => $centre->owner?->name];
    }
}
