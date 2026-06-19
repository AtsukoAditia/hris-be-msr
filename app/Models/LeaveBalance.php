<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'opening_days',
        'used_days',
        'remaining_days',
        'total_days',
    ];

    protected $casts = [
        'opening_days' => 'integer',
        'used_days' => 'integer',
        'remaining_days' => 'integer',
        'year' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function transactions()
    {
        return $this->hasMany(LeaveBalanceTransaction::class);
    }

    public function setTotalDaysAttribute(int $value): void
    {
        $this->attributes['opening_days'] = $value;
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByLeaveType($query, int $leaveTypeId)
    {
        return $query->where('leave_type_id', $leaveTypeId);
    }
}
