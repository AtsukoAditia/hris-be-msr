<?php

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;

class OvertimeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'manager', 'employee'], true);
    }

    public function view(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (in_array($user->role, ['admin', 'hr'], true)) {
            return true;
        }

        if ($user->role === 'manager') {
            return $overtimeRequest->employee->manager_id === $user->employee?->id
                || $overtimeRequest->employee_id === $user->employee?->id;
        }

        return $overtimeRequest->employee_id === $user->employee?->id;
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    public function cancel(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $overtimeRequest->isPending()) {
            return false;
        }

        return $overtimeRequest->employee_id === $user->employee?->id
            || in_array($user->role, ['admin', 'hr'], true);
    }

    public function approve(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $overtimeRequest->isPending()) {
            return false;
        }

        if (in_array($user->role, ['admin', 'hr'], true)) {
            return true;
        }

        if ($user->role === 'manager') {
            return $overtimeRequest->employee->manager_id === $user->employee?->id;
        }

        return false;
    }

    public function reject(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->approve($user, $overtimeRequest);
    }

    public function recordActual(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return in_array($user->role, ['admin', 'hr'], true);
    }
}
