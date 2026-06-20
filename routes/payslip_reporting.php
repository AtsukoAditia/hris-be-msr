<?php

use App\Http\Controllers\API\Admin\PayrollReportAdminController;
use App\Http\Controllers\API\PayslipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/payslips', [PayslipController::class, 'index']);
        Route::get('/payslips/{payroll}', [PayslipController::class, 'show']);
    });

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'role:admin,hr'])
    ->group(function () {
        Route::get('/payroll-reports', [PayrollReportAdminController::class, 'index']);
        Route::get('/payroll-reports/breakdown', [PayrollReportAdminController::class, 'breakdown']);
    });
