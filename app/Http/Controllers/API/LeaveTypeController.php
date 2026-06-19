<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of leave types.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveType::query()->orderBy('name');

        // Only show active leave types for non-admin users
        if (! ($request->user()->isAdmin() || $request->user()->isHr())) {
            $query->where('is_active', true);
        } elseif ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $leaveTypes = $query->get();

        $data = $leaveTypes->map(function ($type) {
            return [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'requires_balance' => (bool) $type->requires_balance,
                'requires_attachment' => (bool) $type->requires_attachment,
                'gender_restriction' => $type->gender_restriction,
                'max_days_per_year' => $type->max_days_per_year,
                'is_paid' => (bool) $type->is_paid,
                'is_active' => (bool) $type->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar jenis cuti berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Display the specified leave type.
     */
    public function show(LeaveType $leaveType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail jenis cuti berhasil diambil.',
            'data' => [
                'id' => $leaveType->id,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'description' => $leaveType->description,
                'requires_balance' => (bool) $leaveType->requires_balance,
                'requires_attachment' => (bool) $leaveType->requires_attachment,
                'gender_restriction' => $leaveType->gender_restriction,
                'max_days_per_year' => $leaveType->max_days_per_year,
                'is_paid' => (bool) $leaveType->is_paid,
                'is_active' => (bool) $leaveType->is_active,
            ],
        ]);
    }
}
