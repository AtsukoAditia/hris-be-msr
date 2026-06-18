<?php

use App\Http\Controllers\API\AttendanceActionController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AttendanceSettingController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\LeaveDetailController;
use App\Http\Controllers\API\MyDocumentController;
use App\Http\Controllers\API\PositionController;
use App\Http\Controllers\API\ProfileChangeRequestController;
use App\Http\Controllers\API\ProfileChangeReviewController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ShiftController;
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
        Route::post('/attendance/check-in', [AttendanceActionController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceActionController::class, 'checkOut']);
        Route::post('/attendance/check-in/qr', [AttendanceActionController::class, 'checkInQr']);
        Route::post('/attendance/check-out/qr', [AttendanceActionController::class, 'checkOutQr']);

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
            Route::get('/departments', [DepartmentController::class, 'index']);
            Route::get('/departments/{department}', [DepartmentController::class, 'show']);
            Route::get('/positions', [PositionController::class, 'index']);
            Route::get('/positions/{position}', [PositionController::class, 'show']);
            Route::get('/branches', [BranchController::class, 'index']);
            Route::get('/branches/{branch}', [BranchController::class, 'show']);
        });

        Route::get('/leaves/my', [LeaveController::class, 'my']);
        Route::get('/leaves/balance', [LeaveController::class, 'balance']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::get('/leaves/{leave}', LeaveDetailController::class);
        Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);

        Route::get('/profile/me', [ProfileController::class, 'me']);
        Route::put('/profile/me', [ProfileController::class, 'updateMe']);
        Route::patch('/profile/me', [ProfileController::class, 'updateMe']);
        Route::get('/profile/me/emergency-contacts', [ContactController::class, 'myIndex']);
        Route::post('/profile/me/emergency-contacts', [ContactController::class, 'myStore']);
        Route::put('/profile/me/emergency-contacts/{emergencyContact}', [ContactController::class, 'myUpdate']);
        Route::patch('/profile/me/emergency-contacts/{emergencyContact}', [ContactController::class, 'myUpdate']);
        Route::delete('/profile/me/emergency-contacts/{emergencyContact}', [ContactController::class, 'myDestroy']);

        Route::get('/profile/change-requests', [ProfileChangeRequestController::class, 'index']);
        Route::post('/profile/change-requests', [ProfileChangeRequestController::class, 'store']);
        Route::get('/profile/change-requests/{profileChangeRequest}', [ProfileChangeRequestController::class, 'show']);
        Route::delete('/profile/change-requests/{profileChangeRequest}', [ProfileChangeRequestController::class, 'destroy']);

        Route::get('/document-categories', [DocumentController::class, 'categories']);
        Route::get('/documents/my', [MyDocumentController::class, 'index']);
        Route::get('/documents/my/summary', [MyDocumentController::class, 'summary']);
        Route::get('/documents/my/{employeeDocument}/download', [MyDocumentController::class, 'download']);
        Route::get('/documents/my/{employeeDocument}', [MyDocumentController::class, 'show']);

        Route::middleware('role:admin,hr')->group(function () {
            Route::put('/attendance/settings', [AttendanceSettingController::class, 'update']);
            Route::post('/attendance/qr/generate', [AttendanceSettingController::class, 'generateQr']);
            Route::apiResource('/shifts', ShiftController::class);
            Route::get('/shift-schedules/employee/{employeeId}', [ShiftScheduleController::class, 'getByEmployee']);
            Route::get('/shift-schedules/date/{date}', [ShiftScheduleController::class, 'getByDate']);
            Route::post('/shift-schedules/bulk', [ShiftScheduleController::class, 'bulkStore']);
            Route::apiResource('/shift-schedules', ShiftScheduleController::class);

            Route::get('/profile-change-requests', [ProfileChangeReviewController::class, 'index']);
            Route::post('/profile-change-requests/{profileChangeRequest}/approve', [ProfileChangeReviewController::class, 'approve']);
            Route::post('/profile-change-requests/{profileChangeRequest}/reject', [ProfileChangeReviewController::class, 'reject']);
            Route::get('/profile-change-requests/{profileChangeRequest}', [ProfileChangeReviewController::class, 'show']);

            Route::get('/employee-documents', [DocumentController::class, 'index']);
            Route::get('/employee-documents/summary', [DocumentController::class, 'summary']);
            Route::get('/employees/{employee}/documents', [DocumentController::class, 'employeeIndex']);
            Route::post('/employees/{employee}/documents', [DocumentController::class, 'store']);
            Route::get('/employees/{employee}/documents/{employeeDocument}/download', [DocumentController::class, 'download']);
            Route::post('/employees/{employee}/documents/{employeeDocument}/replace', [DocumentController::class, 'replace']);
            Route::get('/employees/{employee}/documents/{employeeDocument}', [DocumentController::class, 'show']);
            Route::put('/employees/{employee}/documents/{employeeDocument}', [DocumentController::class, 'update']);
            Route::patch('/employees/{employee}/documents/{employeeDocument}', [DocumentController::class, 'update']);
            Route::delete('/employees/{employee}/documents/{employeeDocument}', [DocumentController::class, 'destroy']);

            Route::get('/employees/manager-options', [EmployeeController::class, 'managerOptions']);
            Route::get('/employees/{employee}/profile', [ProfileController::class, 'show']);
            Route::put('/employees/{employee}/profile', [ProfileController::class, 'update']);
            Route::patch('/employees/{employee}/profile', [ProfileController::class, 'update']);
            Route::get('/employees/{employee}/emergency-contacts', [ContactController::class, 'index']);
            Route::post('/employees/{employee}/emergency-contacts', [ContactController::class, 'store']);
            Route::put('/employees/{employee}/emergency-contacts/{emergencyContact}', [ContactController::class, 'update']);
            Route::patch('/employees/{employee}/emergency-contacts/{emergencyContact}', [ContactController::class, 'update']);
            Route::delete('/employees/{employee}/emergency-contacts/{emergencyContact}', [ContactController::class, 'destroy']);
            Route::apiResource('/employees', EmployeeController::class)->except(['destroy']);
            Route::post('/employees/{employee}/face-enrollment', [EmployeeController::class, 'enrollFace']);
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

            Route::post('/departments', [DepartmentController::class, 'store']);
            Route::put('/departments/{department}', [DepartmentController::class, 'update']);
            Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
            Route::post('/positions', [PositionController::class, 'store']);
            Route::put('/positions/{position}', [PositionController::class, 'update']);
            Route::patch('/positions/{position}', [PositionController::class, 'update']);
            Route::delete('/positions/{position}', [PositionController::class, 'destroy']);
            Route::post('/branches', [BranchController::class, 'store']);
            Route::put('/branches/{branch}', [BranchController::class, 'update']);
            Route::patch('/branches/{branch}', [BranchController::class, 'update']);
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);
        });
    });
});
