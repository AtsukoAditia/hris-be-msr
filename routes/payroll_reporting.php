<?php

use App\Http\Controllers\API\Admin\PayrollReportAdminController;
use App\Http\Controllers\API\PayslipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/payslips', [PayslipController::class, 'index']);
        Route::get('/payslips/{payroll}', [PayslipController::class, 'show']);
        Route::get('/payslips/{payroll}/download', [PayslipController::class, 'download']);

        Route::prefix('admin')
            ->middleware('role:admin,hr')
            ->group(function () {
                Route::get('/payroll-reports/summary', [PayrollReportAdminController::class, 'summary']);
                Route::get('/payroll-reports/export', [PayrollReportAdminController::class, 'export']);
                Route::get('/payrolls/{payroll}/payslip/download', [PayrollReportAdminController::class, 'downloadPayslip']);
            });
    });
