<?php

use App\Http\Controllers\API\Admin\EmployeeSalaryProfileAdminController;
use App\Http\Controllers\API\Admin\PayrollAdminController;
use App\Http\Controllers\API\Admin\PayrollPeriodAdminController;
use App\Http\Controllers\API\Admin\SalaryComponentAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'role:admin,hr'])
    ->group(function () {
        Route::apiResource('/salary-components', SalaryComponentAdminController::class)
            ->parameters(['salary-components' => 'salaryComponent']);

        Route::get('/employees/{employee}/salary-profiles', [EmployeeSalaryProfileAdminController::class, 'index']);
        Route::post('/employees/{employee}/salary-profiles', [EmployeeSalaryProfileAdminController::class, 'store']);
        Route::get('/salary-profiles/{salaryProfile}', [EmployeeSalaryProfileAdminController::class, 'show']);
        Route::put('/salary-profiles/{salaryProfile}', [EmployeeSalaryProfileAdminController::class, 'update']);
        Route::patch('/salary-profiles/{salaryProfile}', [EmployeeSalaryProfileAdminController::class, 'update']);
        Route::delete('/salary-profiles/{salaryProfile}', [EmployeeSalaryProfileAdminController::class, 'destroy']);

        Route::apiResource('/payroll-periods', PayrollPeriodAdminController::class)
            ->parameters(['payroll-periods' => 'payrollPeriod']);
        Route::post('/payroll-periods/{payrollPeriod}/generate', [PayrollAdminController::class, 'generate']);

        Route::get('/payrolls', [PayrollAdminController::class, 'index']);
        Route::get('/payrolls/{payroll}', [PayrollAdminController::class, 'show']);
        Route::post('/payrolls/{payroll}/recalculate', [PayrollAdminController::class, 'recalculate']);
        Route::post('/payrolls/{payroll}/review', [PayrollAdminController::class, 'review']);
        Route::post('/payrolls/{payroll}/finalize', [PayrollAdminController::class, 'finalize']);
        Route::post('/payrolls/{payroll}/paid', [PayrollAdminController::class, 'markPaid']);
        Route::post('/payrolls/{payroll}/cancel', [PayrollAdminController::class, 'cancel']);
    });
