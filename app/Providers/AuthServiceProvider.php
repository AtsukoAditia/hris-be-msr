<?php

namespace App\Providers;

use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\ShiftSchedule;
use App\Policies\OvertimePolicyPolicy;
use App\Policies\OvertimeRequestPolicy;
use App\Policies\PayrollPeriodPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\ShiftSchedulePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        OvertimePolicy::class => OvertimePolicyPolicy::class,
        OvertimeRequest::class => OvertimeRequestPolicy::class,
        Payroll::class => PayrollPolicy::class,
        PayrollPeriod::class => PayrollPeriodPolicy::class,
        ShiftSchedule::class => ShiftSchedulePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
