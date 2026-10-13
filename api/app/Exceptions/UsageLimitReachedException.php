<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class UsageLimitReachedException extends RuntimeException
{
    public function __construct(public readonly string $feature)
    {
        parent::__construct('You have used all your '.str_replace('_', ' ', $feature).' for now. Upgrade your plan to keep practising.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'limit_reached',
            'feature' => $this->feature,
        ], 402);
    }
}
