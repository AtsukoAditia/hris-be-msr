<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ReservedActionsController extends Controller
{
    public function download(): JsonResponse
    {
        return response()->json(['message' => 'This endpoint is not available.'], 501);
    }

    public function __call(string $name, array $arguments): JsonResponse
    {
        return response()->json(['message' => 'This endpoint is not available.'], 501);
    }
}
