<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'manager', 'employee']);
    }

    public function view(User $user, Payroll $payroll): bool
    {
        if (in_array($user->role, ['admin', 'hr'])) {
            return true;
        }

        return $user->id === $payroll->employee_id;
    }

    public function viewPayslip(User $user, Payroll $payroll): bool
    {
        if (in_array($user->role, ['admin', 'hr'])) {
            return true;
        }

        return $user->id === $payroll->employee_id;
    }

    public function generate(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr']);
    }

    public function finalize(User $user, Payroll $payroll): bool
    {
        return in_array($user->role, ['admin', 'hr']) && $payroll->status === 'draft';
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->role === 'admin' && $payroll->status === 'draft';
    }
}
