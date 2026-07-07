<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr']);
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return in_array($user->role, ['admin', 'hr']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr']);
    }

    public function update(User $user, PayrollPeriod $period): bool
    {
        return in_array($user->role, ['admin', 'hr']) && $period->status === 'draft';
    }

    public function process(User $user, PayrollPeriod $period): bool
    {
        return in_array($user->role, ['admin', 'hr']) && $period->status === 'draft';
    }

    public function finalize(User $user, PayrollPeriod $period): bool
    {
        return in_array($user->role, ['admin', 'hr']) && $period->status === 'processing';
    }

    public function delete(User $user, PayrollPeriod $period): bool
    {
        return $user->role === 'admin' && $period->status === 'draft';
    }
}
