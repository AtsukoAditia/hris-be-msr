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
            $user = request()->user();
            return response()->json([
                'total_employees' => \App\Models\Employee::where('is_active', true)->count(),
                'present_today' => \App\Models\Attendance::whereDate('attendance_date', today())
                    ->where('status', 'present')->count(),
                'active_leaves' => \App\Models\Leave::where('status', 'approved')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today())->count(),
                'pending_approvals' => \App\Models\Leave::where('status', 'pending')->count(),
            ]);
        });

        // Employees
        Route::apiResource('/employees', EmployeeController::class);

        // Attendance
        Route::get('/attendance/my', [AttendanceController::class, 'my']);
        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::post('/attendance/check-in/qr', [AttendanceController::class, 'checkInQr']);
        Route::post('/attendance/check-out/qr', [AttendanceController::class, 'checkOutQr']);
        Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'getByEmployee']);
        Route::get('/attendance/export', [AttendanceController::class, 'export']);
        Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/attendance/{id}', [AttendanceController::class, 'show']);

        // Shifts
        Route::apiResource('/shifts', ShiftController::class);

        // Leaves
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::get('/leaves/{id}', [LeaveController::class, 'show']);
        Route::put('/leaves/{id}/approve', [LeaveController::class, 'approve']);
        Route::put('/leaves/{id}/reject', [LeaveController::class, 'reject']);
        Route::delete('/leaves/{id}', [LeaveController::class, 'destroy']);

        // Reports
        Route::get('/reports/attendance', [ReportController::class, 'attendance']);
        Route::get('/reports/leave', [ReportController::class, 'leave']);
    });
});
