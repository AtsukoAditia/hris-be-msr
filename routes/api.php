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
        // Hanya admin & hr yang boleh list/show/create/update
        Route::middleware('role:admin,hr')->group(function () {
            Route::apiResource('/employees', EmployeeController::class)->except(['destroy']);
        });

        // Hanya admin yang boleh delete employee
        Route::middleware('role:admin')->group(function () {
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
                ->name('employees.destroy');
        });

        // Attendance - self service untuk user yang memiliki profil employee
        Route::middleware('role:admin,hr,manager,employee')->group(function () {
            Route::get('/attendance/my', [AttendanceController::class, 'my']);
            Route::get('/attendance/today', [AttendanceController::class, 'today']);
            Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        });

        // Attendance - monitoring dan manajemen untuk admin/hr
        Route::middleware('role:admin,hr')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index']);
            Route::get('/attendance/{attendance}', [AttendanceController::class, 'show']);
            Route::post('/attendance', [AttendanceController::class, 'store']);
            Route::put('/attendance/{attendance}', [AttendanceController::class, 'update']);
            Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy']);
        });

        // Shifts
        Route::apiResource('/shifts', ShiftController::class);

        // Leaves
        Route::get('/leaves/my', [LeaveController::class, 'my']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/{leave}', [LeaveController::class, 'show']);
        Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
        Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject']);
        Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);

        // Reports
        Route::get('/reports/attendance', [ReportController::class, 'attendance']);
        Route::get('/reports/leave', [ReportController::class, 'leave']);
        Route::get('/reports/payroll', [ReportController::class, 'payroll']);
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
    });
});
