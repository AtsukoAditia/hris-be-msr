<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\OvertimePolicy::class => \App\Policies\OvertimePolicyPolicy::class,
        \App\Models\OvertimeRequest::class => \App\Policies\OvertimeRequestPolicy::class,
        \App\Models\PayrollPeriod::class => \App\Policies\PayrollPeriodPolicy::class,
        \App\Models\Payroll::class => \App\Policies\PayrollPolicy::class,
        \App\Models\ShiftSchedule::class => \App\Policies\ShiftSchedulePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}