<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AttendanceSettingController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ShiftController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ShiftScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        Route::get('/attendance/my', [AttendanceController::class, 'my']);
        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::post('/attendance/check-in/qr', [AttendanceController::class, 'checkInQr']);
        Route::post('/attendance/check-out/qr', [AttendanceController::class, 'checkOutQr']);

        Route::middleware('role:admin,hr,manager')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index']);
            Route::get('/attendance/export', [AttendanceController::class, 'export']);
            Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'getByEmployee']);
            Route::get('/attendance/settings', [AttendanceSettingController::class, 'show']);
            Route::get('/attendance/{attendance}', [AttendanceController::class, 'show']);

            Route::get('/leaves', [LeaveController::class, 'index']);
            Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
            Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject']);

            Route::get('/reports/attendance', [ReportController::class, 'attendance']);
            Route::get('/reports/leave', [ReportController::class, 'leave']);
            Route::get('/reports/employee', [ReportController::class, 'employee']);
            Route::get('/reports/export', [ReportController::class, 'export']);
        });

        Route::get('/leaves/my', [LeaveController::class, 'my']);
        Route::get('/leaves/balance', [LeaveController::class, 'balance']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::get('/leaves/{leave}', [LeaveController::class, 'show']);
        Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);

        Route::middleware('role:admin,hr')->group(function () {
            Route::put('/attendance/settings', [AttendanceSettingController::class, 'update']);
            Route::post('/attendance/qr/generate', [AttendanceSettingController::class, 'generateQr']);
            Route::apiResource('/shifts', ShiftController::class);
            Route::apiResource('/shift-schedules', ShiftScheduleController::class);
            Route::get('/shift-schedules/employee/{employeeId}', [ShiftScheduleController::class, 'getByEmployee']);
            Route::get('/shift-schedules/date/{date}', [ShiftScheduleController::class, 'getByDate']);
            Route::post('/shift-schedules/bulk', [ShiftScheduleController::class, 'bulkStore']);
            Route::apiResource('/employees', EmployeeController::class)->except(['destroy']);
            Route::post('/employees/{employee}/face-enrollment', [EmployeeController::class, 'enrollFace']);
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
            Route::get('/employees/{employee}/profile', [EmployeeController::class, 'profile']);
        });
    });
});
