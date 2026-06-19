<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_type_id',
        'year',
        'policy_name',
        'default_quota',
        'min_service_months',
        'accrual_type',
        'accrual_amount',
        'max_carry_forward_days',
        'carry_forward_expiry_month',
        'carry_forward_expiry_day',
        'carry_forward_expiry_months',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'year' => 'integer',
        'default_quota' => 'integer',
        'min_service_months' => 'integer',
        'accrual_amount' => 'integer',
        'max_carry_forward_days' => 'integer',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function balanceTransactions()
    {
        return $this->hasManyThrough(
            LeaveBalanceTransaction::class,
            LeaveBalance::class,
            'leave_type_id',
            'leave_balance_id',
            'leave_type_id',
            'id'
        )->where('leave_balances.year', \DB::raw('leave_policies.year'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
