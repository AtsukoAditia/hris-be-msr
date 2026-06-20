<?php

use App\Http\Controllers\API\Admin\PayrollReportV2Controller;
use App\Http\Controllers\API\PayslipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/payslips', [PayslipController::class, 'index']);
        Route::get('/payslips/{payroll}', [PayslipController::class, 'show']);
        Route::post('/payslips/{payroll}/pdf', [PayslipController::class, 'download']);
    });

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'role:admin,hr'])
    ->group(function () {
        Route::get('/payroll-reports', [PayrollReportV2Controller::class, 'index']);
        Route::get('/payroll-reports/breakdown', [PayrollReportV2Controller::class, 'breakdown']);
        Route::post('/payroll-reports/csv', [PayrollReportV2Controller::class, 'export']);
        Route::post('/payrolls/{payroll}/payslip-pdf', [PayrollReportV2Controller::class, 'payslip']);
    });
