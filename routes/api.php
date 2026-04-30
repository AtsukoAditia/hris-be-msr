<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\ShiftController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - HRIS MSR
|--------------------------------------------------------------------------
*/

// Public routes (no auth required)
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes (Sanctum auth required)
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Dashboard summary
        Route::get('/dashboard/summary', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees'  => \App\Models\Employee::where('is_active', true)->count(),
                    'present_today'    => \App\Models\Attendance::whereDate('attendance_date', today())
                        ->where('status', 'present')->count(),
                    'active_leaves'    => \App\Models\Leave::where('status', 'approved')
                        ->whereDate('start_date', '<=', today())
                        ->whereDate('end_date', '>=', today())->count(),
                    'pending_approval' => \App\Models\Leave::where('status', 'pending')->count(),
                ],
            ]);
        });

        // ==================== ATTENDANCE ====================
        // Employee self-service
        Route::get('/attendance/my',          [AttendanceController::class, 'myAttendance']);
        Route::get('/attendance/today',       [AttendanceController::class, 'todayAttendance']);
        Route::post('/attendance/check-in',   [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out',  [AttendanceController::class, 'checkOut']);
        Route::post('/attendance/check-in/qr',  [AttendanceController::class, 'checkInQR']);
        Route::post('/attendance/check-out/qr', [AttendanceController::class, 'checkOutQR']);

        // Admin/HR
        Route::get('/attendance',                          [AttendanceController::class, 'index']);
        Route::get('/attendance/{attendance}',             [AttendanceController::class, 'show']);
        Route::get('/attendance/employee/{employeeId}',    [AttendanceController::class, 'byEmployee']);
        Route::get('/attendance/export',                   [AttendanceController::class, 'export']);

        // ==================== SHIFTS ====================
        // Shift types CRUD (admin)
        Route::apiResource('/shifts', ShiftController::class);

        // Shift schedules (assign per date)
        Route::get('/shift-schedules',              [ShiftController::class, 'getSchedules']);
        Route::get('/shift-schedules/my',           [ShiftController::class, 'getMySchedule']);
        Route::post('/shift-schedules',             [ShiftController::class, 'assignShift']);
        Route::delete('/shift-schedules/{schedule}', [ShiftController::class, 'removeSchedule']);

        // ==================== LEAVES ====================
        Route::get('/leaves/my',               [LeaveController::class, 'myLeaves']);
        Route::get('/leaves',                  [LeaveController::class, 'index']);
        Route::post('/leaves',                 [LeaveController::class, 'store']);
        Route::get('/leaves/{leave}',          [LeaveController::class, 'show']);
        Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
        Route::post('/leaves/{leave}/reject',  [LeaveController::class, 'reject']);
        Route::delete('/leaves/{leave}',       [LeaveController::class, 'destroy']);

        // ==================== EMPLOYEES ====================
        Route::apiResource('/employees', EmployeeController::class)->except(['destroy']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::get('/employees/{employee}/profile', [EmployeeController::class, 'profile']);

        // ==================== REPORTS ====================
        Route::get('/reports/attendance', [ReportController::class, 'attendance']);
        Route::get('/reports/leave',      [ReportController::class, 'leave']);
        Route::get('/reports/employee',   [ReportController::class, 'employee']);
        Route::get('/reports/export',     [ReportController::class, 'export']);
    });
});
