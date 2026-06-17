<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveDetailController extends Controller
{
    public function __construct(
        private readonly LeaveController $leaveController,
    ) {}

    public function __invoke(Request $request, Leave $leave): JsonResponse
    {
        $user = $request->user();
        $ownerUserId = (int) $leave->employee()->value('user_id');

        if (! $user->isManager() && $ownerUserId !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak diizinkan melihat pengajuan cuti ini.',
            ], 403);
        }

        return $this->leaveController->show($leave);
    }
}
