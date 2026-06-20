<?php

namespace App\Policies;

use App\Models\OvertimePolicy;
use App\Models\User;

class OvertimePolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr', 'manager', 'employee']);
    }

    public function view(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->hasAnyRole(['admin', 'hr', 'manager', 'employee']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr']);
    }

    public function update(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->hasAnyRole(['admin', 'hr']);
    }

    public function delete(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->hasAnyRole(['admin', 'hr']);
    }
}