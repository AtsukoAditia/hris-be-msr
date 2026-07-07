<?php

namespace App\Policies;

use App\Models\ShiftSchedule;
use App\Models\User;

class ShiftSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'manager', 'employee']);
    }

    public function view(User $user, ShiftSchedule $schedule): bool
    {
        if (in_array($user->role, ['admin', 'hr'])) {
            return true;
        }

        $employee = $user->employee;

        if (! $employee) {
            return false;
        }

        if ($user->role === 'manager') {
            return $employee->id === $schedule->employee->manager_id;
        }

        return $employee->id === $schedule->employee_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'manager']);
    }

    public function update(User $user, ShiftSchedule $schedule): bool
    {
        if (in_array($user->role, ['admin', 'hr'])) {
            return true;
        }

        $employee = $user->employee;

        if (! $employee) {
            return false;
        }

        if ($user->role === 'manager') {
            return $employee->id === $schedule->employee->manager_id;
        }

        return false;
    }

    public function delete(User $user, ShiftSchedule $schedule): bool
    {
        return in_array($user->role, ['admin', 'hr']);
    }

    public function bulkStore(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'manager']);
    }

    public function copyWeek(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'manager']);
    }
}
